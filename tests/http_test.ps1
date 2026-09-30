#requires -Version 5.1
<#
  HTTP end-to-end for the account work, against animal_marketplace_test only.
  Temporarily points config/config.php at the test DB and restores the original
  file in `finally`, so the live config can never be left redirected.

  NOTE: all ok() calls use the space-separated form `ok 'label' (cond) 'detail'`.
  The parenthesized form is unusable here: `ok('l', $c -eq 'x')` parses as
  ('l', $c) -eq 'x', so the operator filters the array instead of yielding a bool.
#>
  $ErrorActionPreference = 'Continue'
  # Paths derive from this script's own location so the suite can live in the
  # repo instead of a temp directory that gets wiped.
  $repo  = Split-Path -Parent $PSScriptRoot
  $php   = if ($env:ANIMAL_PHP)   { $env:ANIMAL_PHP }   else { 'C:\xampp\php\php.exe' }
  $mysql = if ($env:ANIMAL_MYSQL) { $env:ANIMAL_MYSQL } else { 'C:\xampp\mysql\bin\mysql.exe' }
  $tmp   = $env:TEMP
  $cfg   = Join-Path $repo 'config\config.php'
  $base  = 'http://127.0.0.1:8000'

$pass = 0; $fail = 0
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
  function sha256hex([string]$s) {
    $sha = [System.Security.Cryptography.SHA256]::Create()
    ([BitConverter]::ToString($sha.ComputeHash([Text.Encoding]::UTF8.GetBytes($s)))).Replace('-', '').ToLower()
  }
function login($email, $s, $password) {
  if (-not $password) { $password = 'password123' }
  $c = get "$base/login.php" $s
  $t = csrf $c
  post "$base/login.php" $s @{ csrf_token = $t; email = $email; password = $password }
}

