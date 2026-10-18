@echo off
REM Starts the CRM on this PC: MySQL, web server, background worker, scheduler.
REM Double-click to run. Close the opened windows to stop.
cd /d "%~dp0"

echo Starting MySQL...
start "CRM - MySQL" /min cmd /c "C:\xampp_old\mysql_start.bat"
timeout /t 6 /nobreak >nul

echo Starting web server on http://localhost:8000 ...
start "CRM - Web server" /min C:\php82\php.exe artisan serve --host=0.0.0.0 --port=8000

echo Starting background worker (campaigns, website leads)...
start "CRM - Queue worker" /min C:\php82\php.exe artisan queue:work --tries=3 --sleep=3

echo Starting scheduler (daily reminders, morning summary)...
start "CRM - Scheduler" /min C:\php82\php.exe artisan schedule:work

timeout /t 3 /nobreak >nul
start "" http://localhost:8000/admin
echo.
echo CRM is running. Keep the minimised windows open while you work.
timeout /t 5 >nul
