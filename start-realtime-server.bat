@echo off
setlocal

echo Starting Realtime Server...

echo.
cd /d "C:\xampp\htdocs\jawda-medical\realtime-events"
call npm run start

echo.
echo Realtime server process started. Press any key to close this window.
pause >nul

endlocal
