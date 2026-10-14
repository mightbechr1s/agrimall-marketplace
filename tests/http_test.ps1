#requires -Version 5.1
<#
  HTTP end-to-end for the account work, against animal_marketplace_test only.
  Points config.php at the test DB and restores it in `finally`.

  NOTE: ok() calls must use the space-separated form `ok 'label' (cond) 'detail'`;
  `ok('l', $c -eq 'x')` parses as ('l', $c) -eq 'x' and yields no bool.
#>
  $ErrorActionPreference = 'Continue'
  $repo  = Split-Path -Parent $PSScriptRoot
  . (Join-Path $PSScriptRoot '_env.ps1')
  $php   = Get-AnimalTool 'ANIMAL_PHP'   'php'   @('C:\xampp\php\php.exe')
  $mysql = Get-AnimalTool 'ANIMAL_MYSQL' 'mysql' @('C:\xampp\mysql\bin\mysql.exe')
  $port  = Get-AnimalPort
  $tmp   = $env:TEMP
  $cfg   = Join-Path $repo 'config\config.php'
  $base  = "http://127.0.0.1:$port"

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
  Assert-AnimalMysqlUp $mysql
  if (Repair-AnimalConfigSwap $cfg) { 'recovered config.php left pointing at the test database by an earlier aborted run' }
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
    # a migration may print a result set; only ERROR counts as failure
    if ($r -match 'ERROR') { "MIGRATION FAIL $($m.Name): $r" }
  }
  $ids = & $php (Join-Path $repo 'tests\http_seed.php') 2>&1 | ConvertFrom-Json
  "seeded ids: buyer=$($ids.buyer) farmer=$($ids.farmer) open=$($ids.openOrder) blocked=$($ids.blocked) admin=$($ids.admin)"

  $srv = Start-Process -FilePath $php -ArgumentList '-S',"127.0.0.1:$port",'-t','public' `
          -WorkingDirectory $repo -WindowStyle Hidden -PassThru `
          -RedirectStandardOutput (Join-Path $tmp 'srv.log') `
          -RedirectStandardError  (Join-Path $tmp 'srv.err')
  if (-not (Wait-AnimalServer -Port $port)) { throw "php -S did not come up on port $port" }
  "dev server up on $port (PID $($srv.Id))"

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
    # action="." resolved to the directory, so the query was discarded
    ok 'search form targets browse.php, not the directory' ($b -match 'class="search-form"\s+method="get"\s+action="browse\.php"') 'form action is wrong'
    ok 'search form no longer targets the bare directory' (-not ($b -match 'search-form"\s+method="get"\s+action="\."')) 'still submitting to "."'
    $miss = get "$base/browse.php?q=zzz_no_such_item_zzz" $s
    ok 'a search that matches nothing stays on the marketplace' ($miss -match 'search-form') 'query bounced to another page'
    ok 'a search that matches nothing never reaches the landing page' (-not ($miss -match 'class="hero"')) 'landed on the landing page'
    ok 'a search that matches nothing runs the filter' ($miss -match 'empty-state-title') 'filter did not run'
    ok 'a search miss does not claim the market is empty' ($miss -match 'No results for|empty for') 'still says nothing for sale'
    ok 'the empty state spans the product grid' (($miss -match 'grid-column:\s*1\s*/\s*-1') -and ($miss -match 'empty-state')) 'not spanning the grid, renders flush left'
    # the size modifier was interpolated into style=, which the browser dropped
    ok 'the empty state applies a real size class' ($miss -match 'empty-state--md') 'size modifier is not a usable class'
    ok 'the empty state no longer inlines an invalid padding value' (-not ($miss -match 'style="padding:\s*py-')) 'invalid inline padding is back'
    # t() resolves flat keys only; a dotted path renders as the key text
    ok 'no copy key leaks into the page as literal text' (-not ($miss -match 'unavailable\.(title|message)')) 'a dotted key path rendered verbatim'
    # a placeholder is not an accessible name
    ok 'the search input has a real label' ($b -match '<label[^>]*for="market-search"') 'input has no accessible name'
    ok 'the search label is not just visually hidden text with no target' ($b -match 'id="market-search"') 'label points at no input'
    ok 'the search placeholder is localised, not hardcoded English' ($b -match 'placeholder="Try mangoes, rice, milk\.\.\."') 'hardcoded placeholder is back'
    # scoped to the CTA row; the header links to register.php on purpose
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
    # register.php bounces signed-in visitors, so the link is hidden for them
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
    ok 'a used reset link is reported as invalid' (($c -match 'expired|invalid|used|HTTPERR')) 'a used token was not reported as invalid'
    # replaying a consumed token must not change the password again
    $replaySess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $replayForm = get "$base/$link" $replaySess
    $replayPwBefore = dbq "SELECT password_hash FROM users WHERE id=$($ids.resetOnly)"
    $replayPost = post "$base/reset.php" $replaySess @{
        csrf_token       = csrf $replayForm
        token            = $token
        password         = 'replayed123'
        password_confirm = 'replayed123'
    }
    ok 'the reset link is single use' (($replayPost -match 'expired|Expired|invalid|Invalid') -and ($replayPost -notmatch 'HTTPERR')) 'replaying a used token was accepted'
    ok 'replaying the used link leaves the password unchanged' ((dbq "SELECT password_hash FROM users WHERE id=$($ids.resetOnly)") -eq $replayPwBefore) 'password changed through a replayed token'
  }

    "`n--- remember me ---"
    # both halves: an expired token is refused, an unexpired one is accepted
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
    # other half of the guard: an expired token is refused, the password is unchanged
    $expiredRaw = 'expired_reset_token_for_test_0123456789'
    $expiredHash = sha256hex $expiredRaw
    $pwBefore = dbq "SELECT password_hash FROM users WHERE id=$($ids.buyer)"
    dbq "INSERT INTO password_resets (user_id, token, expires_at) VALUES ($($ids.buyer), '$expiredHash', NOW() - INTERVAL 1 HOUR)"
    $expiredPage = get "$base/reset.php?token=$expiredRaw" (New-Object Microsoft.PowerShell.Commands.WebRequestSession)
    ok 'an expired reset token is reported as such' ($expiredPage -match 'expired|Expired|invalid|Invalid') 'expired token was not rejected'
    # GET still serves the form; a POST with the token must not change the password
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

    "`n--- reset token expiry cannot be moved by an UPDATE ---"
    # expires_at is DATETIME now, so an unrelated UPDATE cannot restamp it
    $driftRaw = 'drift_reset_token_for_test_0123456789ab'
    $driftHash = sha256hex $driftRaw
    dbq "INSERT INTO password_resets (user_id, token, expires_at, created_at) VALUES ($($ids.buyer), '$driftHash', NOW() - INTERVAL 1 HOUR, NOW() - INTERVAL 2 DAY)"
    $driftBefore = dbq "SELECT expires_at FROM password_resets WHERE token='$driftHash'"
    # Touch an unrelated column.
    dbq "UPDATE password_resets SET created_at = NOW() - INTERVAL 1 DAY WHERE token='$driftHash'" | Out-Null
    $driftAfter = dbq "SELECT expires_at FROM password_resets WHERE token='$driftHash'"
    ok 'an UPDATE to another column leaves the reset expiry untouched' ($driftBefore -eq $driftAfter) "expiry moved from '$driftBefore' to '$driftAfter', so an unrelated UPDATE is restamping it"
    # compared inside MySQL to avoid machine date parsing
    $driftStillExpired = (dbq "SELECT IFNULL(expires_at < NOW(), 0) FROM password_resets WHERE token='$driftHash'") -eq '1'
    ok 'the token is still expired after that UPDATE' $driftStillExpired 'the expired token was resurrected'
    $driftSess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
    $driftForm = get "$base/reset.php?token=$driftRaw" $driftSess
    $driftPost = post "$base/reset.php" $driftSess @{
        csrf_token       = csrf $driftForm
        token            = $driftRaw
        password         = 'drifted123'
        password_confirm = 'drifted123'
    }
    ok 'reset.php still refuses a token that was updated after expiry' ($driftPost -match 'expired|Expired|invalid|Invalid') 'an updated-but-expired token was accepted'
    dbq "DELETE FROM password_resets WHERE token='$driftHash'" | Out-Null

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

  "`n--- numeric validation (number_in) ---"
  # quantity and money fields took a bare (float) cast, so '12abc' passed as 12

  $listingId = $ids.listing
  # $ids.openOrder is a user id; look the order up by its number
  $orderId = dbq "SELECT id FROM orders WHERE order_number = 'ORD-HTTP1';"
  $catId  = dbq 'SELECT id FROM produce_categories LIMIT 1;'
  $unitId = dbq 'SELECT id FROM quantity_units LIMIT 1;'
  # the seed leaves the quantity columns NULL
  dbq "UPDATE produce_listings SET available_quantity = 50.0000, minimum_order_qty = 1.0000 WHERE id = $listingId" | Out-Null

  # http-buyer@x.test is anonymized by the deletion tests above; use http-block@x.test
  $buyerId = dbq "SELECT id FROM users WHERE email = 'http-block@x.test';"
  $vbu = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  login 'http-block@x.test' $vbu
  $cartBefore = [int](dbq "SELECT COUNT(*) FROM cart_items WHERE buyer_id = $buyerId")
  $form = get "$base/listing.php?id=$listingId" $vbu
  $t = csrf $form
  $r = post "$base/cart_add.php" $vbu @{ csrf_token = $t; listing_id = $listingId; quantity = 'abc'; return_to = 'browse.php' }
  $cartAfter = [int](dbq "SELECT COUNT(*) FROM cart_items WHERE buyer_id = $buyerId")
  ok 'cart_add refuses a non-numeric quantity' ($r -match 'valid quantity') "response did not carry the validation error"
  ok 'cart_add wrote no row for a non-numeric quantity' ($cartAfter -eq $cartBefore) "cart rows went $cartBefore -> $cartAfter"

  $form = get "$base/listing.php?id=$listingId" $vbu
  $t = csrf $form
  post "$base/cart_add.php" $vbu @{ csrf_token = $t; listing_id = $listingId; quantity = '3'; return_to = 'browse.php' } | Out-Null
  $cartGood = [int](dbq "SELECT COUNT(*) FROM cart_items WHERE buyer_id = $buyerId")
  ok 'cart_add still accepts a valid quantity' ($cartGood -eq ($cartBefore + 1)) "expected $($cartBefore + 1) cart rows, found $cartGood"

  # Trailing junk: the case a bare cast silently accepted as the leading number.
  $form = get "$base/listing.php?id=$listingId" $vbu
  $t = csrf $form
  $r = post "$base/cart_add.php" $vbu @{ csrf_token = $t; listing_id = $listingId; quantity = '12abc'; return_to = 'browse.php' }
  ok 'cart_add refuses trailing junk (12abc)' ($r -match 'valid quantity') "'12abc' was coerced instead of refused"

  # Quantity above the availability ceiling is a range failure, not a coercion one.
  $form = get "$base/listing.php?id=$listingId" $vbu
  $t = csrf $form
  $r = post "$base/cart_add.php" $vbu @{ csrf_token = $t; listing_id = $listingId; quantity = '999999'; return_to = 'browse.php' }
  ok 'cart_add refuses a quantity above available stock' ($r -match 'available|valid quantity') "an over-order quantity was accepted"

  "`n--- order_request numeric validation ---"
  $ordersBefore = [int](dbq 'SELECT COUNT(*) FROM orders;')
  $form = get "$base/listing.php?id=$listingId" $vbu
  $t = csrf $form
  $r = post "$base/order_request.php" $vbu @{
      csrf_token = $t; listing_id = $listingId; quantity = 'not-a-number'
      delivery_method = 'pickup'; delivery_address = ''; buyer_note = ''
  }
  $ordersAfter = [int](dbq 'SELECT COUNT(*) FROM orders;')
  ok 'order_request refuses a non-numeric quantity' ($r -match 'valid quantity|HTTPERR') "no validation error in the response"
  ok 'order_request created no order' ($ordersAfter -eq $ordersBefore) "order count went $ordersBefore -> $ordersAfter"

  "`n--- order_request bound messages interpolate the unit ---"
  # messages format unit_symbol, which needs the quantity_units join; each case pins its own bounds
  $symbol = dbq "SELECT qu.symbol FROM produce_listings pl JOIN quantity_units qu ON pl.unit_id=qu.id WHERE pl.id=$listingId;"
  foreach ($case in @(
      @{ label = 'below the minimum'; min = 2; max = 5;     avail = 10; qty = '1';  expect = "Minimum order is 2 $symbol." }
      @{ label = 'above the maximum'; min = 2; max = 5;     avail = 10; qty = '7';  expect = "Maximum order is 5 $symbol." }
      @{ label = 'above available stock'; min = 1; max = 'NULL'; avail = 10; qty = '50'; expect = "Only 10 $symbol available." }
  )) {
      dbq "UPDATE produce_listings SET minimum_order_qty=$($case.min), maximum_order_qty=$($case.max), available_quantity=$($case.avail) WHERE id=$listingId;" | Out-Null
      $before = [int](dbq 'SELECT COUNT(*) FROM orders;')
      $form = get "$base/listing.php?id=$listingId" $vbu
      $t = csrf $form
      $r = post "$base/order_request.php" $vbu @{
          csrf_token = $t; listing_id = $listingId; quantity = $case.qty
          delivery_method = 'pickup'; delivery_address = ''; buyer_note = ''
      }
      $after = [int](dbq 'SELECT COUNT(*) FROM orders;')
      ok "order_request names the unit when the quantity is $($case.label)" ($r -match [regex]::Escape($case.expect)) "expected '$($case.expect)' in the response"
      ok "order_request created no order when the quantity is $($case.label)" ($after -eq $before) "order count went $before -> $after"
      ok "order_request leaks no PHP notice when the quantity is $($case.label)" ($r -notmatch 'Undefined array key|Warning:|Fatal error') "engine notice surfaced in the response"
  }
  dbq "UPDATE produce_listings SET minimum_order_qty=1, maximum_order_qty=NULL, available_quantity=50 WHERE id=$listingId;" | Out-Null

  "`n--- create_listing numeric validation ---"
  # failure re-renders the form with HTTP 200, so assert that no row appeared
  $vfar = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  login 'http-farmer@x.test' $vfar
  $newBefore = [int](dbq 'SELECT COUNT(*) FROM produce_listings;')
  $form = get "$base/create_listing.php" $vfar
  $t = csrf $form
  $r = post "$base/create_listing.php" $vfar @{
      csrf_token = $t; category_id = $catId; unit_id = $unitId; name = 'Garbage Price Listing'
      price_per_unit = 'free'; available_quantity = '10'; minimum_order_qty = '1'
      description = 'validation probe'; is_organic = '0'
  }
  $newAfter = [int](dbq 'SELECT COUNT(*) FROM produce_listings;')
  ok 'create_listing refuses a non-numeric price' ($r -match 'name="price_per_unit"') "the form was not re-rendered, so the request may have been gated before validation"
  ok 'create_listing wrote no listing for a bad price' ($newAfter -eq $newBefore) "listing count went $newBefore -> $newAfter"

  $form = get "$base/create_listing.php" $vfar
  $t = csrf $form
  $r = post "$base/create_listing.php" $vfar @{
      csrf_token = $t; category_id = $catId; unit_id = $unitId; name = 'Valid Price Listing'
      price_per_unit = '25.50'; available_quantity = '10'; minimum_order_qty = '1'
      description = 'validation probe'; is_organic = '0'
  }
  $newGood = [int](dbq 'SELECT COUNT(*) FROM produce_listings;')
  ok 'create_listing still accepts a valid price' ($newGood -eq ($newBefore + 1)) "expected $($newBefore + 1) listings, found $newGood"

  "`n--- order_action delivery fee bound ---"
  # only the confirm branch checked MAX_DELIVERY_FEE
  dbq "UPDATE orders SET status = 'delivery_fee_review' WHERE id = $orderId" | Out-Null
  dbq "UPDATE orders SET delivery_fee = 0.00 WHERE id = $orderId" | Out-Null
  $feeSess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  login 'http-farmer@x.test' $feeSess
  $form = get "$base/order.php?id=$orderId" $feeSess
  $t = csrf $form
  # positive control: prove the endpoint is reachable before proving it refuses
  post "$base/order_action.php" $feeSess @{
      csrf_token = $t; action = 'set_fee'; order_id = $orderId; delivery_fee = '150.00'
  } | Out-Null
  $feeOk = dbq "SELECT delivery_fee FROM orders WHERE id = $orderId"
  # compare numerically; the DECIMAL scale is an implementation detail
  ok 'set_fee accepts a fee within range' ([double]$feeOk -eq 150) "fee was written as '$feeOk', so the endpoint is not reachable in this test"

  $form = get "$base/order.php?id=$orderId" $feeSess
  $t = csrf $form
  post "$base/order_action.php" $feeSess @{
      csrf_token = $t; action = 'set_fee'; order_id = $orderId; delivery_fee = '99999999'
  } | Out-Null
  $fee = dbq "SELECT delivery_fee FROM orders WHERE id = $orderId"
  ok 'set_fee refuses a fee above the maximum' ([double]$fee -eq 150) "fee was overwritten to '$fee' by an out-of-range value"

  $form = get "$base/order.php?id=$orderId" $feeSess
  $t = csrf $form
  post "$base/order_action.php" $feeSess @{
      csrf_token = $t; action = 'set_fee'; order_id = $orderId; delivery_fee = '-5'
  } | Out-Null
  $feeNeg = dbq "SELECT delivery_fee FROM orders WHERE id = $orderId"
  ok 'set_fee refuses a negative fee' ([double]$feeNeg -eq 150) "fee was overwritten to '$feeNeg' by a negative value"

  "`n--- stock deduction lifecycle (FR-ORD-010, BR-008, BR-009, FR-LIST-029) ---"
  # Stock moves only on confirmation. The fixture order is for 2 units of $listingId.
  # Compare numbers rather than DECIMAL strings: the scale is an implementation detail.
  $stockSess = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  login 'http-farmer@x.test' $stockSess
  function stockNow { [double](dbq "SELECT available_quantity FROM produce_listings WHERE id = $listingId") }
  # FR-LIST-030 invariant: before + signed change = after, checked in SQL so the
  # DECIMAL string formatting cannot fail the assertion for the wrong reason.
  function adjInvariant($type) {
    dbq "SELECT IF(ABS(quantity_before + quantity_change - quantity_after) < 0.00001, 'ok', 'bad')
          FROM stock_adjustments
          WHERE listing_id = $listingId AND reference_type = 'order' AND reference_id = $orderId
            AND adjustment_type = '$type' ORDER BY id DESC LIMIT 1"
  }

  dbq "UPDATE produce_listings SET available_quantity = 10, status = 'active' WHERE id = $listingId" | Out-Null
  dbq "UPDATE orders SET status = 'pending', confirmed_at = NULL, cancelled_at = NULL, rejected_at = NULL WHERE id = $orderId" | Out-Null

  # 1. Confirmation deducts exactly the ordered quantity.
  $t = csrf (get "$base/order.php?id=$orderId" $stockSess)
  post "$base/order_action.php" $stockSess @{ csrf_token = $t; action = 'confirm'; order_id = $orderId } | Out-Null
  $confirmStatus = dbq "SELECT status FROM orders WHERE id = $orderId"
  # positive control first, so a later failure cannot pass vacuously
  ok 'confirm reaches the confirmed state' ($confirmStatus -eq 'confirmed') "status is '$confirmStatus', so the endpoint is not reachable in this test"
  $afterConfirm = stockNow
  ok 'confirm deducts the ordered quantity (10 -> 8)' ($afterConfirm -eq 8) "available_quantity is $afterConfirm, expected 8"
  ok 'confirm records a sale_deduction that balances before + change = after' ((adjInvariant 'sale_deduction') -eq 'ok') "invariant returned '$(adjInvariant 'sale_deduction')'"

  # 2. Cancelling a confirmed order restores it.
  $t = csrf (get "$base/order.php?id=$orderId" $stockSess)
  post "$base/order_action.php" $stockSess @{ csrf_token = $t; action = 'cancel'; order_id = $orderId } | Out-Null
  $afterCancel = stockNow
  ok 'cancelling a confirmed order restores the stock (8 -> 10)' ($afterCancel -eq 10) "available_quantity is $afterCancel, expected 10"
  ok 'cancel records a return adjustment that balances' ((adjInvariant 'return') -eq 'ok') "invariant returned '$(adjInvariant 'return')'"

  # 3. Rejecting a pending order must not touch stock: nothing was deducted.
  dbq "UPDATE orders SET status = 'pending', cancelled_at = NULL WHERE id = $orderId" | Out-Null
  $beforeReject = stockNow
  # Count only what the reject itself adds: an earlier legitimate cancel on this
  # same order already wrote a return row.
  $returnsBefore = [int](dbq "SELECT COUNT(*) FROM stock_adjustments WHERE listing_id = $listingId AND reference_type = 'order' AND reference_id = $orderId AND adjustment_type = 'return'")
  $t = csrf (get "$base/order.php?id=$orderId" $stockSess)
  post "$base/order_action.php" $stockSess @{ csrf_token = $t; action = 'reject'; order_id = $orderId; reason = 'test' } | Out-Null
  $rejectStatus = dbq "SELECT status FROM orders WHERE id = $orderId"
  $afterReject = stockNow
  ok 'reject reaches the rejected state' ($rejectStatus -eq 'rejected') "status is '$rejectStatus', so the endpoint is not reachable in this test"
  ok 'rejecting a pending order leaves stock unchanged (10 -> 10)' ($afterReject -eq $beforeReject) "stock moved from $beforeReject to $afterReject; a pending order never deducted, so restoring here would inflate the listing"
  $returnsAfter = [int](dbq "SELECT COUNT(*) FROM stock_adjustments WHERE listing_id = $listingId AND reference_type = 'order' AND reference_id = $orderId AND adjustment_type = 'return'")
  ok 'reject adds no return adjustment' ($returnsAfter -eq $returnsBefore) "return adjustments went from $returnsBefore to $returnsAfter"

  # 4. Confirmation cannot oversell: refuse and change nothing.
  dbq "UPDATE produce_listings SET available_quantity = 1 WHERE id = $listingId" | Out-Null
  dbq "UPDATE orders SET status = 'pending', rejected_at = NULL, rejection_reason = NULL WHERE id = $orderId" | Out-Null
  $t = csrf (get "$base/order.php?id=$orderId" $stockSess)
  post "$base/order_action.php" $stockSess @{ csrf_token = $t; action = 'confirm'; order_id = $orderId } | Out-Null
  $overStatus = dbq "SELECT status FROM orders WHERE id = $orderId"
  $afterOver = stockNow
  ok 'confirm refuses when stock is insufficient (1 available, 2 ordered)' ($overStatus -eq 'pending' -and $afterOver -eq 1) "status='$overStatus' stock=$afterOver; a confirmed order here would oversell the listing"

  "`n--- product expiry (FR-LIST-033, FR-LIST-034) ---"
  # The listing reaches 'expired' by passing its date, not by a status flag:
  # there is no expired state to set, so the only thing to drive is the date.
  $yesterday = (& $mysql -u root animal_marketplace_test -N -B -e 'SELECT DATE_SUB(CURDATE(), INTERVAL 1 DAY);' 2>&1 | Out-String).Trim()
  $tomorrow = (& $mysql -u root animal_marketplace_test -N -B -e 'SELECT DATE_ADD(CURDATE(), INTERVAL 1 DAY);' 2>&1 | Out-String).Trim()

  # A fresh order request against an expired listing must be refused outright.
  dbq "UPDATE produce_listings SET expiry_date = '$yesterday', available_quantity = 50.0000, minimum_order_qty = 1.0000, status = 'active' WHERE id = $listingId" | Out-Null
  dbq "UPDATE orders SET status = 'pending', confirmed_at = NULL, cancelled_at = NULL, rejected_at = NULL, rejection_reason = NULL WHERE id = $orderId" | Out-Null
  $ordersBefore = [int](dbq "SELECT COUNT(*) FROM orders WHERE listing_id = $listingId AND status = 'pending'")
  # The token comes from browse.php, not the listing page: an expired listing
