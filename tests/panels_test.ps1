# Showcase panel and workflow check: sidebar links resolve, role gating holds, admin
# workflows do something observable. Throwaway database, config restored in `finally`.
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
$script:pass = 0; $script:fail = 0

function ok($label, $cond, $detail) {
  if ($null -eq $cond) { $cond = $false }
  if ($detail -isnot [string]) { $detail = '' }
  $detail = ($detail -replace '\s+', ' ').Trim()
  if ($detail.Length -gt 130) { $detail = $detail.Substring(0, 130) + '...' }
  if ($cond) { $script:pass++; "  PASS  $label" }
  else { $script:fail++; $d = ''; if ($detail) { $d = "  <- $detail" }; "  FAIL  $label$d" }
}
function csrf($html) {
  if ($html -match 'name="csrf_token"\s+value="([^"]+)"') { return $Matches[1] }
  return $null
}
function get($url, $s) {
  try { (Invoke-WebRequest -Uri $url -WebSession $s -UseBasicParsing -TimeoutSec 20).Content }
  catch { "HTTPERR: $($_.Exception.Message)" }
}
function post($url, $s, $body) {
  try { (Invoke-WebRequest -Uri $url -WebSession $s -UseBasicParsing -TimeoutSec 20 -Method Post -Body $body).Content }
  catch { "HTTPERR: $($_.Exception.Message)" }
}
function dbq($sql) {
  ((& $mysql -u root animal_marketplace_test -N -B -e $sql 2>&1) | Out-String).Trim()
}
function login($email, $s, $password) {
  if (-not $password) { $password = 'password123' }
  $c = get "$base/login.php" $s
  post "$base/login.php" $s @{ csrf_token = (csrf $c); email = $email; password = $password }
}
# a page with a PHP error still returns 200, so check the body
function assertRealPage($label, $html) {
  if ($html -match 'HTTPERR') { ok $label $false $html; return }
  $noise = [regex]::Match($html, '(?i)(Fatal error|Parse error|Warning:|Notice:|Deprecated:|Uncaught)[^<]{0,120}')
  if ($noise.Success) { ok $label $false $noise.Value; return }
  if ($html.Length -lt 400) { ok $label $false "only $($html.Length) bytes rendered"; return }
  ok $label $true ''
}