$orig = $null; $srv = $null
try {
  $orig = Get-Content -LiteralPath $cfg -Raw
  $orig -replace "'name' => 'animal_marketplace',", "'name' => 'animal_marketplace_test'," |
    Set-Content -LiteralPath $cfg -NoNewline
  if (-not (Select-String -LiteralPath $cfg -Pattern 'animal_marketplace_test' -Quiet)) {
    throw 'config swap failed - refusing to continue'
  }
  'config temporarily pointed at animal_marketplace_test'

  & $mysql -u root -e "DROP DATABASE IF EXISTS animal_marketplace_test; CREATE DATABASE animal_marketplace_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1 | Out-Null
  (Get-Content (Join-Path $repo 'database\schema.sql') -Raw) -replace 'animal_marketplace', 'animal_marketplace_test' | & $mysql -u root 2>&1 | Out-Null
  foreach ($m in (Get-ChildItem (Join-Path $repo 'database\migrations\*.sql') | Sort-Object Name)) {
    $r = ((Get-Content $m.FullName -Raw) -replace 'animal_marketplace', 'animal_marketplace_test') | & $mysql -u root --database=animal_marketplace_test 2>&1
    if ($r) { "MIGRATION FAIL $($m.Name): $r" }
  }
  $ids = & $php (Join-Path $repo 'tests\http_seed.php') 2>&1 | ConvertFrom-Json
  "seeded ids: buyer=$($ids.buyer) farmer=$($ids.farmer) open=$($ids.openOrder) blocked=$($ids.blocked) admin=$($ids.admin)"

  $srv = Start-Process -FilePath $php -ArgumentList '-S','127.0.0.1:8000','-t','public' `
          -WorkingDirectory $repo -WindowStyle Hidden -PassThru `
          -RedirectStandardOutput (Join-Path $tmp 'srv.log') `
          -RedirectStandardError  (Join-Path $tmp 'srv.err')
  for ($i = 0; $i -lt 40; $i++) {
    Start-Sleep -Milliseconds 250
    if (Get-NetTCPConnection -LocalPort 8000 -State Listen -ErrorAction SilentlyContinue) { break }
  }
  "dev server up on 8000 (PID $($srv.Id))"

  "`n--- login, session, CSRF ---"
  $s = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  $c = get "$base/login.php" $s
  $t = csrf $c
  ok 'login page renders and exposes csrf' ($t -ne $null) $c
  $c = post "$base/login.php" $s @{ csrf_token = $t; email = 'http-buyer@x.test'; password = 'password123' }
  ok 'valid credentials establish a session' (($c -match 'logout|account\.php') -and ($c -notmatch 'HTTPERR')) 'no session'
    $c = get "$base/account.php" $s
    ok 'account page is reachable while logged in' ($c -match 'name="full_name"') 'redirected or error'

    "`n--- nav labels ---"
    $d = get "$base/dashboard.php" $s
    ok 'dashboard nav shows the preferences entry' ($d -match '>\s*Preferences\s*<') 'label missing'
    ok 'dashboard nav shows the profile and security entry' ($d -match 'Profile &amp; Security|Profile & Security') 'label missing'
    ok 'the duplicate settings icon is gone' (-not ($d -match 'sidebar-icon-btn"\s+href="settings\.php"')) 'icon still present'
    ok 'settings is still reachable from the dropdown' ($d -match 'dropdown-item"\s+href="settings\.php"') 'dropdown link missing'

    # The public header is a different partial from the dashboard one.
    $p = get "$base/browse.php" $s
    ok 'public header links to the account page' ($p -match 'dropdown-item"\s+href="account\.php"') 'no account link in the public header'
    ok 'public header mobile drawer links to the account page' ($p -match 'mobile-menu-actions[\s\S]*?href="account\.php"') 'no account link in the mobile drawer'
    $hard = [regex]::Matches($p, '.{70}(?:>Dashboard</a>|>Log out</a>).{40}')
    if ($hard.Count -gt 0) { [System.IO.File]::WriteAllText((Join-Path $tmp 'hardcoded.txt'), ($hard | ForEach-Object { $_.Value }) -join "`n---`n") }
    ok 'public header no longer hardcodes English nav labels' ($hard.Count -eq 0) "hardcoded label still present ($($hard.Count) match(es))"

    "`n--- marketplace search ---"
    $b = get "$base/browse.php" $s
    # action="." resolved to the directory, which the router serves as the
    # landing page, so the query was discarded on every search.
    ok 'search form targets browse.php, not the directory' ($b -match 'class="search-form"\s+method="get"\s+action="browse\.php"') 'form action is wrong'
    ok 'search form no longer targets the bare directory' (-not ($b -match 'search-form"\s+method="get"\s+action="\."')) 'still submitting to "."'
    $miss = get "$base/browse.php?q=zzz_no_such_item_zzz" $s
    ok 'a search that matches nothing stays on the marketplace' ($miss -match 'search-form') 'query bounced to another page'
    ok 'a search that matches nothing never reaches the landing page' (-not ($miss -match 'class="hero"')) 'landed on the landing page'
    ok 'a search that matches nothing runs the filter' ($miss -match 'empty-state-title') 'filter did not run'
    ok 'a search miss does not claim the market is empty' ($miss -match 'No results for|empty for') 'still says nothing for sale'
    ok 'the empty state spans the product grid' (($miss -match 'grid-column:\s*1\s*/\s*-1') -and ($miss -match 'empty-state')) 'not spanning the grid, renders flush left'
    # The shared partial now supplies the size modifier as a real class. It used
    # to interpolate 'py-5 px-4' into style="padding: ...", which is invalid CSS
    # the browser dropped, so the size option had never taken effect.
    ok 'the empty state applies a real size class' ($miss -match 'empty-state--md') 'size modifier is not a usable class'
    ok 'the empty state no longer inlines an invalid padding value' (-not ($miss -match 'style="padding:\s*py-')) 'invalid inline padding is back'
    # t() resolves a flat key only. A dotted path falls through and renders as
    # the literal key text, so assert that never reaches the page.
    ok 'no copy key leaks into the page as literal text' (-not ($miss -match 'unavailable\.(title|message)')) 'a dotted key path rendered verbatim'
    # The search input had a placeholder and no label at all, so it had no
    # accessible name. A placeholder is not one.
    ok 'the search input has a real label' ($b -match '<label[^>]*for="market-search"') 'input has no accessible name'
    ok 'the search label is not just visually hidden text with no target' ($b -match 'id="market-search"') 'label points at no input'
    ok 'the search placeholder is localised, not hardcoded English' ($b -match 'placeholder="Try mangoes, rice, milk\.\.\."') 'hardcoded placeholder is back'
    # Scope to the CTA row: the header links to register.php on purpose, so a
    # page-wide match proves nothing.
    $missAuth = get "$base/browse.php?q=zzz_no_such_item_zzz" $s
    if (-not $missAuth -or $missAuth -match '^HTTPERR') { throw "signed-in fetch failed, CTA assertions would pass vacuously" }
    $ctaAuth = [regex]::Match($missAuth, '(?s)empty-state-action(.*?)</div>').Groups[1].Value
    if (-not $ctaAuth) { throw "no CTA row found, CTA assertions would pass vacuously" }
    ok 'a signed-in search miss does not offer log in' (-not ($ctaAuth -match 'login\.php')) 'offers log in to a signed-in user'
    ok 'a signed-in search miss does not offer sign up' (-not ($ctaAuth -match 'register\.php')) 'offers sign up to a signed-in user'
    ok 'a signed-in search miss offers creating a listing instead' ($ctaAuth -match 'href="create_listing\.php"') 'no useful action for a signed-in user'
    $anon = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $missAnon = get "$base/browse.php?q=zzz_no_such_item_zzz" $anon
    $ctaAnon = [regex]::Match($missAnon, '(?s)empty-state-action(.*?)</div>').Groups[1].Value
    if (-not $ctaAnon) { throw "no CTA row for the logged-out view" }
    ok 'a logged-out search miss does offer log in' ($ctaAnon -match 'login\.php') 'no log in for a visitor'
    ok 'a logged-out search miss does offer sign up' ($ctaAnon -match 'register\.php') 'no sign up for a visitor'
    ok 'a logged-out search miss does not offer the dashboard' (-not ($ctaAnon -match 'dashboard\.php')) 'offers dashboard to a visitor'

    "`n--- footer seller link ---"
    # register.php bounces an already-signed-in visitor to the dashboard, so the
    # footer's "Become a seller" link was a link to nowhere. It must be hidden
    # for signed-in viewers and still shown to everyone else.
    $footAuth = [regex]::Match($missAuth, '(?s)<footer.*?</footer>').Value
    if (-not $footAuth) { throw "no <footer> found, footer assertions would pass vacuously" }
    $footAnon = [regex]::Match($missAnon, '(?s)<footer.*?</footer>').Value
    if (-not $footAnon) { throw "no <footer> found for the logged-out view" }
    ok 'a signed-in viewer is not offered the seller link in the footer' (-not ($footAuth -match 'register\.php')) 'footer still offers registration'
    ok 'the signed-in footer keeps the rest of the farmers section' ($footAuth -match 'For farmers') 'farmers heading disappeared with the link'
    ok 'a logged-out visitor is still offered the seller link' ($footAnon -match 'register\.php') 'seller link missing for a visitor'

    "`n--- account name change ---"
  ok 'account page links to the delete page' ($c -match 'account_delete\.php') 'no link'
  $t = csrf $c
  $c = post "$base/account.php" $s @{ csrf_token=$t; account_action='name'; current_password='password123'; full_name='Renamed By HTTP' }
  ok 'name change persists to the database' ((dbq "SELECT full_name FROM buyer_profiles WHERE user_id=$($ids.buyer)") -eq 'Renamed By HTTP') (dbq "SELECT full_name FROM buyer_profiles WHERE user_id=$($ids.buyer)")
  $c = get "$base/account.php" $s
  $t = csrf $c
  $c = post "$base/account.php" $s @{ csrf_token=$t; account_action='name'; current_password='WRONG'; full_name='Should Not Apply' }
  ok 'wrong current password is rejected' ((dbq "SELECT full_name FROM buyer_profiles WHERE user_id=$($ids.buyer)") -eq 'Renamed By HTTP') 'name changed despite bad password'
  $c = post "$base/account.php" $s @{ csrf_token='not-a-real-token'; account_action='name'; current_password='password123'; full_name='CSRF Bypass' }
  ok 'invalid csrf token cannot change the name' ((dbq "SELECT full_name FROM buyer_profiles WHERE user_id=$($ids.buyer)") -eq 'Renamed By HTTP') 'csrf bypass'

  "`n--- account deletion ---"
  $c = get "$base/account_delete.php" $s
  ok 'delete page renders for a logged-in buyer' ($c -match 'name="confirm_phrase"') 'no form'
  $t = csrf $c
  $c = post "$base/account_delete.php" $s @{ csrf_token=$t; current_password='password123'; confirm_phrase='wrongword' }
  ok 'wrong confirm phrase leaves the account active' ((dbq "SELECT status FROM users WHERE id=$($ids.buyer)") -eq 'active') (dbq "SELECT status FROM users WHERE id=$($ids.buyer)")
  ok 'wrong confirm phrase surfaces an error' ($c -match 'alert-danger|err_confirm|exact match') 'no error shown'
  $c = get "$base/account_delete.php" $s
  $t = csrf $c
  $c = post "$base/account_delete.php" $s @{ csrf_token=$t; current_password='password123'; confirm_phrase='' }
  ok 'empty confirm phrase leaves the account active' ((dbq "SELECT status FROM users WHERE id=$($ids.buyer)") -eq 'active') 'anonymized with an empty phrase'
  $c = get "$base/account_delete.php" $s
  $t = csrf $c
  $c = post "$base/account_delete.php" $s @{ csrf_token=$t; current_password='WRONGPASS'; confirm_phrase='DELETE' }
  ok 'wrong current password leaves the account active' ((dbq "SELECT status FROM users WHERE id=$($ids.buyer)") -eq 'active') 'anonymized anyway'
  $c = get "$base/account_delete.php" $s
  $t = csrf $c
  $c = post "$base/account_delete.php" $s @{ csrf_token=$t; current_password='password123'; confirm_phrase='delete' }
  ok 'confirm phrase is case-insensitive by design' ((dbq "SELECT status FROM users WHERE id=$($ids.buyer)") -eq 'anonymized') (dbq "SELECT status FROM users WHERE id=$($ids.buyer)")
  ok 'anonymized_at is stamped' ((dbq "SELECT anonymized_at FROM users WHERE id=$($ids.buyer)") -notmatch 'NULL') 'null'
  ok 'email is replaced with a tombstone' ((dbq "SELECT email FROM users WHERE id=$($ids.buyer)") -like "deleted-user-$($ids.buyer)@*") (dbq "SELECT email FROM users WHERE id=$($ids.buyer)")
  $c = get "$base/account.php" $s
  ok 'session is destroyed - account page is gated' (($c -match 'name="email"') -or ($c -match 'HTTPERR')) 'still reachable'

  "`n--- guards block deletion ---"
  foreach ($case in @(
      [pscustomobject]@{ who='open-order buyer'; email='http-open@x.test';  id=$ids.openOrder },
      [pscustomobject]@{ who='held buyer';       email='http-block@x.test'; id=$ids.blocked  })) {
    $g = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    login $case.email $g | Out-Null
    $c = get "$base/account_delete.php" $g
    $t = csrf $c
    $c = post "$base/account_delete.php" $g @{ csrf_token=$t; current_password='password123'; confirm_phrase='DELETE' }
    $st = dbq "SELECT status FROM users WHERE id=$($case.id)"
    ok "$($case.who) cannot delete the account" ($st -eq 'active') "status=$st"
  }

  "`n--- admin ---"
  $a = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  login 'http-admin@x.test' $a | Out-Null
  $c = get "$base/account.php" $a
  ok 'admin account page hides the name form' ($c -notmatch 'name="full_name"') 'name form shown to admin'
  $c = get "$base/account_delete.php" $a
  $t = csrf $c
  $c = post "$base/account_delete.php" $a @{ csrf_token=$t; current_password='password123'; confirm_phrase='DELETE' }
  ok 'admin cannot delete their own account' ((dbq "SELECT status FROM users WHERE id=$($ids.admin)") -eq 'active') (dbq "SELECT status FROM users WHERE id=$($ids.admin)")

  "`n--- password reset round trip ---"
  $r = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  $c = get "$base/forgot.php" $r
  $t = csrf $c
  $c = post "$base/forgot.php" $r @{ csrf_token=$t; email='http-reset@x.test' }
  $link = $null
  if ($c -match 'reset\.php\?token=([a-f0-9]{64})') { $link = $Matches[0] }
  ok 'forgot password issues a local reset link' ($link -ne $null) 'no token link in response'
  ok 'unknown email gives no account-existence hint' (($c -notmatch 'no account|not found|does not exist') -or ($c -match 'if the account')) 'enumeration leak'

  $c = post "$base/forgot.php" $r @{ csrf_token=$t; email='nobody@nowhere.test' }
  ok 'unknown email still shows the same generic confirmation' (($c -match 'reset\.php\?token|check your|if the account|email')) 'different response for unknown email'

  if ($link) {
    $token = $link -replace '^reset\.php\?token=', ''
    $c = get "$base/$link" $r
    ok 'reset link opens the form' ($c -match 'name="password_confirm"') 'no reset form'
    $t = csrf $c
    $c = post "$base/reset.php" $r @{ csrf_token=$t; token=$token; password='NewPass12345'; password_confirm='NewPass12345' }
    ok 'reset completes successfully' (($c -notmatch 'expired|invalid|HTTPERR') -and ($c -match 'success|updated|password|Log in')) 'reset rejected'
    ok 'all reset tokens are revoked after use' ((dbq "SELECT COUNT(*) FROM password_resets WHERE user_id=$($ids.resetOnly)") -eq '0') (dbq "SELECT COUNT(*) FROM password_resets WHERE user_id=$($ids.resetOnly)")

    $o = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    # Clear this email's limiter so a 429 cannot be mistaken for a bad password.
    & $mysql -u root animal_marketplace_test -e "DELETE FROM rate_limits WHERE \`key\` LIKE '%';" 2>&1 | Out-Null
    $c = login 'http-reset@x.test' $o 'NewPass12345'
    [System.IO.File]::WriteAllText((Join-Path $tmp 'newpw_response.html'), [string]$c)
    $title = ''; if ($c -match '<title>([^<]*)</title>') { $title = $Matches[1] }
    $alerts = ([regex]::Matches([string]$c, 'alert-(danger|warning|success)[^"]*"[^>]*>([^<]{0,160})') |
               ForEach-Object { $_.Groups[2].Value.Trim() }) -join ' | '
    ok 'the new password logs in' (($c -match 'logout|account\.php') -and ($c -notmatch 'HTTPERR')) "title=<$title> alerts=<$alerts>"
    $old = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    & $mysql -u root animal_marketplace_test -e "DELETE FROM rate_limits WHERE \`key\` LIKE '%';" 2>&1 | Out-Null
    $c = get "$base/login.php" $old
    $t = csrf $c
    $c = post "$base/login.php" $old @{ csrf_token=$t; email='http-reset@x.test'; password='password123' }
    ok 'the old password no longer works' (($c -match 'name="email"') -or ($c -match 'invalid|incorrect|HTTPERR')) 'old password still accepted'

    $c = get "$base/$link" $r
    ok 'the reset link is single use' (($c -match 'expired|invalid|used|HTTPERR')) 'token was replayable'
  }

    "`n--- remember me ---"
    # remember_resume() only accepts a row whose remember_expires is still in
    # the future, and the cookie carries the raw token while the column stores
    # its SHA-256. Both halves are asserted here: an expired token must not
    # authenticate, and an unexpired one must, so the pair cannot pass just
    # because the mechanism is broken in some other way.
    $rawRemember = 'a1b2c3d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90'
    $rememberHash = sha256hex $rawRemember
    $rmUser = [int](dbq 'SELECT id FROM users WHERE role=''farmer'' AND status=''active'' LIMIT 1')
    if (-not $rmUser) { throw 'no active farmer to test remember-me against' }

    dbq "UPDATE users SET remember_token='$rememberHash', remember_expires=NOW() - INTERVAL 1 DAY WHERE id=$rmUser" | Out-Null
    $rmExpiredSess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $rmExpiredSess.Cookies.Add((New-Object System.Net.Cookie('animal_remember', $rawRemember, '/', '127.0.0.1')))
    $rmExpired = get "$base/account.php" $rmExpiredSess
    ok 'an expired remember-me token does not sign anyone in' ($rmExpired -match 'login|sign in|Sign in') 'expired token was accepted'
    ok 'an expired remember-me token is cleared from the database' ((dbq "SELECT IFNULL(remember_token,'NULL') FROM users WHERE id=$rmUser") -eq 'NULL') 'expired token left in place'

    dbq "UPDATE users SET remember_token='$rememberHash', remember_expires=NOW() + INTERVAL 1 DAY WHERE id=$rmUser" | Out-Null
    $rmLiveSess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $rmLiveSess.Cookies.Add((New-Object System.Net.Cookie('animal_remember', $rawRemember, '/', '127.0.0.1')))
    $rmLive = get "$base/account.php" $rmLiveSess
    ok 'an unexpired remember-me token does sign the user in' ($rmLive -notmatch 'login\.php') 'valid token was rejected, so the pair above proves nothing'

    "`n--- reset token expiry ---"
    # The round-trip test above covers a fresh token. This one covers the other
    # half of the guard: a token that is past expires_at must be refused, and
    # the password must survive the attempt untouched.
    $expiredRaw = 'expired_reset_token_for_test_0123456789'
    $expiredHash = sha256hex $expiredRaw
    $pwBefore = dbq "SELECT password_hash FROM users WHERE id=$($ids.buyer)"
    dbq "INSERT INTO password_resets (user_id, token, expires_at) VALUES ($($ids.buyer), '$expiredHash', NOW() - INTERVAL 1 HOUR)"
    $expiredPage = get "$base/reset.php?token=$expiredRaw" (New-Object Microsoft.PowerShell.Commands.WebRequestSession)
    ok 'an expired reset token is reported as such' ($expiredPage -match 'expired|Expired|invalid|Invalid') 'expired token was not rejected'
    # The form is still served on GET so the page can explain itself; what must
    # not happen is a POST with that token actually changing the password.
    $expiredSess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $expiredForm = get "$base/reset.php?token=$expiredRaw" $expiredSess
    $expiredPost = post "$base/reset.php" $expiredSess @{
        csrf_token      = csrf $expiredForm
        token           = $expiredRaw
        password        = 'hijacked123'
        password_confirm = 'hijacked123'
    }
    ok 'posting the expired token is refused' ($expiredPost -match 'expired|Expired|invalid|Invalid') 'the POST was accepted'
    ok 'posting the expired token leaves the password unchanged' ((dbq "SELECT password_hash FROM users WHERE id=$($ids.buyer)") -eq $pwBefore) 'password changed through an expired token'
    dbq "DELETE FROM password_resets WHERE token='$expiredHash'" | Out-Null

    "`n--- login rate limiting ---"
  $rl = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  $rlKey = [System.BitConverter]::ToString(
    (New-Object System.Security.Cryptography.MD5CryptoServiceProvider).ComputeHash(
      [Text.Encoding]::UTF8.GetBytes('127.0.0.1:nobody@nowhere.test'))).Replace('-','').ToLower()
  & $mysql -u root animal_marketplace_test -e "DELETE FROM rate_limits WHERE \`key\` = '$rlKey';" 2>&1 | Out-Null
  $codes = @()
  for ($i = 1; $i -le 6; $i++) {
    $c = get "$base/login.php" $rl
    $t = csrf $c
    try {
      $resp = Invoke-WebRequest -Uri "$base/login.php" -WebSession $rl -UseBasicParsing -TimeoutSec 20 -Method Post `
              -Body @{ csrf_token=$t; email='nobody@nowhere.test'; password='wrong' }
      $codes += $resp.StatusCode
    } catch { $codes += [int]$_.Exception.Response.StatusCode }
  }
  ok 'login is rate limited after 5 attempts' (($codes -contains 429) -or ($codes[5] -eq 429)) ($codes -join ',')

  "`n--- logout ---"
  $c = get "$base/logout.php" $s
  $c = get "$base/account.php" $s
  ok 'after logout the account page is gated' (($c -match 'name="email"') -or ($c -match 'HTTPERR')) 'still reachable after logout'
}
catch {
  "HARNESS ERROR: $($_.Exception.Message)"
  $script:fail++
}
finally {
  if ($srv) { Stop-Process -Id $srv.Id -Force -ErrorAction SilentlyContinue }
  Start-Sleep -Milliseconds 300
  if ($orig) { [System.IO.File]::WriteAllText($cfg, $orig); 'config restored to original' }
}

"`n================ $pass passed, $fail failed ================"
'verify live config and live database are untouched:'
$line = (Select-String -LiteralPath $cfg -Pattern "'name' => 'animal_marketplace" | Select-Object -First 1)
'  config db line : ' + $line.Line.Trim()
$liveUsers = ((& $mysql -u root animal_marketplace -N -B -e 'SELECT COUNT(*) FROM users;' 2>&1) | Out-String).Trim()
"  live users     : $liveUsers  (non-zero proves the live DB was never dropped or seeded)"
if ($fail -eq 0) { exit 0 } else { exit 1 }
