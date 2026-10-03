# Browser E2E runner: prepares the throwaway test database, starts PHP, runs the
# smoke spec, then restores config and stops the server.
$ErrorActionPreference = 'Stop'

$repo  = Split-Path -Parent $PSScriptRoot
$cfg   = Join-Path $repo 'config\config.php'
$mysql = 'C:\xampp\mysql\bin\mysql.exe'
$php   = 'C:\xampp\php\php.exe'
$base  = 'http://127.0.0.1:8000'
$orig  = $null
$srv   = $null

try {
  $orig = Get-Content -LiteralPath $cfg -Raw
  $orig -replace "'name' => 'animal_marketplace',", "'name' => 'animal_marketplace_test'," |
    Set-Content -LiteralPath $cfg -NoNewline
  if (-not (Select-String -LiteralPath $cfg -Pattern 'animal_marketplace_test' -Quiet)) {
    throw 'config swap failed - refusing to continue'
  }

  & $mysql -u root -e "DROP DATABASE IF EXISTS animal_marketplace_test; CREATE DATABASE animal_marketplace_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | Out-Null
  (Get-Content (Join-Path $repo 'database\schema.sql') -Raw) -replace 'animal_marketplace', 'animal_marketplace_test' | & $mysql -u root 2>&1 | Out-Null
  foreach ($m in (Get-ChildItem (Join-Path $repo 'database\migrations\*.sql') | Sort-Object Name)) {
    $r = ((Get-Content $m.FullName -Raw) -replace 'animal_marketplace', 'animal_marketplace_test') | & $mysql -u root --database=animal_marketplace_test 2>&1
    if ($r -match 'ERROR') { "MIGRATION FAIL $($m.Name): $r" }
  }

  $ids = & $php (Join-Path $repo 'tests\http_seed.php') 2>&1 | ConvertFrom-Json
  # the seed leaves the fixture out of stock, so the page renders no order form
  & $mysql -u root animal_marketplace_test -e "UPDATE produce_listings SET available_quantity=50.0000, minimum_order_qty=1.0000 WHERE id=$($ids.listing)" 2>&1 | Out-Null

  $srv = Start-Process -FilePath $php -ArgumentList '-S', '127.0.0.1:8000', '-t', 'public' `
          -WorkingDirectory $repo -WindowStyle Hidden -PassThru
  for ($i = 0; $i -lt 40; $i++) {
    Start-Sleep -Milliseconds 250
    if (Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue) { break }
  }
  "dev server up on 8000 (PID $($srv.Id))"

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
