@echo off
REM Starts the CRM on this PC: MySQL, web server, background worker, scheduler.
REM Double-click to run. To stop, double-click stop-crm.bat (never close the MySQL window:
REM killing MySQL mid-write is what corrupted the old C:\xampp_old install).
cd /d "%~dp0"

tasklist /FI "IMAGENAME eq mysqld.exe" | find /I "mysqld.exe" >nul
if errorlevel 1 (
    echo Starting MySQL...
    start "CRM - MySQL" /min cmd /c "C:\xampp_new\mysql_start.bat"
    timeout /t 6 /nobreak >nul
) else (
    echo MySQL is already running.
)

echo Starting web server on http://localhost:8000 ...
start "CRM - Web server" /min C:\php82\php.exe artisan serve --host=0.0.0.0 --port=8000

echo Starting background worker (campaigns, website leads)...
start "CRM - Queue worker" /min C:\php82\php.exe artisan queue:work --tries=3 --sleep=3

echo Starting scheduler (daily reminders, morning summary)...
start "CRM - Scheduler" /min C:\php82\php.exe artisan schedule:work

timeout /t 3 /nobreak >nul
start "" http://localhost:8000/admin
echo.
echo CRM is running. Keep the minimised windows open while you work; stop with stop-crm.bat.
timeout /t 5 >nul
