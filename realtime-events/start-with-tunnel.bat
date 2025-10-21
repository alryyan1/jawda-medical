@echo off
echo Starting Jawda Realtime Events Server with Pinggy Tunnel...
echo.
echo This will:
echo 1. Start the realtime-events server on port 4001
echo 2. Create a Pinggy tunnel to expose it publicly
echo 3. Display the public URLs for your frontend to use
echo.
echo Press Ctrl+C to stop both server and tunnel
echo.

node start-with-tunnel.js

pause

