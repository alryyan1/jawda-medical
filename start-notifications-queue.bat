@echo off
REM Change to this script's directory (project root)
cd /d %~dp0

title Jawda Medical - Notifications Queue Worker
echo Starting Laravel queue worker for "notifications" queue...
echo Press Ctrl+C to stop.

REM Use PHP from PATH; ensure php.exe is available (e.g., XAMPP)
php artisan queue:work --queue=notifications --sleep=1 --tries=3 --timeout=120

pause