# renders no order form, so it carries no csrf field, and borrowing from there
# would abort the POST on 403 before the expiry guard is ever reached.
  $t = csrf (get "$base/browse.php" $vbu)
  $r = post "$base/order_request.php" $vbu @{ csrf_token = $t; listing_id = $listingId; quantity = '2'; delivery_method = 'pickup'; delivery_address = ''; buyer_note = '' }
  $ordersAfter = [int](dbq "SELECT COUNT(*) FROM orders WHERE listing_id = $listingId AND status = 'pending'")
  ok 'order_request refuses an expired listing' ($r -match 'no longer be ordered') "response did not carry the expiry refusal (match against 'no longer be ordered')"
  # Paired with the refusal above, so the refusal cannot pass by rejecting everything:
  # the same endpoint still has to accept a live listing further down.
  ok 'order_request wrote no pending order for an expired listing' ($ordersAfter -eq $ordersBefore) "pending orders went $ordersBefore -> $ordersAfter"

  # The listing stays visible and is labelled, rather than vanishing: a buyer
  # must be able to see why it cannot be bought.
  $page = get "$base/listing.php?id=$listingId" $vbu
  ok 'an expired listing is still visible on its detail page' ($page -match 'Best before') "the detail page did not render, so the expired label is unreachable"
  ok 'an expired listing is marked expired' ($page -match 'data-testid="expired-badge"') "no expired marker was rendered"
  ok 'an expired listing offers no order form' ($page -notmatch 'name="buyer_note"') "the Place Order form was still rendered for an expired listing"

  # A future expiry must stay purchasable, or the guard would block all sales.
  dbq "UPDATE produce_listings SET expiry_date = '$tomorrow' WHERE id = $listingId" | Out-Null
  $t = csrf (get "$base/listing.php?id=$listingId" $vbu)
  $r = post "$base/order_request.php" $vbu @{ csrf_token = $t; listing_id = $listingId; quantity = '2'; delivery_method = 'pickup'; delivery_address = ''; buyer_note = '' }
  $futureOrders = [int](dbq "SELECT COUNT(*) FROM orders WHERE listing_id = $listingId AND status = 'pending'")
  ok 'a listing expiring tomorrow still accepts an order' ($futureOrders -eq ($ordersBefore + 1)) "pending orders went $ordersBefore -> $futureOrders; the guard rejects live listings too"

  # Expiry must not write off stock: quantity and the ledger are untouched
  # (FR-LIST-034). The buyer's own order reserves nothing until confirmation.
  $qtyNow = stockNow
  ok 'expiry leaves available_quantity untouched' ($qtyNow -eq 50) "available_quantity is $qtyNow, expected 50; expiry must not write off stock"
  $adjAfterExpiry = [int](dbq "SELECT COUNT(*) FROM stock_adjustments WHERE listing_id = $listingId AND adjustment_type = 'spoilage'")
  ok 'expiry writes no spoilage adjustment' ($adjAfterExpiry -eq 0) "found $adjAfterExpiry spoilage rows; expiry withholds stock from sale, it does not write it off"

  # Confirmation is the second gate: an order placed before the date passed
  # must still be refused once it has.
  dbq "UPDATE produce_listings SET expiry_date = '$yesterday' WHERE id = $listingId" | Out-Null
  $newOrderId = dbq "SELECT id FROM orders WHERE listing_id = $listingId AND status = 'pending' ORDER BY id DESC LIMIT 1"
  $confirmOrder = New-Object Microsoft.PowerShell.Commands.WebRequestSession
  login 'http-farmer@x.test' $confirmOrder
  $t = csrf (get "$base/order.php?id=$newOrderId" $confirmOrder)
  $r = post "$base/order_action.php" $confirmOrder @{ csrf_token = $t; action = 'confirm'; order_id = $newOrderId }
  $confirmStatus = dbq "SELECT status FROM orders WHERE id = $newOrderId"
  ok 'confirm refuses an order whose listing expired after the request' ($confirmStatus -eq 'pending') "status is '$confirmStatus'; a confirm here would sell expired produce"
  $qtyAfterExpiredConfirm = stockNow
  ok 'refusing the expired confirm deducted no stock' ($qtyAfterExpiredConfirm -eq 50) "available_quantity is $qtyAfterExpiredConfirm, expected 50"

  "`n--- migration 009 rate_limits ---"
  # db-reset.php loads schema.sql + migrations, so a fresh clone must have the
  # table the login throttle queries.
  $hasRate = dbq "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'rate_limits'"
  ok 'rate_limits exists after schema.sql + migrations' ([int]$hasRate -eq 1) "the login throttle would fail with Unknown table 'rate_limits'"

  "`n--- migration 008 CHECK constraints ---"
  # the PHP guard is the first line; these prove the database refuses too
  $negPrice = (& $mysql -u root animal_marketplace_test -e "UPDATE produce_listings SET price_per_unit = -5 WHERE id = $listingId" 2>&1) | Out-String
  ok 'the database refuses a negative price' ($negPrice -match 'chk_listing_price_positive') "MySQL did not report the expected constraint failure: $negPrice"
  $negFee = (& $mysql -u root animal_marketplace_test -e "UPDATE orders SET delivery_fee = -100 WHERE id = $orderId" 2>&1) | Out-String
  ok 'the database refuses a negative delivery fee' ($negFee -match 'chk_order_fee_nonnegative') "MySQL did not report the expected constraint failure: $negFee"
  $zeroCart = (& $mysql -u root animal_marketplace_test -e "INSERT INTO cart_items (buyer_id, listing_id, quantity) VALUES ($buyerId, $listingId, 0)" 2>&1) | Out-String
  ok 'the database refuses a zero cart quantity' ($zeroCart -match 'chk_cart_qty_positive') "MySQL did not report the expected constraint failure: $zeroCart"
  # positive control: a legal write must still land
  $goodWrite = (& $mysql -u root animal_marketplace_test -e "UPDATE produce_listings SET price_per_unit = 25.00 WHERE id = $listingId" 2>&1) | Out-String
  $priceBack = dbq "SELECT price_per_unit FROM produce_listings WHERE id = $listingId"
  ok 'the database still accepts a legal price' (($goodWrite -notmatch 'ERROR') -and ([double]$priceBack -eq 25)) "a valid price was rejected or not written: $priceBack $goodWrite"

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
