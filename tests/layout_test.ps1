# Layout regression runner: prepares a throwaway test DB with one expired and one
# fresh listing, starts PHP, runs the layout spec, restores config, stops server.
$ErrorActionPreference = 'Stop'

$repo = Split-Path -Parent $PSScriptRoot
$cfg  = Join-Path $repo 'config\config.php'
. (Join-Path $PSScriptRoot '_env.ps1')
$php   = Get-AnimalTool 'ANIMAL_PHP'   'php'   @('C:\xampp\php\php.exe')
$mysql = Get-AnimalTool 'ANIMAL_MYSQL' 'mysql' @('C:\xampp\mysql\bin\mysql.exe')
$port  = Get-AnimalPort
$base  = "http://127.0.0.1:$port"
$orig  = $null
$srv   = $null

try {
  Assert-AnimalMysqlUp $mysql
  if (Repair-AnimalConfigSwap $cfg) { 'recovered config.php left pointing at the test database by an earlier aborted run' }
  $orig = Get-Content -LiteralPath $cfg -Raw
  $orig -replace "'name' => 'animal_marketplace',", "'name' => 'animal_marketplace_test'," |
    Set-Content -LiteralPath $cfg -NoNewline
  if (-not (Select-String -LiteralPath $cfg -Pattern 'animal_marketplace_test' -Quiet)) {
    throw 'config swap failed - refusing to continue'
  }
  # This harness speaks plain http, so a Secure cookie is never stored and no
  # session exists. Restored along with the rest of config in `finally`.
  if (Disable-AnimalSecureCookies $cfg) { 'secure session cookies disabled for this run' }

  & $mysql -u root -e "DROP DATABASE IF EXISTS animal_marketplace_test; CREATE DATABASE animal_marketplace_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | Out-Null
  (Get-Content (Join-Path $repo 'database\schema.sql') -Raw) -replace 'animal_marketplace', 'animal_marketplace_test' | & $mysql -u root 2>&1 | Out-Null
  foreach ($m in (Get-ChildItem (Join-Path $repo 'database\migrations\*.sql') | Sort-Object Name)) {
    $r = ((Get-Content $m.FullName -Raw) -replace 'animal_marketplace', 'animal_marketplace_test') | & $mysql -u root --database=animal_marketplace_test 2>&1
    if ($r -match 'ERROR') { "MIGRATION FAIL $($m.Name): $r" }
  }

  $ids = & $php (Join-Path $repo 'tests\http_seed.php') 2>&1 | ConvertFrom-Json

  # Seed leaves the fixture out of stock, and the view only lists rows with
  # quantity > 0. Give both listings stock so they reach the browse grid.
  & $mysql -u root animal_marketplace_test -e "
    UPDATE produce_listings SET available_quantity=50.0000, minimum_order_qty=1.0000 WHERE id=$($ids.listing);" 2>&1 | Out-Null

  # FR-LIST-033 needs both cases present: one listing past its best-before date
  # (must stay visible but be labelled) and one still fresh (must NOT be labelled).
  & $mysql -u root animal_marketplace_test -e "
    UPDATE produce_listings SET expiry_date = CURDATE() - INTERVAL 1 DAY WHERE id=$($ids.listing);
    INSERT INTO produce_listings (farmer_id, category_id, unit_id, name, price_per_unit, status,
                                  available_quantity, minimum_order_qty, expiry_date, published_at)
    SELECT farmer_id, category_id, unit_id, 'Layout Fresh Produce', 30.00, 'active',
           50.0000, 1.0000, CURDATE() + INTERVAL 30 DAY, NOW()
    FROM produce_listings WHERE id=$($ids.listing);" 2>&1 | Out-Null

  # Add a price figure wide enough to expose a too-narrow card at mobile width.
  & $mysql -u root animal_marketplace_test -e "
    UPDATE produce_listings SET price_per_unit = 1234567.89 WHERE id=$($ids.listing);" 2>&1 | Out-Null

  $listed = & $mysql -u root animal_marketplace_test -N -B -e "SELECT COUNT(*) FROM v_active_listings;" 2>&1
  "test DB has $listed listings visible in the browse view"

  $srv = Start-Process -FilePath $php -ArgumentList '-S', "127.0.0.1:$port", '-t', 'public' `
          -WorkingDirectory $repo -WindowStyle Hidden -PassThru
  if (-not (Wait-AnimalServer -Port $port)) { throw "php -S did not come up on port $port" }
  "dev server up on $port (PID $($srv.Id))"

  $env:BASE_URL = $base
  & node (Join-Path $PSScriptRoot 'e2e\layout.js')
  if ($LASTEXITCODE -ne 0) { throw "layout spec failed (exit $LASTEXITCODE)" }
  '--- layout regression passed ---'
}
finally {
  if ($srv)  { Stop-Process -Id $srv.Id -Force -ErrorAction SilentlyContinue }
  if ($orig) { [System.IO.File]::WriteAllText($cfg, $orig) }
  'config restored'
}