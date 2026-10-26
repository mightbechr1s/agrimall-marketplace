@echo off
REM AniMALL one-click start
cd /d "C:\xampp\mysql"
start "" "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\my.ini" --standalone
timeout /t 6 /nobreak >nul
cd /d "C:\Users\your4\Projects\animal-marketplace"
start "" "C:\xampp\php\php.exe" -S 127.0.0.1:8000 -t public public/router.php
timeout /t 2 /nobreak >nul
start "" "http://127.0.0.1:8000/index.php"
echo AniMALL started. Close this window to keep running in background.
pause