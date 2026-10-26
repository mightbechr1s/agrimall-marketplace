#requires -Version 5.1
<#
  Shared test bootstrap. Dot-source before a suite touches the database:
    . (Join-Path $PSScriptRoot '_env.ps1')
    $php   = Get-AnimalTool 'ANIMAL_PHP'   'php'   @('C:\xampp\php\php.exe')
    $mysql = Get-AnimalTool 'ANIMAL_MYSQL' 'mysql' @('C:\xampp\mysql\bin\mysql.exe')
    $port  = Get-AnimalPort
    $base  = "http://127.0.0.1:$port"

  Per-machine overrides (suites don't hardcode vendor layout):
    $env:ANIMAL_PHP    full path to php.exe
    $env:ANIMAL_MYSQL  full path to mysql.exe
    $env:ANIMAL_PORT   pin the port instead of auto-picking a free one

  Wrong ANIMAL_* value is reported plainly instead of failing later as migration error.
#>

function Get-AnimalTool {
  param(
    [Parameter(Mandatory)][string]$EnvVar,
    [Parameter(Mandatory)][string]$Label,
    [Parameter(Mandatory)][string[]]$Fallback
  )

  $override = [Environment]::GetEnvironmentVariable($EnvVar)
  if ($override) {
    if (-not (Test-Path -LiteralPath $override)) {
      throw "$EnvVar is set to '$override', which does not exist. Correct it or remove it to use the default."
    }
    return $override
  }
  foreach ($candidate in $Fallback) {
    if (Test-Path -LiteralPath $candidate) { return $candidate }
  }
  $onPath = Get-Command $Label -ErrorAction SilentlyContinue
  if ($onPath) { return $onPath.Source }

  throw ("Could not find {0}. Set {1} to the full path, e.g. $EnvVar='C:\xampp\{0}\{0}.exe'" -f $Label, $EnvVar)
}

function Get-AnimalPort {
  # Port 0 asks OS for free port so suite never collides with dev server on 8000.
  if ($env:ANIMAL_PORT) { return [int]$env:ANIMAL_PORT }

  $listener = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
  try {
    $listener.Start()
    return $listener.LocalEndpoint.Port
  }
  finally { $listener.Stop() }
}

function Assert-AnimalMysqlUp {
  param([Parameter(Mandatory)][string]$MySql)

  $reply = (& $MySql -u root -N -B -e 'SELECT 1;' 2>&1) | Out-String
  if ($LASTEXITCODE -ne 0 -or $reply -notmatch '\b1\b') {
    throw ("Cannot reach MySQL as root via '{0}'. Start your database server first (XAMPP: Start MySQL), then retry. Server said: {1}" -f $MySql, $reply.Trim())
  }
}

function Wait-AnimalServer {
  param([Parameter(Mandatory)][int]$Port, [int]$Attempts = 40)

  for ($i = 0; $i -lt $Attempts; $i++) {
    Start-Sleep -Milliseconds 250
    if (Get-NetTCPConnection -LocalPort $Port -State Listen -ErrorAction SilentlyContinue) { return $true }
  }
  return $false
}

function Repair-AnimalConfigSwap {
  <#
    Suites point config.php at throwaway test DB and restore after. If run dies
    before restore (terminal killed, DB server dies), dev app serves test DB and
    git status shows nothing (config.php is gitignored). Call before swapping;
    only rewrites DB name, safe when config.php is already correct.
  #>
  param(
    [Parameter(Mandatory)][string]$Cfg,
    [string]$LiveDb = 'animal_marketplace'
  )

  if (-not (Test-Path -LiteralPath $Cfg)) { return $false }

  $text = [System.IO.File]::ReadAllText($Cfg)
  # Anchor on quoted DB name so app's later 'name' => 'AniMALL' is safe.
  $stale = "('name'\s*=>\s*')animal_marketplace_test(')"
  if ($text -notmatch $stale) { return $false }

  $fixed = $text -replace $stale, "`$1$LiveDb`$2"
  [System.IO.File]::WriteAllText($Cfg, $fixed)
  return $true
}

function Disable-AnimalSecureCookies {
  <#
    Suites serve plain http on 127.0.0.1. A Secure session cookie is not stored
    over http by PowerShell's cookie jar, so the session never existed and every
    authenticated request came back 403 -- 70 failures across two suites that had
    nothing to do with cookies. Turning it off here, and only here, keeps the
    shipped default (true) correct for every real deployment.

    An older local config may predate the 'secure_cookies' key entirely, so an
    absent key is inserted rather than assumed.
  #>
  param([Parameter(Mandatory)][string]$Cfg)

  if (-not (Test-Path -LiteralPath $Cfg)) { return $false }
  $text = [System.IO.File]::ReadAllText($Cfg)

  if ($text -match "('secure_cookies'\s*=>\s*)(true|false)") {
    $out = $text -replace "('secure_cookies'\s*=>\s*)(true|false)", "`$1false"
  } else {
    # Insert beside the sibling session setting so the intent stays local.
    $out = $text -replace "('session_lifetime_minutes'\s*=>\s*\d+,)", "`$1`r`n        'secure_cookies' => false, // harness only: plain http on 127.0.0.1"
  }
  if ($out -eq $text) { return $false }
  [System.IO.File]::WriteAllText($Cfg, $out)
  return $true
}


function Enable-AnimalLocalResetLinks {
  <#
    There is no mail transport in this build, so password reset only works at all
    when the link is displayed. It ships off, because a real deployment must not put
    a one-time token on screen, which leaves the whole reset round trip untestable --
    no link is issued, so nothing can follow it.

    local_reset_links_enabled() used to gate on 'env' instead, but 'env' also decides
    whether PHP diagnostics reach the browser. Two unrelated decisions, one switch:
    the same conflation that lost the session cookie. Displaying the link is now its
    own key, so 'env' is deliberately left alone here.

    Restored with the rest of config in the suite's `finally`.
  #>
  param([Parameter(Mandatory)][string]$Cfg)

  if (-not (Test-Path -LiteralPath $Cfg)) { return $false }
  $text = [System.IO.File]::ReadAllText($Cfg)

  if ($text -match "('local_password_reset_links'\s*=>\s*)(true|false)") {
    $out = $text -replace "('local_password_reset_links'\s*=>\s*)(true|false)", "`$1true"
  } else {
    # An older local config may predate the key entirely.
    $out = $text -replace "('env'\s*=>\s*'[a-z]+',\r?\n)", "`$1        'local_password_reset_links' => true, // harness only: no mail transport`r`n"
  }
  if ($out -eq $text) { return $false }
  [System.IO.File]::WriteAllText($Cfg, $out)
  return $true
}
