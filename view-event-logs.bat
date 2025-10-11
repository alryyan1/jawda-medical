@echo off
REM View Windows Event Logs for Jawda Medical Notifications Service

title Jawda Medical - Event Log Viewer
echo Opening Windows Event Viewer for Jawda Medical Notifications Service...
echo.

REM Open Event Viewer and filter for our service
echo Opening Event Viewer...
echo.
echo To view our service logs:
echo 1. In Event Viewer, expand "Windows Logs"
echo 2. Click on "Application"
echo 3. In the Actions panel (right side), click "Filter Current Log..."
echo 4. In the "Event sources" field, type: Jawda Medical Notifications Service
echo 5. Click OK
echo.

REM Try to open Event Viewer directly to our service logs
echo Attempting to open Event Viewer with service filter...
eventvwr.msc

echo.
echo Alternative: You can also use PowerShell to view logs:
echo Get-WinEvent -LogName Application -FilterHashtable @{ProviderName="Jawda Medical Notifications Service"} | Format-Table TimeCreated, Id, LevelDisplayName, Message
echo.

pause
