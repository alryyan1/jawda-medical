<?php

/**
 * HL7 Client - Standalone script to connect to Mindray 30s CBC analyzer
 * 
 * This script connects to a Mindray 30s device via TCP and processes HL7 messages
 * using Mindray30sHandler directly instead of going through HL7MessageProcessor
 * 
 * Usage: php hl7-client.php [--host=192.168.1.114] [--port=5100]
 */

require_once __DIR__ . '/vendor/autoload.php';

use Aranyasen\Exceptions\HL7Exception;
use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use React\Socket\ConnectionInterface;
use React\EventLoop\Loop;
use React\Socket\Connector;
use App\Models\HL7Message;
use App\Services\HL7\Devices\Mindray30sHandler;

// Load Laravel application
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

// Parse command line arguments
$options = getopt('', ['host:', 'port:', 'help']);
$host = $options['host'] ?? '192.168.1.114';
$port = $options['port'] ?? 5100;

if (isset($options['help'])) {
    echo "HL7 Client - Connect to Mindray 30s CBC analyzer\n";
    echo "Usage: php hl7-client.php [--host=IP] [--port=PORT]\n";
    echo "Options:\n";
    echo "  --host=IP     Target host IP (default: 192.168.1.114)\n";
    echo "  --port=PORT   Target port (default: 5100)\n";
    echo "  --help        Show this help message\n";
    echo "\nThis client processes HL7 messages using Mindray30sHandler directly.\n";
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
                
                // Add data to buffer
                $messageBuffer .= $data;
                
                // Check if we have a complete HL7 message
                // Look for MLLP framing or message boundaries
                $messageComplete = false;
                $cleanData = '';
                
                // Check for MLLP framing (starts with \x0B, ends with \x1C)
                if (strpos($messageBuffer, "\x0B") !== false && strpos($messageBuffer, "\x1C") !== false) {
                    $startPos = strpos($messageBuffer, "\x0B");
                    $endPos = strpos($messageBuffer, "\x1C", $startPos);
                    
                    if ($endPos !== false) {
                        // Extract the complete MLLP message
                        $mllpMessage = substr($messageBuffer, $startPos, $endPos - $startPos + 1);
                        
                        // Handle MLLP framing - remove start and end characters
                        $cleanData = $mllpMessage;
                        if (substr($cleanData, 0, 1) === "\x0B") { // Remove start character
                            $cleanData = substr($cleanData, 1);
                        }
                        if (substr($cleanData, -1) === "\x1C") { // Remove end character
                            $cleanData = substr($cleanData, 0, -1);
                        }
                        if (substr($cleanData, -1) === "\x0D") { // Remove carriage return
                            $cleanData = substr($cleanData, 0, -1);
                        }
                        
                        $messageComplete = true;
                        $messageBuffer = substr($messageBuffer, $endPos + 1); // Remove processed message
                    }
                }
                // Check for simple MSH-based message (fallback)
                elseif (str_contains($messageBuffer, 'MSH') && strlen($messageBuffer) > 100) {
                    // Assume we have a complete message if it contains MSH and is reasonably long
                    $cleanData = $messageBuffer;
                    $messageComplete = true;
                    $messageBuffer = ''; // Clear buffer
                }
                
                if ($messageComplete && !empty($cleanData)) {
                    echo "🔍 Complete HL7 message detected, processing...\n";
                    echo "🔍 Clean data length: " . strlen($cleanData) . " bytes\n";
                    echo "🔍 Message preview: " . substr($cleanData, 0, 200) . "...\n";
                    
                    // Find the MSH segment start
                    $mshStart = strpos($cleanData, 'MSH');
                    if ($mshStart === false) {
                        echo "❌ MSH segment not found in message\n";
                        return;
                    }
                    
                    $row = substr($cleanData, $mshStart);
                    
                    try {
                        $msg = new Message($row);
                        
                        // Log raw message to database
                        try {
                            HL7Message::create([
                                'raw_message' => $cleanData,
                            ]);
                            echo "✅ HL7 message saved to database\n";
                        } catch (\Exception $e) {
                            echo "❌ Error saving to database: " . $e->getMessage() . "\n";
                        }
                        
                        // Process the complete message using Mindray30sHandler directly
                        try {
                            // Create MSH segment for the handler
                            $msh = new MSH($msg->getSegmentByIndex(0)->getFields());
                            
                            // Log message details before processing
                            echo "🔍 Message segments: " . count($msg->getSegments()) . "\n";
                            
                            // Process using Mindray30sHandler directly
                            $mindrayHandler = new Mindray30sHandler();
                            $mindrayHandler->processMessage($msg, $msh, $connection);
                            echo "✅ HL7 message processed successfully by Mindray30sHandler\n";
                        } catch (\Exception $e) {
                            echo "❌ Processing error: " . $e->getMessage() . "\n";
                            echo "❌ Error details: " . $e->getTraceAsString() . "\n";
                        }
                        
                    } catch (\Exception $e) {
                        echo "❌ Message parsing error: " . $e->getMessage() . "\n";
                    }
                } else {
                    echo "⏳ Buffering message... (buffer size: " . strlen($messageBuffer) . " bytes)\n";
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
