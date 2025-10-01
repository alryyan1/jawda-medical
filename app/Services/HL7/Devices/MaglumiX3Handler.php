<?php

namespace App\Services\HL7\Devices;

use Aranyasen\HL7\Message;
use Aranyasen\HL7\Segments\MSH;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MaglumiX3Handler
{
    protected array $hormoneMapping;

    public function __construct()
    {
        $this->hormoneMapping = config('hl7.test_mappings.hormone', []);
    }

    /**
     * Process HL7 message from Maglumi X3 device
     */
    public function processMessage(Message $msg, MSH $msh, $connection): void
    {
        try {
            $msgType = $msh->getMessageType(10);
            $pid = $this->getPatientId($msg, $msgType);

            if (!$pid) {
                Log::warning('MaglumiX3: Could not extract patient ID from message');
                return;
            }

            if ($msgType === 'TSREQ') {
                $this->handleTestRequest($msg, $pid, $connection);
            } elseif ($msgType === 'OUL') {
                $this->handleResultMessage($msg, $pid);
            }

        } catch (\Exception $e) {
            Log::error('MaglumiX3 processing error: ' . $e->getMessage(), [
                'exception' => $e,
                'message' => $msg->toString()
            ]);
        }
    }

    /**
     * Handle test request (TSREQ) message
     */
    protected function handleTestRequest(Message $msg, int $pid, $connection): void
    {
        try {
            $tests = $this->buildTestString($pid);
            
            if (empty($tests)) {
                Log::info("MaglumiX3: No hormone tests found for patient {$pid}");
                return;
            }

            $response = $this->generateTestResponse($pid, $tests);
            $connection->write($response);

            Log::info("MaglumiX3: Sent test response for patient {$pid}");

        } catch (\Exception $e) {
            Log::error('MaglumiX3 TSREQ error: ' . $e->getMessage());
        }
    }

    /**
     * Handle result (OUL) message
     */
    protected function handleResultMessage(Message $msg, int $pid): void
    {
        try {
            // Process hormone results and save to database
            $this->processHormoneResults($msg, $pid);
            
            Log::info("MaglumiX3: Processed results for patient {$pid}");

        } catch (\Exception $e) {
            Log::error('MaglumiX3 OUL error: ' . $e->getMessage());
        }
    }

    /**
     * Extract patient ID from message
     */
    protected function getPatientId(Message $msg, string $msgType): ?int
    {
        try {
            // Extract PID from message based on message type
            $pidField = $msgType === 'TSREQ' ? 21 : 20;
            
            // This would need to be implemented based on your specific message structure
            // For now, returning a placeholder
            return 1; // Replace with actual PID extraction logic
            
        } catch (\Exception $e) {
            Log::error('MaglumiX3: Error extracting patient ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Build test string for requested tests
     */
    protected function buildTestString(int $pid): string
    {
        try {
            $dataTests = DB::table('requested')
                ->where('patient_id', $pid)
                ->get(['main_test_id']);

            $tests = "";
            $obrIndex = 1;

            foreach ($dataTests as $test) {
                if (array_key_exists($test->main_test_id, $this->hormoneMapping)) {
                    $key = $test->main_test_id;
                    $tests .= "OBR|{$obrIndex}|||{$this->hormoneMapping[$key]}^\n";
                }
                $obrIndex++;
            }

            return $tests;

        } catch (\Exception $e) {
            Log::error('MaglumiX3: Error building test string: ' . $e->getMessage());
            return "";
        }
    }

    /**
     * Generate test response message
     */
    protected function generateTestResponse(int $pid, string $tests): string
    {
        // This would generate the actual RTA response
        // For now, returning a placeholder
        return "MSH|^~\\&|MaglumiX3|Lab|LIS|Hospital|" . date('YmdHis') . "||RTA^R01|1|P|2.5.1\r" .
               "PID|1||{$pid}||Patient|||\r" .
               $tests;
    }

    /**
     * Process hormone results from OUL message
     */
    protected function processHormoneResults(Message $msg, int $pid): void
    {
        try {
            // Process and save hormone results to database
            // This would parse the OUL message and save results
            
            Log::info("MaglumiX3: Processing hormone results for patient {$pid}");
            
            // Placeholder for actual result processing
            // You would implement the specific logic to parse OUL segments
            // and save results to your database tables
            
        } catch (\Exception $e) {
            Log::error('MaglumiX3: Error processing hormone results: ' . $e->getMessage());
        }
    }
}
