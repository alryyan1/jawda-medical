@echo off
setlocal

REM Run TCPDF font conversion artisan command (accepts optional args like --font=ARIAL.TTF)
echo Running: php artisan tcpdf:convert-font %*

echo.
cd /d "C:\xampp\htdocs\jawda-medical"
php artisan tcpdf:convert-font %*

echo.
echo Done. Press any key to close.
pause >nul

endlocal
