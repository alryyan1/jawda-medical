# Node.js Windows Service for Jawda Medical Notifications Queue

This setup uses Node.js and the `node-windows` package to create a Windows service for the Laravel notifications queue worker.

## Prerequisites

1. **Node.js** (version 14 or higher)
   - Download from: https://nodejs.org/
   - Ensure npm is included in the installation

2. **Administrator Privileges**
   - Required for installing/removing Windows services
   - Right-click Command Prompt and select "Run as administrator"

## Quick Setup

1. **Run the setup script**:
   ```bash
   setup-service.bat
   ```
   This will:
   - Check Node.js installation
   - Install required dependencies
   - Optionally install the Windows service

## Manual Setup

### 1. Install Dependencies
```bash
npm install
```

### 2. Install the Service
```bash
# Run as Administrator
node install-service.js
```

### 3. Verify Installation
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

### Using Node.js Scripts
```bash
# Install service
node install-service.js

# Uninstall service
node uninstall-service.js

# Test service (run directly)
node notifications-service.js
```

## Service Features

- **Automatic Restart**: Service automatically restarts if the queue worker crashes (up to 5 times)
- **Graceful Shutdown**: Handles SIGTERM and SIGINT signals properly
- **Comprehensive Logging**: Logs to both console and files
- **Error Handling**: Catches and logs uncaught exceptions and unhandled rejections
- **Memory Management**: Configured with 4GB memory limit for Node.js

## Logs

Service logs are automatically created in:
- **Main Log**: `logs/notifications-service.log`
- **Error Log**: `logs/notifications-service-error.log`

Log format includes timestamps and process information.

## Service Configuration

The service is configured with:
- **Service Name**: `JawdaMedicalNotifications`
- **Display Name**: "Jawda Medical - Notifications Queue Worker"
- **Startup Type**: Automatic (starts with Windows)
- **Script**: `notifications-service.js`
- **Node Options**: `--max_old_space_size=4096`
- **Environment**: `NODE_ENV=production`

## Troubleshooting

### Service Won't Start
1. Check if Node.js is properly installed
2. Verify PHP is in PATH
3. Check error logs in `logs/notifications-service-error.log`
4. Ensure Laravel project is properly configured

### Service Stops Unexpectedly
1. Check error logs for crash information
2. Verify queue configuration in Laravel
3. Check system resources (memory, disk space)
4. Review Laravel logs in `storage/logs/`

### Permission Issues
1. Ensure running as Administrator
2. Check file permissions on project directory
3. Verify Node.js and PHP have necessary permissions

### Node.js Issues
```bash
# Check Node.js version
node --version

# Check npm version
npm --version

# Reinstall dependencies
npm install --force

# Test service directly
node notifications-service.js
```

## Development and Testing

### Test the Service Directly
```bash
# Run the service without installing as Windows service
node notifications-service.js
```

### Debug Mode
You can modify `notifications-service.js` to add more verbose logging or debugging information.

### Service Status
The service provides status information including:
- Running state
- Restart count
- Process ID (PID)

## Uninstallation

```bash
# Run as Administrator
node uninstall-service.js
```

Or manually:
```bash
sc stop JawdaMedicalNotifications
sc delete JawdaMedicalNotifications
```

## Advantages of Node.js Service

1. **Better Process Management**: Automatic restart on crashes
2. **Comprehensive Logging**: Built-in logging with timestamps
3. **Error Handling**: Catches and handles various error conditions
4. **Memory Management**: Configurable memory limits
5. **Graceful Shutdown**: Proper cleanup on service stop
6. **Cross-Platform**: Can be adapted for other operating systems

## File Structure

```
jawda-medical/
├── notifications-service.js      # Main service script
├── install-service.js           # Service installation script
├── uninstall-service.js         # Service removal script
├── setup-service.bat           # Setup automation script
├── package.json                # Node.js dependencies
├── logs/                       # Service logs (auto-created)
│   ├── notifications-service.log
│   └── notifications-service-error.log
└── README-NodeJS-Service.md    # This documentation
```

## Notes

- The service will automatically start when Windows boots
- Logs are written to both console and files
- The service runs the same Laravel queue command as the original batch file
- Make sure XAMPP or your PHP installation is properly configured
- The service inherits the environment from the system
