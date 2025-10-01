<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use App\Services\HL7\HL7MessageProcessor;
use React\EventLoop\Loop;
use React\Socket\SocketServer;

class HL7ServerCommand extends Command
{
    protected $signature = 'hl7:serve 
                            {--host=127.0.0.1 : The host to bind the server to}
                            {--port=6400 : The port to bind the server to}';

    protected $description = 'Start the HL7 TCP server to receive messages from laboratory devices';

    protected HL7MessageProcessor $messageProcessor;

    public function __construct(HL7MessageProcessor $messageProcessor)
    {
        parent::__construct();
        $this->messageProcessor = $messageProcessor;
    }

    public function handle(): int
    {
        $host = $this->option('host') ?: config('hl7.server.host', '127.0.0.1');
        $port = $this->option('port') ?: config('hl7.server.port', 6400);
        
        $address = "{$host}:{$port}";

        $this->info("Starting HL7 TCP server on {$address}...");
        $this->info("Supported devices: " . implode(', ', $this->messageProcessor->getAvailableDevices()));
        $this->info("Press Ctrl+C to stop the server");

        try {
            $loop = Loop::get();
            $socket = new SocketServer($address, [], $loop);

            $socket->on('connection', function ($connection) {
                $this->info('New connection established');
                
                $connection->on('data', function ($data) use ($connection) {
                    $this->info('Received data: ' . strlen($data) . ' bytes');
                    $this->messageProcessor->processMessage($data, $connection);
                });

                $connection->on('close', function () {
                    $this->info('Connection closed');
                });

                $connection->on('error', function ($error) {
                    $this->error('Connection error: ' . $error->getMessage());
                });
            });

            $socket->on('error', function ($error) {
                $this->error('Socket error: ' . $error->getMessage());
                Log::error('HL7 Server socket error: ' . $error->getMessage());
            });

            $this->info("HL7 server is running on {$address}");
            $loop->run();

        } catch (\Exception $e) {
            $this->error('Failed to start HL7 server: ' . $e->getMessage());
            Log::error('HL7 Server startup error: ' . $e->getMessage());
            return 1;
        }

        return 0;
    }
}
