@echo off
REM AniMALL one-click start
setlocal

REM Start MySQL if not already running
tasklist /FI "IMAGENAME eq mysqld.exe" 2>nul | find /I "mysqld.exe" >nul
if errorlevel 1 (
    cd /d "C:\xampp\mysql"
    start "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    timeout /t 6 /nobreak >nul
)

REM Start PHP dev server if port 8000 is free
cd /d "C:\Users\your4\Projects\animal-marketplace"
start "" "C:\xampp\php\php.exe" -S 127.0.0.1:8000 -t public public/router.php
timeout /t 2 /nobreak >nul

REM Verify the server actually started
powershell -NoProfile -Command "try { (Invoke-WebRequest -Uri 'http://127.0.0.1:8000/login.php' -UseBasicParsing -TimeoutSec 5) | Out-Null; exit 0 } catch { exit 1 }"
if errorlevel 1 (
    echo.
    echo ERROR: The PHP server did not start. Check that port 8000 is free and PHP is installed.
    echo.
    pause
    exit /b 1
)

start "" "http://127.0.0.1:8000/login.php"
echo AniMALL started. Close this window to keep running in background.
pause