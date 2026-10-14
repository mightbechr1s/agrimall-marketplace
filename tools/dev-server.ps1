<#
.SYNOPSIS
    Starts (or stops) the local AniMAL stack: MariaDB and the PHP dev server.

.DESCRIPTION
    The PHP dev server must be launched WITH public\router.php:

        php -S host:port -t public public/router.php

    Launching with only "-t public" silently bypasses router.php and ignores
    public\uploads\.htaccess, re-opening remote code execution through uploaded
    files. Every step is idempotent, so it is safe to re-run.

.PARAMETER Port
    Port for the PHP dev server. Default 8000.

.PARAMETER Host
    Bind address for the PHP dev server. Default 127.0.0.1 (local only).

.PARAMETER Stop
    Stop both services instead of starting them.
#>

[CmdletBinding()]
param(
    [int]$Port = 8000,
    [string]$Host_ = '127.0.0.1',
    [switch]$Stop
)

$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$PublicDir   = Join-Path $ProjectRoot 'public'
$RouterFile  = Join-Path $PublicDir  'router.php'

$PhpExe      = 'C:\xampp\php\php.exe'
$MySqlDir    = 'C:\xampp\mysql'
$MySqlExe    = Join-Path $MySqlDir 'bin\mysqld.exe'
$MySqlAdmin  = Join-Path $MySqlDir 'bin\mysqladmin.exe'

function Test-TcpPort([int]$TcpPort, [string]$TcpHost = '127.0.0.1', [int]$TimeoutMs = 1000) {
    # Pure .NET probe: native clients like mysqladmin write to stderr when the
    # server is down, which $ErrorActionPreference='Stop' turns into terminating errors.
    $client = New-Object System.Net.Sockets.TcpClient
    try {
        $async = $client.BeginConnect($TcpHost, $TcpPort, $null, $null)
        if (-not $async.AsyncWaitHandle.WaitOne($TimeoutMs, $false)) { return $false }
        $client.EndConnect($async)
        return $true
    } catch {
        return $false
    } finally {
        $client.Close()
    }
}

function Test-MySqlAlive {
    if (-not (Test-Path $MySqlAdmin)) { return $false }
    return (Test-TcpPort 3306)
}

# --- Preflight -------------------------------------------------------------
$missing = @()
foreach ($p in @($PhpExe, $MySqlExe, $RouterFile)) {
    if (-not (Test-Path $p)) { $missing += $p }
}
if ($missing) {
    Write-Error ("Missing required path(s):`n  " + ($missing -join "`n  "))
    exit 1
}

# --- Stop ------------------------------------------------------------------
if ($Stop) {
    Get-Process php -ErrorAction SilentlyContinue |
        Stop-Process -Force -ErrorAction SilentlyContinue
    Write-Host 'Stopped PHP dev server.' -ForegroundColor Yellow

    & $MySqlAdmin -u root shutdown 2>&1 | Out-Null
    Start-Sleep -Seconds 2
    if (Test-MySqlAlive) {
        Write-Warning 'MariaDB did not shut down via mysqladmin; leaving it running.'
    } else {
        Write-Host 'Stopped MariaDB.' -ForegroundColor Yellow
    }
    return
}

# --- MariaDB ---------------------------------------------------------------
if (Test-MySqlAlive) {
    Write-Host 'MariaDB      : already running' -ForegroundColor DarkGray
} else {
    Write-Host 'MariaDB      : starting...' -ForegroundColor Cyan
    # No --datadir override on purpose: it must come from mysql\my.ini, so a broken config fails loudly.
    Start-Process -FilePath $MySqlExe -WorkingDirectory $MySqlDir -WindowStyle Hidden

    $ready = $false
    foreach ($_ in 1..30) {
        Start-Sleep -Seconds 1
        if (Test-MySqlAlive) { $ready = $true; break }
    }
    if (-not $ready) {
        Write-Error 'MariaDB did not become ready within 30s. Check C:\xampp\mysql\data\mysql_error.log'
        exit 1
    }
    Write-Host 'MariaDB      : up' -ForegroundColor Green
}

# --- PHP dev server --------------------------------------------------------
if (Test-TcpPort $Port $Host_) {
    Write-Host "PHP dev server: port $Port already in use, leaving it alone" -ForegroundColor DarkGray
} else {
    Write-Host "PHP dev server: starting on $Host_`:$Port (router enabled)" -ForegroundColor Cyan
    # router.php MUST be the final argument. Without it, uploads/.htaccess is
    # not honoured and uploaded .php files execute.
    Start-Process -FilePath $PhpExe `
        -ArgumentList '-S', "${Host_}:$Port", '-t', $PublicDir, $RouterFile `
        -WorkingDirectory $ProjectRoot -WindowStyle Hidden

    $ready = $false
    foreach ($_ in 1..20) {
        Start-Sleep -Milliseconds 500
        try {
            $r = Invoke-WebRequest -Uri "http://${Host_}:$Port/index.php" -UseBasicParsing -TimeoutSec 3
            if ($r.StatusCode -eq 200) { $ready = $true; break }
        } catch { }
    }
    if (-not $ready) {
        Write-Error "PHP dev server did not answer on http://${Host_}:$Port/index.php"
        exit 1
    }
    Write-Host "PHP dev server: up" -ForegroundColor Green
}

Write-Host ''
Write-Host "  App  : http://${Host_}:$Port/index.php" -ForegroundColor White
Write-Host "  Stop : powershell -ExecutionPolicy Bypass -File tools\dev-server.ps1 -Stop" -ForegroundColor DarkGray
