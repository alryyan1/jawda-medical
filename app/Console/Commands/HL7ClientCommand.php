<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\HL7\HL7MessageProcessor;
use App\Services\HL7\Devices\Mindray30sHandler;
use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use App\Models\HL7Message;
use React\EventLoop\Loop;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;

class HL7ClientCommand extends Command
{
    protected $signature = 'hl7:client 
                            {--host=192.168.1.114 : The host to connect to}
                            {--port=5100 : The port to connect to}
                            {--reconnect-delay=5 : Delay in seconds before reconnecting on failure}';

    protected $description = 'Start the HL7 TCP client to connect to Mindray 30s CBC analyzer and receive messages';

    protected Mindray30sHandler $mindrayHandler;
    protected ?ConnectionInterface $connection = null;
    protected bool $shouldReconnect = true;

    public function __construct()
    {
        parent::__construct();
        $this->mindrayHandler = new Mindray30sHandler();
    }

    public function handle(): int
    {
        $host = $this->option('host');
        $port = $this->option('port');
        $reconnectDelay = (int) $this->option('reconnect-delay');
        
        $address = "{$host}:{$port}";

        $this->info("Starting HL7 TCP client to connect to {$address}...");
        $this->info("Press Ctrl+C to stop the client");

        $loop = Loop::get();
        $connector = new Connector($loop);

        // Handle graceful shutdown
        $this->setupSignalHandlers($loop);

        // Start connection process
        $this->connectToServer($connector, $address, $reconnectDelay, $loop);

        try {
            $loop->run();
        } catch (\Exception $e) {
            $this->error('HL7 Client error: ' . $e->getMessage());
            Log::error('HL7 Client error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }

    /**
     * Connect to the HL7 server
     */
    protected function connectToServer(Connector $connector, string $address, int $reconnectDelay, $loop): void
    {
        if (!$this->shouldReconnect) {
            return;
        }

        $this->info("Attempting to connect to {$address}...");

        $connector->connect($address)->then(
            function (ConnectionInterface $connection) use ($address, $reconnectDelay, $loop) {
                $this->connection = $connection;
                $this->info("✅ Connected to {$address}");
                Log::info("HL7 Client connected to {$address}");

                // Set up connection event handlers
                $this->setupConnectionHandlers($connection, $address, $reconnectDelay, $loop);

            },
            function (\Exception $error) use ($address, $reconnectDelay, $loop) {
                $this->error("❌ Failed to connect to {$address}: " . $error->getMessage());
                Log::error("HL7 Client connection failed to {$address}: " . $error->getMessage());

                if ($this->shouldReconnect) {
                    $this->info("Reconnecting in {$reconnectDelay} seconds...");
                    $loop->addTimer($reconnectDelay, function () use ($connector, $address, $reconnectDelay, $loop) {
                        $this->connectToServer($connector, $address, $reconnectDelay, $loop);
                    });
                }
            }
        );
    }

    /**
     * Set up connection event handlers
     */
    protected function setupConnectionHandlers(ConnectionInterface $connection, string $address, int $reconnectDelay, $loop): void
    {
        $connector = new Connector($loop);
        $messageBuffer = '';

        $connection->on('data', function ($data) use ($connection, &$messageBuffer) {
            $this->info('📨 Received data: ' . strlen($data) . ' bytes');
            Log::info('HL7 Client received data', ['bytes' => strlen($data)]);
            
            // Add data to buffer
            $messageBuffer .= $data;
            
            // Check if we have a complete HL7 message
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
                try {
                    // Find the MSH segment start
                    $mshStart = strpos($cleanData, 'MSH');
                    if ($mshStart === false) {
                        $this->error('❌ MSH segment not found in message');
                        return;
                    }
                    
                    $row = substr($cleanData, $mshStart);
                    $msg = new Message($row);
                    
                    // Create MSH segment for the handler
                    $msh = new MSH($msg->getSegmentByIndex(0)->getFields());
                    
                    // Log raw message to database
                    try {
                        HL7Message::create([
                            'raw_message' => $cleanData,
                        ]);
                        $this->info('✅ HL7 message saved to database');
                    } catch (\Exception $e) {
                        $this->error('❌ Error saving to database: ' . $e->getMessage());
                    }
                    
                    // Process using Mindray30sHandler directly
                    $this->mindrayHandler->processMessage($msg, $msh, $connection);
                    $this->info('✅ HL7 message processed successfully by Mindray30sHandler');
                    
                } catch (\Exception $e) {
                    $this->error('❌ Processing error: ' . $e->getMessage());
                    Log::error('HL7: Error processing message: ' . $e->getMessage(), [
                        'exception' => $e,
                        'raw_data' => $cleanData
                    ]);
                }
            } else {
                $this->warn('⏳ Buffering message... (buffer size: ' . strlen($messageBuffer) . ' bytes)');
            }
        });

        $connection->on('close', function () use ($address, $reconnectDelay, $loop, $connector) {
            $this->warn("🔌 Connection to {$address} closed");
            Log::info("HL7 Client connection to {$address} closed");
            $this->connection = null;

            if ($this->shouldReconnect) {
                $this->info("Attempting to reconnect in {$reconnectDelay} seconds...");
                $loop->addTimer($reconnectDelay, function () use ($connector, $address, $reconnectDelay, $loop) {
                    $this->connectToServer($connector, $address, $reconnectDelay, $loop);
                });
            }
        });

        $connection->on('error', function ($error) use ($address, $reconnectDelay, $loop, $connector) {
            $this->error("❌ Connection error to {$address}: " . $error->getMessage());
            Log::error("HL7 Client connection error to {$address}: " . $error->getMessage());
            $this->connection = null;

            if ($this->shouldReconnect) {
                $this->info("Attempting to reconnect in {$reconnectDelay} seconds...");
                $loop->addTimer($reconnectDelay, function () use ($connector, $address, $reconnectDelay, $loop) {
                    $this->connectToServer($connector, $address, $reconnectDelay, $loop);
                });
            }
        });
    }

    /**
     * Set up signal handlers for graceful shutdown
     */
    protected function setupSignalHandlers($loop): void
    {
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () use ($loop) {
                $this->info("\n🛑 Received SIGINT, shutting down gracefully...");
                $this->shutdown($loop);
            });

            pcntl_signal(SIGTERM, function () use ($loop) {
                $this->info("\n🛑 Received SIGTERM, shutting down gracefully...");
                $this->shutdown($loop);
            });

            $loop->addPeriodicTimer(0.1, function () {
                pcntl_signal_dispatch();
            });
        }
    }

    /**
     * Graceful shutdown
     */
    protected function shutdown($loop): void
    {
        $this->shouldReconnect = false;
        
        if ($this->connection) {
            $this->info("Closing connection...");
            $this->connection->close();
        }

        $this->info("HL7 Client stopped");
        Log::info("HL7 Client stopped gracefully");
        
        $loop->stop();
    }

    /**
     * Get connection status
     */
    public function isConnected(): bool
    {
        return $this->connection !== null;
    }

    /**
     * Get connection instance
     */
    public function getConnection(): ?ConnectionInterface
    {
        return $this->connection;
    }
}

