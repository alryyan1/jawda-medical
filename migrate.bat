@echo off
cd /d "%~dp0"
php artisan migrate --path=database/setup/migrations
pause
