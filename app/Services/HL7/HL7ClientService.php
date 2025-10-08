<?php

namespace App\Services\HL7;

use Illuminate\Support\Facades\Log;
use React\EventLoop\Loop;
use React\Socket\Connector;
use React\Socket\ConnectionInterface;
use App\Services\HL7\HL7MessageProcessor;

class HL7ClientService
{
    protected HL7MessageProcessor $messageProcessor;
    protected ?ConnectionInterface $connection = null;
    protected bool $isConnected = false;
    protected bool $shouldReconnect = true;
    protected string $host;
    protected int $port;
    protected int $reconnectDelay;
    protected $loop;
    protected $connector;

    public function __construct(HL7MessageProcessor $messageProcessor)
    {
        $this->messageProcessor = $messageProcessor;
        $this->host = config('hl7.client.host', '192.168.1.114');
        $this->port = config('hl7.client.port', 5100);
        $this->reconnectDelay = config('hl7.client.reconnect_delay', 5);
        $this->loop = Loop::get();
        $this->connector = new Connector($this->loop);
    }

    /**
     * Start the HL7 client connection
     */
    public function start(): void
    {
        Log::info("Starting HL7 client to {$this->host}:{$this->port}");
        $this->connect();
    }

    /**
     * Stop the HL7 client connection
     */
    public function stop(): void
    {
        $this->shouldReconnect = false;
        
        if ($this->connection) {
            Log::info("Closing HL7 client connection");
            $this->connection->close();
            $this->connection = null;
            $this->isConnected = false;
        }
    }

    /**
     * Connect to the HL7 server
     */
    protected function connect(): void
    {
        if (!$this->shouldReconnect) {
            return;
        }

        $address = "{$this->host}:{$this->port}";
        Log::info("Attempting to connect to HL7 server at {$address}");

        $this->connector->connect($address)->then(
            function (ConnectionInterface $connection) use ($address) {
                $this->connection = $connection;
                $this->isConnected = true;
                Log::info("HL7 client connected to {$address}");

                $this->setupConnectionHandlers($connection, $address);
            },
            function (\Exception $error) use ($address) {
                Log::error("HL7 client connection failed to {$address}: " . $error->getMessage());
                $this->isConnected = false;

                if ($this->shouldReconnect) {
                    Log::info("HL7 client will reconnect in {$this->reconnectDelay} seconds");
                    $this->loop->addTimer($this->reconnectDelay, function () {
                        $this->connect();
                    });
                }
            }
        );
    }

    /**
     * Set up connection event handlers
     */
    protected function setupConnectionHandlers(ConnectionInterface $connection, string $address): void
    {
        $connection->on('data', function ($data) use ($connection) {
            Log::info('HL7 client received data', [
                'bytes' => strlen($data),
                'address' => $address
            ]);
            
            // Process the HL7 message using the existing message processor
            $this->messageProcessor->processMessage($data, $connection);
        });

        $connection->on('close', function () use ($address) {
            Log::info("HL7 client connection to {$address} closed");
            $this->connection = null;
            $this->isConnected = false;

            if ($this->shouldReconnect) {
                Log::info("HL7 client attempting to reconnect in {$this->reconnectDelay} seconds");
                $this->loop->addTimer($this->reconnectDelay, function () {
                    $this->connect();
                });
            }
        });

        $connection->on('error', function ($error) use ($address) {
            Log::error("HL7 client connection error to {$address}: " . $error->getMessage());
            $this->connection = null;
            $this->isConnected = false;

            if ($this->shouldReconnect) {
                Log::info("HL7 client attempting to reconnect in {$this->reconnectDelay} seconds");
                $this->loop->addTimer($this->reconnectDelay, function () {
                    $this->connect();
                });
            }
        });
    }

    /**
     * Send data to the connected server
     */
    public function sendData(string $data): bool
    {
        if (!$this->isConnected || !$this->connection) {
            Log::warning("HL7 client cannot send data: not connected");
            return false;
        }

        try {
            $this->connection->write($data);
            Log::info("HL7 client sent data", ['bytes' => strlen($data)]);
            return true;
        } catch (\Exception $e) {
            Log::error("HL7 client failed to send data: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if client is connected
     */
    public function isConnected(): bool
    {
        return $this->isConnected && $this->connection !== null;
    }

    /**
     * Get connection status info
     */
    public function getStatus(): array
    {
        return [
            'connected' => $this->isConnected(),
            'host' => $this->host,
            'port' => $this->port,
            'reconnect_delay' => $this->reconnectDelay,
            'should_reconnect' => $this->shouldReconnect,
        ];
    }

    /**
     * Update connection settings
     */
    public function updateSettings(array $settings): void
    {
        if (isset($settings['host'])) {
            $this->host = $settings['host'];
        }
        if (isset($settings['port'])) {
            $this->port = $settings['port'];
        }
        if (isset($settings['reconnect_delay'])) {
            $this->reconnectDelay = $settings['reconnect_delay'];
        }

        Log::info("HL7 client settings updated", $settings);
    }
}

