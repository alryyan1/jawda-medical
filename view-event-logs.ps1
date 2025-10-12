# PowerShell script to view Jawda Medical Notifications Service event logs

Write-Host "🔍 Jawda Medical Notifications Service - Event Log Viewer" -ForegroundColor Green
Write-Host "=====================================================" -ForegroundColor Green
Write-Host ""

# Get recent events from our service
Write-Host "📋 Recent Events (Last 50):" -ForegroundColor Yellow
Write-Host ""

try {
    $events = Get-WinEvent -LogName Application -FilterHashtable @{ProviderName="Jawda Medical Notifications Service"} -MaxEvents 50 -ErrorAction Stop
    
    if ($events.Count -eq 0) {
        Write-Host "No events found for 'Jawda Medical Notifications Service'" -ForegroundColor Red
        Write-Host "The service may not be installed or hasn't generated any events yet." -ForegroundColor Yellow
    } else {
        $events | ForEach-Object {
            $time = $_.TimeCreated.ToString("yyyy-MM-dd HH:mm:ss")
            $level = $_.LevelDisplayName
            $message = $_.Message
            
            # Color code by level
            switch ($level) {
                "Error" { $color = "Red" }
                "Warning" { $color = "Yellow" }
                "Information" { $color = "Green" }
                default { $color = "White" }
            }
            
            Write-Host "[$time] [$level]" -ForegroundColor $color -NoNewline
            Write-Host " $message" -ForegroundColor White
        }
    }
} catch {
    Write-Host "Error accessing event logs: $($_.Exception.Message)" -ForegroundColor Red
    Write-Host "You may need to run this script as Administrator." -ForegroundColor Yellow
}

Write-Host ""
Write-Host "📊 Event Summary:" -ForegroundColor Yellow

try {
    $errorCount = (Get-WinEvent -LogName Application -FilterHashtable @{ProviderName="Jawda Medical Notifications Service"; Level=2} -ErrorAction SilentlyContinue).Count
    $warningCount = (Get-WinEvent -LogName Application -FilterHashtable @{ProviderName="Jawda Medical Notifications Service"; Level=3} -ErrorAction SilentlyContinue).Count
    $infoCount = (Get-WinEvent -LogName Application -FilterHashtable @{ProviderName="Jawda Medical Notifications Service"; Level=4} -ErrorAction SilentlyContinue).Count
    
    Write-Host "  Errors: $errorCount" -ForegroundColor Red
    Write-Host "  Warnings: $warningCount" -ForegroundColor Yellow
    Write-Host "  Information: $infoCount" -ForegroundColor Green
} catch {
    Write-Host "  Could not retrieve event summary" -ForegroundColor Red
}

Write-Host ""
Write-Host "💡 Tips:" -ForegroundColor Cyan
Write-Host "  - Run this script as Administrator for full access" -ForegroundColor White
Write-Host "  - Use 'Get-WinEvent -LogName Application | Where-Object {$_.ProviderName -eq \"Jawda Medical Notifications Service\"}' for more details" -ForegroundColor White
Write-Host "  - Check the logs directory for detailed file logs" -ForegroundColor White

Write-Host ""
Write-Host "Press any key to exit..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
