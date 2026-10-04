#requires -Version 5.1
<#
  Shared test bootstrap. Dot-source it before a suite touches the database:

    . (Join-Path $PSScriptRoot '_env.ps1')
    $php   = Get-AnimalTool 'ANIMAL_PHP'   'php'   @('C:\xampp\php\php.exe')
    $mysql = Get-AnimalTool 'ANIMAL_MYSQL' 'mysql' @('C:\xampp\mysql\bin\mysql.exe')
    $port  = Get-AnimalPort
    $base  = "http://127.0.0.1:$port"

  Per-machine overrides, so the suites do not hardcode one vendor's layout:
    $env:ANIMAL_PHP    full path to php.exe
    $env:ANIMAL_MYSQL  full path to mysql.exe
    $env:ANIMAL_PORT   pin the port instead of auto-picking a free one

  A wrong ANIMAL_* value is reported plainly instead of failing later as a
  confusing migration error.
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
  # Port 0 asks the OS for any free port, so a suite never collides with a dev
  # server the developer left running on 8000.
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
