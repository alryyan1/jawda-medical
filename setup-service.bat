@echo off
REM Setup script for Jawda Medical Notifications Queue Windows Service
REM This script installs Node.js dependencies and sets up the service

title Jawda Medical - Service Setup
echo Setting up Jawda Medical Notifications Queue Windows Service...
echo.

REM Check if Node.js is installed
where node >nul 2>nul
if %errorlevel% neq 0 (
    echo ERROR: Node.js is not installed or not in PATH.
    echo Please install Node.js from https://nodejs.org/
    echo.
    pause
    exit /b 1
)

REM Check Node.js version
echo Checking Node.js version...
node --version
echo.

REM Check if npm is available
where npm >nul 2>nul
if %errorlevel% neq 0 (
    echo ERROR: npm is not available.
    echo Please ensure Node.js is properly installed.
    echo.
    pause
    exit /b 1
)

REM Install dependencies
echo Installing Node.js dependencies...
npm install

if %errorlevel% neq 0 (
    echo ERROR: Failed to install dependencies.
    echo Please check your internet connection and try again.
    echo.
    pause
    exit /b 1
)

echo.
echo Dependencies installed successfully!
echo.

REM Ask user if they want to install the service
set /p install_service="Do you want to install the Windows service now? (y/n): "
if /i "%install_service%"=="y" (
    echo.
    echo Installing Windows service...
    echo This requires administrator privileges.
    echo.
    node install-service.js
) else (
    echo.
    echo Service setup complete!
    echo.
    echo To install the service later, run:
    echo   node install-service.js
    echo.
    echo To uninstall the service, run:
    echo   node uninstall-service.js
)

echo.
pause
