@echo off
REM Advanced HL7 Client Startup Script
REM This script allows custom configuration for the HL7 client

echo ========================================
echo    Advanced HL7 Client Startup Script
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

echo Configuration Options:
echo 1. Use default settings (192.168.1.114:5100)
echo 2. Enter custom host and port
echo 3. Show help
echo.
set /p choice="Enter your choice (1-3): "

if "%choice%"=="1" goto default
if "%choice%"=="2" goto custom
if "%choice%"=="3" goto help
echo Invalid choice. Using default settings.
goto default

:default
echo.
echo Using default settings: 192.168.1.114:5100
echo Starting HL7 Client...
echo.
echo Press Ctrl+C to stop the client
echo ========================================
echo.
php hl7-client.php
goto end

:custom
echo.
set /p host="Enter host IP (default: 192.168.1.114): "
if "%host%"=="" set host=192.168.1.114

set /p port="Enter port (default: 5100): "
if "%port%"=="" set port=5100

echo.
echo Using custom settings: %host%:%port%
echo Starting HL7 Client...
echo.
echo Press Ctrl+C to stop the client
echo ========================================
echo.
php hl7-client.php --host=%host% --port=%port%
goto end

:help
echo.
echo HL7 Client Help:
echo.
php hl7-client.php --help
echo.
pause
goto end

:end
echo.
echo ========================================
echo HL7 Client has stopped
echo ========================================
pause

