# Browser E2E runner: prepares throwaway test DB, starts PHP, runs smoke spec, restores config, stops server.
$ErrorActionPreference = 'Stop'

$repo  = Split-Path -Parent $PSScriptRoot
$cfg   = Join-Path $repo 'config\config.php'
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
  # This harness speaks plain http, so a Secure session cookie is never stored and
  # no session exists. Without this the suite passes or fails depending on what
  # secure_cookies happened to be in config.php when it started -- it inherits
  # whatever a previously-run suite left behind rather than setting its own
  # precondition. Restored along with the rest of config in `finally`.
  if (Disable-AnimalSecureCookies $cfg) { 'secure session cookies disabled for this run' }

  & $mysql -u root -e "DROP DATABASE IF EXISTS animal_marketplace_test; CREATE DATABASE animal_marketplace_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | Out-Null
  (Get-Content (Join-Path $repo 'database\schema.sql') -Raw) -replace 'animal_marketplace', 'animal_marketplace_test' | & $mysql -u root 2>&1 | Out-Null
  foreach ($m in (Get-ChildItem (Join-Path $repo 'database\migrations\*.sql') | Sort-Object Name)) {
    $r = ((Get-Content $m.FullName -Raw) -replace 'animal_marketplace', 'animal_marketplace_test') | & $mysql -u root --database=animal_marketplace_test 2>&1
    if ($r -match 'ERROR') { "MIGRATION FAIL $($m.Name): $r" }
  }

  $ids = & $php (Join-Path $repo 'tests\http_seed.php') 2>&1 | ConvertFrom-Json
  # Seed leaves fixture out of stock; page renders no order form.
  & $mysql -u root animal_marketplace_test -e "UPDATE produce_listings SET available_quantity=50.0000, minimum_order_qty=1.0000 WHERE id=$($ids.listing)" 2>&1 | Out-Null

  $srv = Start-Process -FilePath $php -ArgumentList '-S', "127.0.0.1:$port", '-t', 'public' `
          -WorkingDirectory $repo -WindowStyle Hidden -PassThru
  if (-not (Wait-AnimalServer -Port $port)) { throw "php -S did not come up on port $port" }
  "dev server up on $port (PID $($srv.Id))"

  $env:BASE_URL   = $base
  $env:LISTING_ID = "$($ids.listing)"
  & node (Join-Path $PSScriptRoot 'e2e\smoke.js')
  if ($LASTEXITCODE -ne 0) { throw "e2e smoke failed (exit $LASTEXITCODE)" }
  '--- browser e2e passed ---'
}
finally {
  if ($srv) { Stop-Process -Id $srv.Id -Force -ErrorAction SilentlyContinue }
  if ($orig) { [System.IO.File]::WriteAllText($cfg, $orig) }
  Remove-Item Env:\BASE_URL -ErrorAction SilentlyContinue
  Remove-Item Env:\LISTING_ID -ErrorAction SilentlyContinue
  'config restored'
}
