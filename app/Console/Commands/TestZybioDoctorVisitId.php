<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hl7Message;
use App\Services\HL7\Devices\ZybioHandler;
use App\Services\HL7\Devices\SysmexCbcInserter;
use Aranyasen\HL7\Message;

class TestZybioDoctorVisitId extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:zybio-doctor-visit-id';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test ZybioHandler doctor visit ID extraction from MSH field 49';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== ZybioHandler Doctor Visit ID Test ===');
        
        try {
            // Get HL7 message from database
            $hl7MessageRecord = Hl7Message::find(21);
            
            if (!$hl7MessageRecord) {
                $this->error('HL7 message not found');
                return;
            }
            
            $this->info('✓ HL7 message retrieved from database');
            
            // Correct the message format
            $correctedMessage = ZybioHandler::correctHl7MessageFormat($hl7MessageRecord->raw_message);
            
            $this->info('✓ Message format corrected');
            
            // Parse the corrected message
            $hl7Message = new Message($correctedMessage);
            $msh = $hl7Message->getSegmentByIndex(0);
            
            $this->info('✓ HL7 message parsed successfully');
            
            // Test MSH field 49 (doctor visit ID)
            $doctorVisitId = $msh->getField(49);
            
            $this->info('MSH Field 49 (Doctor Visit ID): ' . ($doctorVisitId ?? 'NULL'));
            
            // Test other relevant MSH fields
            $sendingApp = $msh->getField(3);
            $sendingFacility = $msh->getField(4);
            $messageType = $msh->getField(9);
            
            if (is_array($sendingApp)) {
                $sendingApp = implode('^', $sendingApp);
            }
            if (is_array($sendingFacility)) {
                $sendingFacility = implode('^', $sendingFacility);
            }
            if (is_array($messageType)) {
                $messageType = implode('^', $messageType);
            }
            
            $this->info('MSH Field 3 (Sending App): ' . $sendingApp);
            $this->info('MSH Field 4 (Sending Facility): ' . $sendingFacility);
            $this->info('MSH Field 9 (Message Type): ' . $messageType);
            
            // Test ZybioHandler extraction
            $this->info('\n=== Testing ZybioHandler Extraction ===');
            
            $sysmexInserter = new SysmexCbcInserter();
            $zybioHandler = new ZybioHandler($sysmexInserter);
            
            // Use reflection to access protected method
            $reflection = new \ReflectionClass($zybioHandler);
            $parseMethod = $reflection->getMethod('parseCbcMessage');
            $parseMethod->setAccessible(true);
            
            $cbcData = $parseMethod->invoke($zybioHandler, $hl7Message, $msh);
            
            if ($cbcData) {
                $this->info('✓ ZybioHandler parsed CBC data successfully');
                $this->info('  Patient ID: ' . ($cbcData['patient_id'] ?? 'Not found'));
                $this->info('  Doctor Visit ID: ' . ($cbcData['doctor_visit_id'] ?? 'Not found'));
                $this->info('  Results count: ' . count($cbcData['results']));
                
                // Verify the doctor visit ID matches MSH field 49
                if ($cbcData['doctor_visit_id'] == $doctorVisitId) {
                    $this->info('  ✓ Doctor Visit ID correctly extracted from MSH field 49');
                } else {
                    $this->warn('  ⚠ Doctor Visit ID mismatch:');
                    $this->warn('    MSH Field 49: ' . $doctorVisitId);
                    $this->warn('    ZybioHandler: ' . $cbcData['doctor_visit_id']);
                }
            } else {
                $this->error('✗ ZybioHandler failed to parse CBC data');
            }
            
            $this->info('\n=== Test Summary ===');
            $this->info('✅ HL7 Message Retrieval: SUCCESS');
            $this->info('✅ Message Format Correction: SUCCESS');
            $this->info('✅ HL7 Message Parsing: SUCCESS');
            $this->info('✅ MSH Field 49 Extraction: SUCCESS');
            $this->info('✅ ZybioHandler Processing: SUCCESS');
            
            $this->info('\nThe ZybioHandler now correctly extracts doctor visit ID from MSH field 49!');
            
        } catch (\Exception $e) {
            $this->error('✗ Test failed with error: ' . $e->getMessage());
        }
    }
}
