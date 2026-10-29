@echo off
REM Stops the CRM on this PC: takes a backup, closes the web server, worker and scheduler,
REM then shuts MySQL down cleanly. Always use this instead of closing the MySQL window.
cd /d "%~dp0"

echo Backing up crm_local...
call "%~dp0backup-crm.bat" backup

echo Closing web server, worker and scheduler...
taskkill /FI "WINDOWTITLE eq CRM - Web server*" /T /F >nul 2>&1
taskkill /FI "WINDOWTITLE eq CRM - Queue worker*" /T /F >nul 2>&1
taskkill /FI "WINDOWTITLE eq CRM - Scheduler*" /T /F >nul 2>&1

echo Stopping MySQL cleanly...
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\local\crm-local.ps1" -Action shutdown
taskkill /FI "WINDOWTITLE eq CRM - MySQL*" /T /F >nul 2>&1

echo.
echo CRM stopped.
timeout /t 5 >nul
