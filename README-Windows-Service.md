# Windows Service Setup for Jawda Medical Notifications Queue

This guide explains how to set up the notifications queue as a Windows service using NSSM (Non-Sucking Service Manager).

## Prerequisites

1. **NSSM (Non-Sucking Service Manager)**
   - Download from: https://nssm.cc/download
   - Extract the executable and add it to your system PATH
   - Or place `nssm.exe` in the project root directory

2. **Administrator Privileges**
   - You need administrator rights to install/remove Windows services
   - Right-click on Command Prompt and select "Run as administrator"

## Installation

1. **Download and Setup NSSM**
   ```bash
   # Download NSSM from https://nssm.cc/download
   # Extract nssm.exe and add to PATH or place in project root
   ```

2. **Install the Service**
   ```bash
   # Run as Administrator
   install-notifications-service.bat
   ```

3. **Verify Installation**
   ```bash
   # Check service status
   sc query JawdaMedicalNotifications
   
   # Or use Services.msc GUI
   services.msc
   ```

## Service Management

### Using Command Line
```bash
# Start service
sc start JawdaMedicalNotifications

# Stop service
sc stop JawdaMedicalNotifications

# Check status
sc query JawdaMedicalNotifications

# View service details
sc qc JawdaMedicalNotifications
```

### Using Services GUI
1. Press `Win + R`, type `services.msc`, press Enter
2. Find "Jawda Medical - Notifications Queue Worker"
3. Right-click for options (Start, Stop, Restart, Properties)

## Logs

Service logs are automatically created in:
- **Main Log**: `logs/notifications-service.log`
- **Error Log**: `logs/notifications-service-error.log`

Log rotation is configured:
- Daily rotation
- 1MB file size limit
- Online rotation (no service restart needed)

## Uninstallation

```bash
# Run as Administrator
uninstall-notifications-service.bat
```

## Service Configuration

The service is configured with:
- **Service Name**: `JawdaMedicalNotifications`
- **Display Name**: "Jawda Medical - Notifications Queue Worker"
- **Startup Type**: Automatic (starts with Windows)
- **Command**: `start-notifications-queue.bat`
- **Working Directory**: Project root
- **Logging**: Automatic with rotation

## Troubleshooting

### Service Won't Start
1. Check if PHP is in PATH
2. Verify Laravel project is properly configured
3. Check error logs in `logs/notifications-service-error.log`
4. Ensure database connection is working

### Service Stops Unexpectedly
1. Check error logs
2. Verify queue configuration in Laravel
3. Check system resources (memory, disk space)
4. Review Laravel logs in `storage/logs/`

### Permission Issues
1. Ensure running as Administrator
2. Check file permissions on project directory
3. Verify PHP and Laravel have necessary permissions

## Manual Service Management

If you need to manually manage the service without NSSM:

```bash
# Install service manually
sc create JawdaMedicalNotifications binPath="C:\path\to\start-notifications-queue.bat" start=auto

# Configure service
sc config JawdaMedicalNotifications start=auto
sc description JawdaMedicalNotifications "Laravel queue worker for notifications"

# Start/Stop
sc start JawdaMedicalNotifications
sc stop JawdaMedicalNotifications

# Remove service
sc delete JawdaMedicalNotifications
```

## Notes

- The service will automatically start when Windows boots
- Logs are rotated daily and when they reach 1MB
- The service runs the same command as the original batch file
- Make sure XAMPP or your PHP installation is properly configured
- The service inherits the environment from the system
