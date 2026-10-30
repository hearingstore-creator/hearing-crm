@echo off
REM Backs up crm_local to E:\Backups\CRM\local (14 daily + 8 weekly copies). Safe while the CRM runs.
REM Runs daily at 21:00 from Windows Task Scheduler ("CRM Backup") and from stop-crm.bat.
REM   backup-crm.bat          make a backup
REM   backup-crm.bat verify   restore the newest backup into a scratch database and check it
cd /d "%~dp0"
set ACTION=%1
if "%ACTION%"=="" set ACTION=backup
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\local\crm-local.ps1" -Action %ACTION%
exit /b %errorlevel%
