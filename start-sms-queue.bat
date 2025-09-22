@echo off
setlocal

REM Change to this script's directory (project root)
cd /d "%~dp0"

title Jawda SMS Queue Worker
echo Starting Laravel queue worker for queues: sms,default
echo Press Ctrl+C to stop.

REM Use XAMPP PHP if available, otherwise fall back to system PHP
set "PHP_PATH=C:\xampp\php\php.exe"
if exist "%PHP_PATH%" (
  "%PHP_PATH%" artisan queue:work --queue=sms,default --sleep=3 --tries=3 --backoff=30
) else (
  php artisan queue:work --queue=sms,default --sleep=3 --tries=3 --backoff=30
)

pause

