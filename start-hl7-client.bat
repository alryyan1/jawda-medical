@echo off
REM HL7 Client Startup Script
REM This script starts the HL7 client to connect to Mindray 30s CBC analyzer

echo ========================================
echo    HL7 Client Startup Script
echo ========================================
echo.

REM Check if PHP is available
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo ERROR: PHP is not installed or not in PATH
    echo Please install PHP and add it to your system PATH
    echo.
    pause
    exit /b 1
)

REM Check if the hl7-client.php file exists
if not exist "hl7-client.php" (
    echo ERROR: hl7-client.php file not found
    echo Please make sure you are running this script from the correct directory
    echo.
    pause
    exit /b 1
)

REM Check if vendor directory exists (composer dependencies)
if not exist "vendor" (
    echo ERROR: vendor directory not found
    echo Please run 'composer install' first to install dependencies
    echo.
    pause
    exit /b 1
)

echo Starting HL7 Client...
echo.
echo Default connection: 192.168.1.114:5100
echo To use custom host/port, edit this batch file or run manually:
echo php hl7-client.php --host=YOUR_IP --port=YOUR_PORT
echo.
echo Press Ctrl+C to stop the client
echo ========================================
echo.

REM Start the HL7 client
php hl7-client.php

REM If the script exits, show a message
echo.
echo ========================================
echo HL7 Client has stopped
echo ========================================
pause

