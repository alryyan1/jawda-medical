# HL7 Client Implementation

This implementation provides multiple ways to connect to laboratory devices and receive HL7 messages, similar to the original `client.php` but integrated with Laravel.

## Components

### 1. Laravel Artisan Command
**File**: `app/Console/Commands/HL7ClientCommand.php`

```bash
# Start HL7 client with default settings
php artisan hl7:client

# Start with custom host and port
php artisan hl7:client --host=192.168.1.100 --port=5200

# Start with custom reconnect delay
php artisan hl7:client --reconnect-delay=10
```

### 2. Standalone Script
**File**: `hl7-client.php`

```bash
# Run standalone client
php hl7-client.php

# Run with custom settings
php hl7-client.php --host=192.168.1.100 --port=5200

# Show help
php hl7-client.php --help
```

### 3. Service Class
**File**: `app/Services/HL7/HL7ClientService.php`

Use this in your Laravel application:

```php
use App\Services\HL7\HL7ClientService;

$clientService = app(HL7ClientService::class);
$clientService->start();

// Check connection status
if ($clientService->isConnected()) {
    echo "Connected to HL7 device";
}

// Send data to device
$clientService->sendData("HL7 message data");
```

## Configuration

### Environment Variables
Add these to your `.env` file:

```env
# HL7 Client Configuration
HL7_CLIENT_HOST=192.168.1.114
HL7_CLIENT_PORT=5100
HL7_CLIENT_RECONNECT_DELAY=5
HL7_CLIENT_ENABLED=true

# HL7 Server Configuration (if running server)
HL7_SERVER_HOST=127.0.0.1
HL7_SERVER_PORT=6400
HL7_SERVER_ENABLED=true

# HL7 Processing Configuration
HL7_LOG_RAW_MESSAGES=true
HL7_LOG_PROCESSED_MESSAGES=true
HL7_MAX_MESSAGE_SIZE=65536
HL7_TIMEOUT=30

# Database Configuration
HL7_DB_LOG_MESSAGES=true
HL7_DB_TABLE=hl7_messages
HL7_DB_RETENTION_DAYS=30
```

### Configuration File
**File**: `config/hl7.php`

This file contains all HL7-related configuration including supported devices and processing settings.

## Database

### Migration
Run the migration to create the HL7 messages table:

```bash
php artisan migrate
```

### Model
**File**: `app/Models/HL7Message.php`

The model provides methods to work with HL7 messages:

```php
use App\Models\HL7Message;

// Get unprocessed messages
$unprocessed = HL7Message::unprocessed()->get();

// Get messages by device
$deviceMessages = HL7Message::byDevice('BC-6800')->get();

// Mark message as processed
$message = HL7Message::find(1);
$message->markAsProcessed('Successfully processed');
```

## Features

### 1. Automatic Reconnection
- Automatically reconnects if connection is lost
- Configurable reconnect delay
- Graceful shutdown handling

### 2. Message Processing
- Integrates with existing HL7 message processor
- Supports all existing device handlers:
  - MaglumiX3
  - CL-900 (Mindray)
  - BC-6800 (Mindray)
  - ACON
  - Z3 (Zybio)
  - URIT

### 3. Database Logging
- Logs all received HL7 messages
- Parses and stores message metadata
- Tracks processing status
- Stores parsed data as JSON

### 4. Error Handling
- Comprehensive error logging
- Graceful error recovery
- Detailed error messages

## Usage Examples

### Running as a Service
```bash
# Start the client as a background service
nohup php artisan hl7:client > hl7-client.log 2>&1 &

# Or use the standalone script
nohup php hl7-client.php > hl7-client.log 2>&1 &
```

### Integration with Existing Code
The client integrates seamlessly with your existing HL7 infrastructure:

```php
// Your existing device handlers will work automatically
// The client uses the same HL7MessageProcessor
// Messages are processed by the same device handlers
```

### Monitoring
```php
// Check connection status
$clientService = app(HL7ClientService::class);
$status = $clientService->getStatus();

// View recent messages
$recentMessages = HL7Message::latest()->take(10)->get();

// Check processing status
$unprocessedCount = HL7Message::unprocessed()->count();
```

## Comparison with Original client.php

| Feature | Original client.php | New HL7 Client |
|---------|-------------------|----------------|
| Connection | ✅ TCP Connection | ✅ TCP Connection |
| Message Processing | ✅ HL7 Parsing | ✅ HL7 Parsing |
| Device Support | ✅ CBC Processing | ✅ All Device Handlers |
| Database Logging | ✅ Basic Logging | ✅ Comprehensive Logging |
| Error Handling | ❌ Basic | ✅ Advanced |
| Reconnection | ❌ Manual | ✅ Automatic |
| Configuration | ❌ Hardcoded | ✅ Configurable |
| Laravel Integration | ❌ None | ✅ Full Integration |
| Monitoring | ❌ None | ✅ Built-in |

## Troubleshooting

### Connection Issues
1. Check network connectivity to the device
2. Verify host and port settings
3. Check firewall settings
4. Review logs for connection errors

### Message Processing Issues
1. Check device handler configuration
2. Review HL7 message format
3. Check database connection
4. Review processing logs

### Performance Issues
1. Monitor database size
2. Check message processing speed
3. Review system resources
4. Consider message retention policies

## Logs

The client logs to Laravel's log system. Check these locations:

- `storage/logs/laravel.log` - General application logs
- `storage/logs/hl7-client.log` - If running standalone script

Log levels:
- `INFO` - Connection status, message reception
- `WARNING` - Non-critical issues
- `ERROR` - Critical errors, connection failures

