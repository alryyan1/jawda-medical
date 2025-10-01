# HL7 TCP Server Setup

This document explains how to set up and run the HL7 TCP server in the Jawda Medical Laravel project.

## Overview

The HL7 server receives messages from laboratory devices and processes them according to device-specific protocols:

- **Maglumi X3**: Hormone testing device
- **Mindray CL-900**: Chemistry analyzer
- **BC-6800**: CBC (Complete Blood Count) analyzer

## Prerequisites

1. PHP 8.1 or higher
2. Laravel 10.x
3. Composer
4. MySQL/MariaDB database

## Installation

### 1. Install Dependencies

```bash
cd jawda-medical
composer install
```

### 2. Run Database Migration

```bash
php artisan migrate
```

This will create the `hl7_messages` table to log all incoming HL7 messages.

### 3. Configure Environment

Add the following to your `.env` file:

```env
HL7_SERVER_HOST=127.0.0.1
HL7_SERVER_PORT=6400
```

## Running the Server

### Start the HL7 Server

```bash
php artisan hl7:serve
```

### Custom Host/Port

```bash
php artisan hl7:serve --host=192.168.1.100 --port=6400
```

### Run in Background (Linux/Mac)

```bash
nohup php artisan hl7:serve > hl7-server.log 2>&1 &
```

### Windows Service (Optional)

Create a batch file `start-hl7-server.bat`:

```batch
@echo off
cd /d "C:\xampp\htdocs\jawda-medical"
php artisan hl7:serve
pause
```

## Device Configuration

### Supported Devices

The server automatically detects devices based on the MSH segment field 3:

- `MaglumiX3` → MaglumiX3Handler
- `CL-900` → MindrayCL900Handler  
- `BC-6800` → BC6800Handler

### Test Mappings

Device-specific test mappings are configured in `config/hl7.php`:

- **Hormone tests**: Maps main test IDs to Maglumi test codes
- **Chemistry tests**: Maps main test IDs to Mindray test codes

## Message Flow

1. **Device Connection**: Laboratory device connects to TCP server
2. **Message Reception**: Raw HL7 message received and logged
3. **Device Detection**: MSH segment parsed to identify device type
4. **Message Processing**: Device-specific handler processes message
5. **Response**: Appropriate ACK/response sent back to device

## Logging

All HL7 messages are logged to:

- **Database**: `hl7_messages` table
- **Laravel Logs**: `storage/logs/laravel.log`

## Troubleshooting

### Common Issues

1. **Port Already in Use**
   ```
   Error: Address already in use
   ```
   Solution: Change port or kill existing process

2. **Permission Denied**
   ```
   Error: Permission denied
   ```
   Solution: Run with appropriate permissions or use different port

3. **Database Connection**
   ```
   Error: Database connection failed
   ```
   Solution: Check database configuration in `.env`

### Debug Mode

Enable detailed logging by setting in `.env`:

```env
LOG_LEVEL=debug
```

### Test Connection

Use telnet to test server connectivity:

```bash
telnet 127.0.0.1 6400
```

## Device-Specific Details

### Maglumi X3

- **Message Types**: TSREQ (test request), OUL (results)
- **Test Mapping**: Hormone tests (TSH, T3, T4, etc.)
- **Response**: RTA message with test list

### Mindray CL-900

- **Message Types**: QRY (query), ORU (results)
- **Test Mapping**: Chemistry profiles (RFT, Liver, Lipid)
- **Response**: DSP message with test list

### BC-6800

- **Message Types**: ORU (results)
- **Test Mapping**: CBC parameters (WBC, RBC, HGB, etc.)
- **Response**: ACK message

## Security Considerations

1. **Network Security**: Ensure server is behind firewall
2. **Access Control**: Limit device IP addresses if possible
3. **Data Validation**: All incoming messages are validated
4. **Logging**: Monitor logs for suspicious activity

## Performance

- **Concurrent Connections**: Supports multiple device connections
- **Message Processing**: Asynchronous processing with ReactPHP
- **Database**: Optimized with indexes for fast queries

## Maintenance

### Log Rotation

Configure log rotation to prevent disk space issues:

```bash
# Add to crontab
0 0 * * * find /path/to/logs -name "*.log" -mtime +30 -delete
```

### Database Cleanup

Regularly clean old HL7 messages:

```sql
DELETE FROM hl7_messages WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

## Support

For issues or questions:

1. Check Laravel logs: `storage/logs/laravel.log`
2. Check database logs: `hl7_messages` table
3. Verify device configuration
4. Test network connectivity

## Development

### Adding New Devices

1. Create device handler class in `app/Services/HL7/Devices/`
2. Add device mapping to `config/hl7.php`
3. Update `HL7MessageProcessor` to include new handler
4. Test with device simulator

### Customizing Test Mappings

Edit `config/hl7.php` to modify test mappings for your specific devices and test codes.
