@echo off
echo Starting Laravel Queue Worker...
echo Press Ctrl+C to stop the worker
echo.

cd /d "C:\xampp\htdocs\jawda-medical"
php artisan queue:work --verbose --tries=3 --timeout=300

pause