try {
  Assert-AnimalMysqlUp $mysql
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

  # the seed creates only APPROVED farmers; add one pending so the queue is not empty
  & $mysql -u root animal_marketplace_test -e "INSERT INTO users (email,password_hash,role,status) SELECT 'http-pending@x.test', password_hash, 'farmer', 'active' FROM users WHERE id=$($ids.farmer);" 2>&1 | Out-Null
  $pendingId = dbq "SELECT id FROM users WHERE email='http-pending@x.test';"
  & $mysql -u root animal_marketplace_test -e "INSERT INTO farmer_profiles (user_id,farm_name,farm_description,contact_person,contact_phone,contact_email,address,barangay,municipality_city,province,region,valid_id_type,valid_id_number,verification_status) VALUES ($pendingId,'Pending Farm','awaiting review','P farmer','09171234567','p@x.test','7 Rural Rd','San Isidro','Bayambang','Pampanga','Pangasinan','national_id','ID-PENDING','pending');" 2>&1 | Out-Null
  "seeded ids: farmer=$($ids.farmer) admin=$($ids.admin) pending=$pendingId"

  $srv = Start-Process -FilePath $php -ArgumentList '-S', "127.0.0.1:$port", '-t', 'public' `
          -WorkingDirectory $repo -WindowStyle Hidden -PassThru
  if (-not (Wait-AnimalServer -Port $port)) { throw "php -S did not come up on port $port" }
  "dev server up on $port (PID $($srv.Id))"

  $menus = @{
    buyer  = @('Dashboard','Browse','Cart','My Orders','Saved')
    farmer = @('Dashboard','My Listings','New Listing','Orders','Analytics')
    admin  = @('Dashboard','Orders','Verifications','Audit Log','Reports')
  }
  $creds = @{ buyer = 'http-block@x.test'; farmer = 'http-farmer@x.test'; admin = 'http-admin@x.test' }

  # shared labels prove nothing; scope to role-exclusive ones
  $exclusive = @{}
  foreach ($r in $menus.Keys) {
    $others = @($menus.Keys | Where-Object { $_ -ne $r } | ForEach-Object { $menus[$_] })
    $exclusive[$r] = @($menus[$r] | Where-Object { $others -notcontains $_ })
  }

  foreach ($role in @('buyer', 'farmer', 'admin')) {
    "`n--- $role panel ---"
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    login $creds[$role] $s
    $d = get "$base/dashboard.php" $s
    assertRealPage "$role reaches its dashboard" $d

    foreach ($label in $menus[$role]) {
      ok "$role sidebar shows '$label'" ($d -match [regex]::Escape(">$label<") -or $d -match [regex]::Escape(">$label ")) 'label missing'
    }
    # a role-exclusive item visible to the wrong role means no gating
    foreach ($other in $exclusive.Keys) {
      if ($other -eq $role) { continue }
      $leaked = $exclusive[$other] | Where-Object { $d -match [regex]::Escape(">$_<") }
      if ($leaked) { ok "$role cannot see $other-only nav" $false ("leaked: " + ($leaked -join ', ')) }
    }
  }

  "`n--- every menu target renders ---"
  $targets = @(
    @{ role = 'buyer';  href = 'browse.php' },        @{ role = 'buyer';  href = 'cart.php' },
    @{ role = 'farmer'; href = 'create_listing.php' },
    @{ role = 'admin';  href = 'admin_verify.php' },  @{ role = 'admin';  href = 'admin_audit.php' },
    @{ role = 'admin';  href = 'admin_reports.php' }
  )
  $sessions = @{}
  foreach ($role in @('buyer', 'farmer', 'admin')) {
    $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    login $creds[$role] $s
    $sessions[$role] = $s
  }
  foreach ($t in $targets) {
    $page = get "$base/$($t.href)" $sessions[$t.role]
    assertRealPage "$($t.role) can open $($t.href)" $page
  }

  "`n--- dashboard sections behind the anchors ---"
  foreach ($anchor in @('listings', 'orders', 'analytics')) {
    $p = get "$base/dashboard.php#$anchor" $sessions['farmer']
    assertRealPage "farmer dashboard#$anchor renders" $p
    ok "farmer dashboard has an '$anchor' section" ($p -match "id=`"$anchor`"") 'no element with that id'
  }
  foreach ($anchor in @('orders', 'saved')) {
    $p = get "$base/dashboard.php#$anchor" $sessions['buyer']
    assertRealPage "buyer dashboard#$anchor renders" $p
    ok "buyer dashboard has an '$anchor' section" ($p -match "id=`"$anchor`"") 'no element with that id'
  }

  "`n--- role gating ---"
  foreach ($pair in @(@{ as = 'buyer'; href = 'admin_verify.php' }, @{ as = 'farmer'; href = 'admin_audit.php' })) {
    $p = get "$base/$($pair.href)" $sessions[$pair.as]
    ok "$($pair.as) is refused $($pair.href)" ($p -match 'HTTPERR' -or $p -match 'not authorized|forbidden|denied|Access') 'page was served'
  }

  "`n--- admin workflow: verify the pending farmer ---"
  $a = $sessions['admin']
  $v = get "$base/admin_verify.php" $a
  assertRealPage 'admin opens the verification queue' $v
  ok 'the pending farmer appears in the queue' ($v -match 'http-pending@x\.test' -or $v -match 'Pending Farm') 'queue is empty'

  $pendingId = dbq "SELECT user_id FROM farmer_profiles WHERE verification_status='pending' LIMIT 1;"
  $before = dbq "SELECT verification_status FROM farmer_profiles WHERE user_id=$pendingId;"
  ok 'fixture starts with a pending farmer' ($before -eq 'pending') "status was '$before'"

  $form = get "$base/admin_verify.php" $a
  $t = csrf $form
  $resp = post "$base/admin_verify.php" $a @{
    csrf_token = $t; action = 'approve'; user_id = $pendingId
    valid_id_type = 'national_id'; valid_id_number = 'ID-PENDING'
    verification_notes = 'showcase approval'
  }
  $after = dbq "SELECT verification_status FROM farmer_profiles WHERE user_id=$pendingId;"
  ok 'approving flips the farmer to approved' ($after -eq 'approved') "status is '$after'"
  ok 'the approval is confirmed back to the admin' (($resp -match 'approv') -and ($resp -notmatch 'HTTPERR')) 'no confirmation in the response'

  "`n--- admin workflow: audit log records it ---"
  $al = get "$base/admin_audit.php" $a
  assertRealPage 'admin opens the audit log' $al
  ok 'the audit log shows the approval just made' ($al -match 'approve|verification') 'no verification entry'

  "`n--- admin workflow: reports ---"
  $rp = get "$base/admin_reports.php" $a
  assertRealPage 'admin opens reports' $rp
  ok 'reports page offers at least one report or export' ($rp -match 'report|export|csv|pdf|download') 'no report controls found'

  # the PDF is built by hand, so check the bytes that come back over HTTP
  try {
    $pdf = Invoke-WebRequest -Uri "$base/admin_reports.php?t=orders&export=pdf" -WebSession $a -UseBasicParsing -TimeoutSec 20
    $bytes = if ($pdf.Content -is [byte[]]) { $pdf.Content } else { [System.Text.Encoding]::GetEncoding('ISO-8859-1').GetBytes([string]$pdf.Content) }
    $head = if ($bytes.Length -ge 8) { [System.Text.Encoding]::ASCII.GetString($bytes[0..7]) } else { '' }
    ok 'the PDF export returns a real PDF' ($pdf.StatusCode -eq 200 -and $head.StartsWith('%PDF-')) "status $($pdf.StatusCode), head '$head'"
    ok 'the PDF export is served as application/pdf' ("$($pdf.Headers['Content-Type'])" -match 'application/pdf') "content-type '$($pdf.Headers['Content-Type'])'"
    ok 'the PDF export is not empty' ($pdf.RawContentLength -gt 400) "$($pdf.RawContentLength) bytes"
    $tail = [System.Text.Encoding]::ASCII.GetString($bytes[($bytes.Length - 5)..($bytes.Length - 1)])
    ok 'the PDF export is terminated' ($tail -match '%%EOF') "tail '$tail'"
  }
  catch {
    ok 'the PDF export returns a real PDF' $false $_.Exception.Message
  }

  "`n================ $script:pass passed, $script:fail failed ================"
  if ($script:fail -gt 0) { throw "$script:fail panel check(s) failed" }
}
finally {
  # only ever stops its own server: a dev server on 8000 is left untouched
  if ($srv) { Stop-Process -Id $srv.Id -Force -ErrorAction SilentlyContinue }
  if ($orig) { [System.IO.File]::WriteAllText($cfg, $orig) }
  'config restored'
}
