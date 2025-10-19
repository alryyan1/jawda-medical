@echo off
cd /d "C:\xampp\htdocs\jawda-medical"
php artisan queue:work --queue=notifications --sleep=1 --tries=3 --timeout=120 > storage\logs\queue-worker.log 2>&1