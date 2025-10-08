<?php

/**
 * HL7 Client - Standalone script to connect to laboratory devices
 * 
 * This script connects to a laboratory device via TCP and processes HL7 messages
 * Similar to the original client.php but integrated with Laravel
 * 
 * Usage: php hl7-client.php [--host=192.168.1.114] [--port=5100]
 */

require_once __DIR__ . '/vendor/autoload.php';

use Aranyasen\Exceptions\HL7Exception;
use Aranyasen\HL7\Message;
use React\Socket\ConnectionInterface;
use React\EventLoop\Loop;
use React\Socket\Connector;
use App\Models\HL7Message;

// Load Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Parse command line arguments
$options = getopt('', ['host:', 'port:', 'help']);
$host = $options['host'] ?? '192.168.1.114';
$port = $options['port'] ?? 5100;

if (isset($options['help'])) {
    echo "HL7 Client - Connect to laboratory devices\n";
    echo "Usage: php hl7-client.php [--host=IP] [--port=PORT]\n";
    echo "Options:\n";
    echo "  --host=IP     Target host IP (default: 192.168.1.114)\n";
    echo "  --port=PORT   Target port (default: 5100)\n";
    echo "  --help        Show this help message\n";
    exit(0);
}

$address = "{$host}:{$port}";
$shouldReconnect = true;

echo "🔌 HL7 Client starting...\n";
echo "📡 Connecting to: {$address}\n";
echo "⏹️  Press Ctrl+C to stop\n\n";

$loop = Loop::get();
$connector = new Connector($loop);

// Handle graceful shutdown
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGINT, function () use (&$shouldReconnect, $loop) {
        echo "\n🛑 Shutting down gracefully...\n";
        $shouldReconnect = false;
        $loop->stop();
    });

    $loop->addPeriodicTimer(0.1, function () {
        pcntl_signal_dispatch();
    });
}

function connectToDevice($connector, $address, $loop, &$shouldReconnect) {
    if (!$shouldReconnect) {
        return;
    }

    echo "🔄 Attempting to connect to {$address}...\n";

    $connector->connect($address)->then(
        function (ConnectionInterface $connection) use ($connector, $address, $loop, &$shouldReconnect) {
            echo "✅ Connected to {$address}\n";
            
            // Buffer to accumulate complete HL7 messages
            $messageBuffer = '';
            
            $connection->on('data', function ($data) use ($connection, $address, &$messageBuffer) {
                echo "📨 Received data: " . strlen($data) . " bytes from {$address}\n";
                echo "🔍 Data preview: " . substr($data, 0, 200) . "...\n";
                
                // Add data to buffer
                $messageBuffer .= $data;
                
                // Check if we have a complete HL7 message
                // HL7 messages typically end with a carriage return or are terminated by MLLP framing
                if (str_contains($messageBuffer, 'MSH') && (str_ends_with($messageBuffer, "\r") || str_ends_with($messageBuffer, "\n") || strlen($messageBuffer) > 1000)) {
                    echo "🔍 Complete HL7 message detected, processing...\n";
                    echo "🔍 Buffer length: " . strlen($messageBuffer) . " bytes\n";
                    echo "🔍 Contains MSH: " . (str_contains($messageBuffer, 'MSH') ? 'YES' : 'NO') . "\n";
                    
                    // Log raw message to database
                    try {
                        HL7Message::create([
                            'raw_message' => $messageBuffer,
                        ]);
                        echo "✅ HL7 message saved to database\n";
                    } catch (\Exception $e) {
                        echo "❌ Error saving to database: " . $e->getMessage() . "\n";
                    }
                    
                    // Process the complete message
                    try {
                        // Process using Laravel's HL7 message processor
                        $messageProcessor = app(\App\Services\HL7\HL7MessageProcessor::class);
                        $messageProcessor->processMessage($messageBuffer, $connection);
                        echo "✅ HL7 message processed successfully\n";
                    } catch (\Exception $e) {
                        echo "❌ Processing error: " . $e->getMessage() . "\n";
                    }
                    
                    // Clear buffer for next message
                    $messageBuffer = '';
                } else {
                    echo "⏳ Incomplete message, buffering... (buffer size: " . strlen($messageBuffer) . " bytes)\n";
                }
            });

            $connection->on('close', function () use ($connector, $address, $loop, &$shouldReconnect) {
                echo "🔌 Connection to {$address} closed\n";
                
                if ($shouldReconnect) {
                    echo "🔄 Reconnecting in 5 seconds...\n";
                    $loop->addTimer(5, function () use ($connector, $address, $loop, &$shouldReconnect) {
                        connectToDevice($connector, $address, $loop, $shouldReconnect);
                    });
                }
            });

            $connection->on('error', function ($error) use ($connector, $address, $loop, &$shouldReconnect) {
                echo "❌ Connection error to {$address}: " . $error->getMessage() . "\n";
                
                if ($shouldReconnect) {
                    echo "🔄 Reconnecting in 5 seconds...\n";
                    $loop->addTimer(5, function () use ($connector, $address, $loop, &$shouldReconnect) {
                        connectToDevice($connector, $address, $loop, $shouldReconnect);
                    });
                }
            });
        },
        function (\Exception $error) use ($connector, $address, $loop, &$shouldReconnect) {
            echo "❌ Failed to connect to {$address}: " . $error->getMessage() . "\n";
            
            if ($shouldReconnect) {
                echo "🔄 Retrying connection in 5 seconds...\n";
                $loop->addTimer(5, function () use ($connector, $address, $loop, &$shouldReconnect) {
                    connectToDevice($connector, $address, $loop, $shouldReconnect);
                });
            }
        }
    );
}

// Start the connection
connectToDevice($connector, $address, $loop, $shouldReconnect);

try {
    $loop->run();
} catch (\Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "👋 HL7 Client stopped\n";
