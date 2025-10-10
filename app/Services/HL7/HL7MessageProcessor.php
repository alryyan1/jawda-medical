<?php

namespace App\Services\HL7;

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\HL7Message;
use App\Services\HL7\Devices\MaglumiX3Handler;
use App\Services\HL7\Devices\MindrayCL900Handler;
use App\Services\HL7\Devices\BC6800Handler;
use App\Services\HL7\Devices\ACONHandler;
use App\Services\HL7\Devices\ZybioHandler;
use App\Services\HL7\Devices\SysmexCbcInserter;
use App\Services\HL7\Devices\UritHandler;
use App\Services\HL7\Devices\Mindray30sHandler;

class HL7MessageProcessor
{
    protected array $deviceHandlers;

    public function __construct()
    {
        $this->deviceHandlers = [
            'MaglumiX3' => new MaglumiX3Handler(),
            'CL-900' => new MindrayCL900Handler(),
            'BC-6800' => new BC6800Handler(new SysmexCbcInserter()),
            'ACON' => new ACONHandler(),
            'Z3' => new ZybioHandler(new SysmexCbcInserter()),
            'URIT' => new UritHandler(new SysmexCbcInserter()),
            'Mindray30s' => new Mindray30sHandler(new SysmexCbcInserter()),
        ];
    }

    /**
     * Process incoming HL7 message
     */
    public function processMessage(string $rawData, $connection): void
    {
        try {
            // Debug: Log the received data
            Log::info('HL7: Received data for processing', [
                'data_length' => strlen($rawData),
                'data_preview' => substr($rawData, 0, 200),
                'contains_MSH' => str_contains($rawData, 'MSH'),
                'contains_PID' => str_contains($rawData, 'PID'),
                'contains_OBX' => str_contains($rawData, 'OBX'),
            ]);
            
            // Check if message contains MSH segment
            if (!str_contains($rawData, 'MSH')) {
                Log::warning('HL7: Message does not contain MSH segment', [
                    'data_length' => strlen($rawData),
                    'data_preview' => substr($rawData, 0, 100),
                ]);
                return;
            }
            $hl  = preg_replace('/\s+/', '', $rawData);
            $row = substr($hl,strpos($hl,'MSH'));

            // Clean and parse message - preserve segment separators
            // $cleanData = preg_replace('/\r\n|\r|\n/', "\r", $rawData); // Normalize line endings
            // $cleanData = preg_replace('/[ \t]+/', ' ', $cleanData); // Replace multiple spaces/tabs with single space
            // $cleanData = trim($cleanData); // Remove leading/trailing whitespace

            // Try to parse HL7 message; if it fails, attempt Zybio auto-format then retry
            try {
                $msg = new Message($row);
            } catch (\Throwable $parseError) {
                Log::warning('HL7: Initial parse failed; applying Zybio format correction', [
                    'error' => $parseError->getMessage()
                ]);
                $formatted = ZybioHandler::correctHl7MessageFormat($rawData);
                $msg = new Message($formatted);
            }

            $msh = new MSH($msg->getSegmentByIndex(0)->getFields());
            Log::info("HL7: MSH", ['msh' => $msh]);
            
            // Log message to database
            $hl7Message = $this->logMessage($rawData, $msg, $msh);

            // Extract device identifier from MSH field 4 (Sending Facility)
            $device = $msh->getField(4);
            if (is_array($device)) {
                $device = implode('^', $device);
            }

            // Route to appropriate device handler
            $this->routeToDeviceHandler($device, $msg, $msh, $connection, $hl7Message);

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
    protected function routeToDeviceHandler(string $device, Message $msg, MSH $msh, $connection, HL7Message $hl7Message): void
    {
        if (!isset($this->deviceHandlers[$device])) {
            Log::warning("HL7: Unknown device: {$device}");
            return;
        }
        
        $handler = $this->deviceHandlers[$device];
        $handler->processMessage($msg, $msh, $connection);
        
        // Mark message as processed
        $hl7Message->markAsProcessed("Processed by {$device} handler");
    }

    /**
     * Log HL7 message to database with parsed information
     */
    protected function logMessage(string $rawData, Message $msg, MSH $msh): HL7Message
    {
        try {
            // Extract MSH fields
            $sendingApplication = $msh->getField(3);
            $sendingFacility = $msh->getField(4);
            $receivingApplication = $msh->getField(5);
            $receivingFacility = $msh->getField(6);
            $messageDateTime = $msh->getField(7);
            $messageType = $msh->getField(9);
            $messageControlId = $msh->getField(10);

            // Convert arrays to strings
            if (is_array($sendingApplication)) $sendingApplication = implode('^', $sendingApplication);
            if (is_array($sendingFacility)) $sendingFacility = implode('^', $sendingFacility);
            if (is_array($receivingApplication)) $receivingApplication = implode('^', $receivingApplication);
            if (is_array($receivingFacility)) $receivingFacility = implode('^', $receivingFacility);
            if (is_array($messageType)) $messageType = implode('^', $messageType);

            // Parse message datetime
            $parsedDateTime = null;
            if ($messageDateTime) {
                try {
                    $parsedDateTime = \Carbon\Carbon::createFromFormat('YmdHis', $messageDateTime);
                } catch (\Exception $e) {
                    Log::warning('HL7: Could not parse message datetime: ' . $messageDateTime);
                }
            }

            return HL7Message::create([
                'raw_message' => $rawData,
                'device' => $sendingFacility,
                'message_type' => $messageType,
            ]);
        } catch (\Exception $e) {
            Log::error('HL7: Error logging message: ' . $e->getMessage());
            
            // Fallback: create basic record
            return HL7Message::create([
                'raw_message' => $rawData,
            ]);
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
