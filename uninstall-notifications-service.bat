@echo off
REM Uninstall Windows Service for Jawda Medical Notifications Queue

echo Uninstalling Jawda Medical Notifications Queue Windows Service...
echo.

set "SERVICE_NAME=JawdaMedicalNotifications"

REM Check if service exists
sc query "%SERVICE_NAME%" >nul 2>nul
if %errorlevel% neq 0 (
    echo Service %SERVICE_NAME% does not exist.
    echo.
    pause
    exit /b 0
)

echo Found service: %SERVICE_NAME%
echo.

REM Stop the service first
echo Stopping service...
sc stop "%SERVICE_NAME%"

REM Wait a moment for the service to stop
echo Waiting for service to stop...
timeout /t 5 >nul

REM Check if service is still running
sc query "%SERVICE_NAME%" | find "STOPPED" >nul
if %errorlevel% neq 0 (
    echo WARNING: Service may still be running. Force stopping...
    sc stop "%SERVICE_NAME%" >nul 2>nul
    timeout /t 3 >nul
)

REM Remove the service
echo Removing service...
sc delete "%SERVICE_NAME%"

if %errorlevel% equ 0 (
    echo.
    echo SUCCESS: Service %SERVICE_NAME% has been removed successfully!
    echo.
    echo Note: Log files in the logs directory have been preserved.
    echo You can manually delete them if no longer needed.
) else (
    echo ERROR: Failed to remove service.
    echo You may need to run this script as Administrator.
)

echo.
pause
