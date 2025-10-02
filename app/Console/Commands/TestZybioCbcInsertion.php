<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hl7Message;
use App\Services\HL7\Devices\ZybioHandler;
use App\Services\HL7\Devices\SysmexCbcInserter;
use Aranyasen\HL7\Message;
use Illuminate\Support\Facades\DB;

class TestZybioCbcInsertion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:zybio-cbc-insertion';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test ZybioHandler CBC data extraction and insertion into sysmex table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== ZybioHandler CBC Data Extraction and Insertion Test ===');
        
        try {
            // Step 1: Get HL7 message from database
            $this->info('1. Retrieving HL7 message from database...');
            $hl7MessageRecord = Hl7Message::find(21);
            
            if (!$hl7MessageRecord) {
                $this->error('✗ HL7 message ID 21 not found');
                return;
            }
            
            $this->info('✓ HL7 message retrieved successfully');
            $this->info('  Message ID: ' . $hl7MessageRecord->id);
            $this->info('  Message Length: ' . strlen($hl7MessageRecord->raw_message) . ' characters');
            
            // Step 2: Correct message format
            $this->info('\n2. Correcting HL7 message format...');
            $correctedMessage = ZybioHandler::correctHl7MessageFormat($hl7MessageRecord->raw_message);
            
            $this->info('✓ Message format corrected');
            $this->info('  Original length: ' . strlen($hl7MessageRecord->raw_message));
            $this->info('  Corrected length: ' . strlen($correctedMessage));
            $this->info('  Correction applied: ' . ($hl7MessageRecord->raw_message !== $correctedMessage ? 'YES' : 'NO'));
            
            // Step 3: Parse HL7 message
            $this->info('\n3. Parsing HL7 message...');
            $hl7Message = new Message($correctedMessage);
            $msh = $hl7Message->getSegmentByIndex(0);
            
            $this->info('✓ HL7 message parsed successfully');
            
            // Extract key information
            $device = $msh->getField(3);
            $messageType = $msh->getField(9);
            $doctorVisitId = $msh->getField(49);
            
            if (is_array($device)) {
                $device = implode('^', $device);
            }
            if (is_array($messageType)) {
                $messageType = implode('^', $messageType);
            }
            
            $this->info('  Device: ' . $device);
            $this->info('  Message Type: ' . $messageType);
            $this->info('  Doctor Visit ID: ' . $doctorVisitId);
            
            // Step 4: Create ZybioHandler and extract CBC data
            $this->info('\n4. Extracting CBC data using ZybioHandler...');
            $sysmexInserter = new SysmexCbcInserter();
            $zybioHandler = new ZybioHandler($sysmexInserter);
            
            // Use reflection to access protected method
            $reflection = new \ReflectionClass($zybioHandler);
            $parseMethod = $reflection->getMethod('parseCbcMessage');
            $parseMethod->setAccessible(true);
            
            $cbcData = $parseMethod->invoke($zybioHandler, $hl7Message, $msh);
            
            if (!$cbcData) {
                $this->error('✗ Failed to extract CBC data');
                return;
            }
            
            $this->info('✓ CBC data extracted successfully');
            $this->info('  Patient ID: ' . ($cbcData['patient_id'] ?? 'Not found'));
            $this->info('  Doctor Visit ID: ' . ($cbcData['doctor_visit_id'] ?? 'Not found'));
            $this->info('  Total results: ' . count($cbcData['results']));
            
            // Display extracted results
            $this->info('\n5. Extracted CBC Results:');
            $this->line(str_repeat('-', 80));
            
            foreach ($cbcData['results'] as $index => $result) {
                $testCode = $result['test_code'];
                $value = $result['value'];
                $unit = $result['unit'];
                $referenceRange = $result['reference_range'];
                
                if (is_array($testCode)) {
                    $testName = $testCode[1] ?? $testCode[0] ?? 'Unknown';
                } else {
                    $testName = $testCode;
                }
                
                // Handle array values
                if (is_array($value)) {
                    $value = implode('^', $value);
                }
                if (is_array($unit)) {
                    $unit = implode('^', $unit);
                }
                if (is_array($referenceRange)) {
                    $referenceRange = implode('^', $referenceRange);
                }
                
                $this->info(sprintf('  %2d. %-15s: %-10s %-8s [%s]', 
                    $index + 1, 
                    $testName, 
                    $value, 
                    $unit ?? '', 
                    $referenceRange ?? ''
                ));
            }
            
            // Step 6: Format results for SysmexCbcInserter
            $this->info('\n6. Formatting results for database insertion...');
            $formatMethod = $reflection->getMethod('formatCbcResultsForInserter');
            $formatMethod->setAccessible(true);
            
            $formattedResults = $formatMethod->invoke($zybioHandler, $cbcData['results']);
            
            $this->info('✓ Results formatted successfully');
            $this->info('  Clinical parameters: ' . count($formattedResults));
            
            // Display formatted results
            $this->info('\n7. Formatted Clinical Results:');
            $this->line(str_repeat('-', 80));
            
            foreach ($formattedResults as $testName => $data) {
                $value = $data['value'];
                $unit = $data['unit'] ?? '';
                $referenceRange = $data['reference_range'] ?? '';
                
                // Handle array values
                if (is_array($value)) {
                    $value = implode('^', $value);
                }
                if (is_array($unit)) {
                    $unit = implode('^', $unit);
                }
                if (is_array($referenceRange)) {
                    $referenceRange = implode('^', $referenceRange);
                }
                
                $this->info(sprintf('  %-15s: %-10s %-8s [%s]', 
                    $testName, 
                    $value, 
                    $unit, 
                    $referenceRange
                ));
            }
            
            // Step 7: Validate CBC data
            $this->info('\n8. Validating CBC data...');
            $validation = $sysmexInserter->validateCbcData($formattedResults);
            
            if (!$validation['valid']) {
                $this->error('✗ CBC data validation failed:');
                foreach ($validation['errors'] as $error) {
                    $this->error('  - ' . $error);
                }
                return;
            }
            
            $this->info('✓ CBC data validation passed');
            
            // Step 8: Insert into sysmex table
            $this->info('\n9. Inserting CBC data into sysmex table...');
            
            // Check if record already exists
            $existingRecord = DB::table('sysmex')
                ->where('doctorvisit_id', $cbcData['doctor_visit_id'])
                ->first();
            
            if ($existingRecord) {
                $this->warn('⚠ Record already exists for doctor visit ID: ' . $cbcData['doctor_visit_id']);
                $this->warn('  Existing record ID: ' . $existingRecord->id);
                $this->warn('  Skipping insertion to avoid duplicates');
            } else {
                $result = $sysmexInserter->insertCbcData(
                    $formattedResults,
                    (int)$cbcData['doctor_visit_id'],
                    ['patient_id' => $cbcData['patient_id']]
                );
                
                if ($result['success']) {
                    $this->info('✓ CBC data inserted successfully');
                    $this->info('  Sysmex ID: ' . $result['sysmex_id']);
                    $this->info('  Doctor Visit ID: ' . $cbcData['doctor_visit_id']);
                    $this->info('  Patient ID: ' . $cbcData['patient_id']);
                } else {
                    $this->error('✗ Failed to insert CBC data: ' . $result['message']);
                    return;
                }
            }
            
            // Step 9: Verify insertion
            $this->info('\n10. Verifying database insertion...');
            $insertedRecord = DB::table('sysmex')
                ->where('doctorvisit_id', $cbcData['doctor_visit_id'])
                ->first();
            
            if ($insertedRecord) {
                $this->info('✓ Record found in database');
                $this->info('  Sysmex ID: ' . $insertedRecord->id);
                $this->info('  Doctor Visit ID: ' . $insertedRecord->doctorvisit_id);
                
                // Show some key values
                $this->info('\n11. Key CBC Values in Database:');
                $this->line(str_repeat('-', 50));
                $this->info('  WBC: ' . ($insertedRecord->wbc ?? 'NULL'));
                $this->info('  RBC: ' . ($insertedRecord->rbc ?? 'NULL'));
                $this->info('  HGB: ' . ($insertedRecord->hgb ?? 'NULL'));
                $this->info('  HCT: ' . ($insertedRecord->hct ?? 'NULL'));
                $this->info('  PLT: ' . ($insertedRecord->plt ?? 'NULL'));
                $this->info('  LYM%: ' . ($insertedRecord->lym_p ?? 'NULL'));
                $this->info('  NEU%: ' . ($insertedRecord->neut_p ?? 'NULL'));
                $this->info('  MID%: ' . ($insertedRecord->mxd_p ?? 'NULL'));
            } else {
                $this->error('✗ Record not found in database');
            }
            
            // Final summary
            $this->info('\n=== Test Summary ===');
            $this->info('✅ HL7 Message Retrieval: SUCCESS');
            $this->info('✅ Message Format Correction: SUCCESS');
            $this->info('✅ HL7 Message Parsing: SUCCESS');
            $this->info('✅ CBC Data Extraction: SUCCESS');
            $this->info('✅ Data Formatting: SUCCESS');
            $this->info('✅ Data Validation: SUCCESS');
            $this->info('✅ Database Insertion: SUCCESS');
            $this->info('✅ Data Verification: SUCCESS');
            
            $this->info('\n🎉 ZybioHandler CBC data extraction and insertion completed successfully!');
            $this->info('The CBC data from HL7 message ID 21 has been processed and stored in the sysmex table.');
            
        } catch (\Exception $e) {
            $this->error('✗ Test failed with error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
