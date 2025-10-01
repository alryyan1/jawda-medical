<?php

namespace App\Services\HL7;

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Services\HL7\Devices\MaglumiX3Handler;
use App\Services\HL7\Devices\MindrayCL900Handler;
use App\Services\HL7\Devices\BC6800Handler;
use App\Services\HL7\Devices\ACONHandler;

class HL7MessageProcessor
{
    protected array $deviceHandlers;

    public function __construct()
    {
        $this->deviceHandlers = [
            'MaglumiX3' => new MaglumiX3Handler(),
            'CL-900' => new MindrayCL900Handler(),
            'BC-6800' => new BC6800Handler(),
            'ACON' => new ACONHandler(),
        ];
    }

    /**
     * Process incoming HL7 message
     */
    public function processMessage(string $rawData, $connection): void
    {
        try {
            // Log raw message to database
            // $this->logRawMessage($rawData);

            // Check if message contains MSH segment
            if (!str_contains($rawData, 'MSH')) {
                Log::warning('HL7: Message does not contain MSH segment');
                return;
            }

            // Clean and parse message - preserve segment separators
            $cleanData = preg_replace('/\r\n|\r|\n/', "\r", $rawData); // Normalize line endings
            $cleanData = preg_replace('/[ \t]+/', ' ', $cleanData); // Replace multiple spaces/tabs with single space
            $cleanData = trim($cleanData); // Remove leading/trailing whitespace
            
            $msg = new Message($cleanData);
            // Log::info((string) $msg->toString());
            $msh = new MSH($msg->getSegmentByIndex(0)->getFields());
            Log::info("HL7: MSH", ['msh' => $msh]);
            // Extract device identifier from MSH field 4 (Sending Facility)
            $device = $msh->getField(4);
            
      

            // Route to appropriate device handler
            $this->routeToDeviceHandler($device, $msg, $msh, $connection);

        } catch (\Exception $e) {
            Log::error('HL7: Error processing message: ' . $e->getMessage(), [
                'exception' => $e,
                'raw_data' => $rawData
            ]);
        }
    }

    /**
     * Route message to appropriate device handler
     */
    protected function routeToDeviceHandler(string $device, Message $msg, MSH $msh, $connection): void
    {
        if (!isset($this->deviceHandlers[$device])) {
            Log::warning("HL7: Unknown device: {$device}");
            return;
        }
        $device = $msh->getField(4);
            
     
        $handler = $this->deviceHandlers[$device];
        $handler->processMessage($msg, $msh, $connection);
    }

    /**
     * Log raw HL7 message to database
     */
    protected function logRawMessage(string $rawData): void
    {
        try {
            DB::table('hl7_messages')->insert([
                'raw_message' => $rawData,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('HL7: Error logging raw message: ' . $e->getMessage());
        }
    }

    /**
     * Get available device handlers
     */
    public function getAvailableDevices(): array
    {
        return array_keys($this->deviceHandlers);
    }

    /**
     * Get device handler instance
     */
    public function getDeviceHandler(string $device)
    {
        return $this->deviceHandlers[$device] ?? null;
    }
}
