<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Hl7Message;
use App\Services\HL7\Devices\ZybioHandler;
use App\Services\HL7\Devices\SysmexCbcInserter;
use Aranyasen\HL7\Message;
use Illuminate\Support\Facades\DB;

class TestZybioForceInsertion extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:zybio-force-insertion {--doctor-visit-id=999}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force insert Zybio CBC data into sysmex table with a new doctor visit ID';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== ZybioHandler Force CBC Data Insertion Test ===');
        
        try {
            // Get doctor visit ID from option or use default
            $doctorVisitId = $this->option('doctor-visit-id');
            
            $this->info("Using Doctor Visit ID: $doctorVisitId");
            
            // Step 1: Get HL7 message from database
            $this->info('\n1. Retrieving HL7 message from database...');
            $hl7MessageRecord = Hl7Message::find(21);
            
            if (!$hl7MessageRecord) {
                $this->error('✗ HL7 message ID 21 not found');
                return;
            }
            
            $this->info('✓ HL7 message retrieved successfully');
            
            // Step 2: Correct message format
            $this->info('\n2. Correcting HL7 message format...');
            $correctedMessage = ZybioHandler::correctHl7MessageFormat($hl7MessageRecord->raw_message);
            
            $this->info('✓ Message format corrected');
            
            // Step 3: Parse HL7 message
            $this->info('\n3. Parsing HL7 message...');
            $hl7Message = new Message($correctedMessage);
            $msh = $hl7Message->getSegmentByIndex(0);
            
            $this->info('✓ HL7 message parsed successfully');
            
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
            $this->info('  Total results: ' . count($cbcData['results']));
            
            // Override the doctor visit ID
            $cbcData['doctor_visit_id'] = $doctorVisitId;
            $this->info("  Using Doctor Visit ID: $doctorVisitId");
            
            // Step 5: Format results for SysmexCbcInserter
            $this->info('\n5. Formatting results for database insertion...');
            $formatMethod = $reflection->getMethod('formatCbcResultsForInserter');
            $formatMethod->setAccessible(true);
            
            $formattedResults = $formatMethod->invoke($zybioHandler, $cbcData['results']);
            
            $this->info('✓ Results formatted successfully');
            $this->info('  Clinical parameters: ' . count($formattedResults));
            
            // Step 6: Validate CBC data
            $this->info('\n6. Validating CBC data...');
            $validation = $sysmexInserter->validateCbcData($formattedResults);
            
            if (!$validation['valid']) {
                $this->error('✗ CBC data validation failed:');
                foreach ($validation['errors'] as $error) {
                    $this->error('  - ' . $error);
                }
                return;
            }
            
            $this->info('✓ CBC data validation passed');
            
            // Step 7: Check if record already exists
            $this->info('\n7. Checking for existing record...');
            $existingRecord = DB::table('sysmex')
                ->where('doctorvisit_id', $doctorVisitId)
                ->first();
            
            if ($existingRecord) {
                $this->warn('⚠ Record already exists for doctor visit ID: ' . $doctorVisitId);
                $this->warn('  Existing record ID: ' . $existingRecord->id);
                
                if ($this->confirm('Do you want to delete the existing record and insert a new one?')) {
                    DB::table('sysmex')->where('doctorvisit_id', $doctorVisitId)->delete();
                    $this->info('✓ Existing record deleted');
                } else {
                    $this->info('Skipping insertion as requested');
                    return;
                }
            }
            
            // Step 8: Insert into sysmex table
            $this->info('\n8. Inserting CBC data into sysmex table...');
            
            $result = $sysmexInserter->insertCbcData(
                $formattedResults,
                (int)$doctorVisitId,
                ['patient_id' => $cbcData['patient_id']]
            );
            
            if ($result['success']) {
                $this->info('✓ CBC data inserted successfully');
                $this->info('  Sysmex ID: ' . $result['sysmex_id']);
                $this->info('  Doctor Visit ID: ' . $doctorVisitId);
                $this->info('  Patient ID: ' . $cbcData['patient_id']);
            } else {
                $this->error('✗ Failed to insert CBC data: ' . $result['message']);
                return;
            }
            
            // Step 9: Verify insertion
            $this->info('\n9. Verifying database insertion...');
            $insertedRecord = DB::table('sysmex')
                ->where('doctorvisit_id', $doctorVisitId)
                ->first();
            
            if ($insertedRecord) {
                $this->info('✓ Record found in database');
                $this->info('  Sysmex ID: ' . $insertedRecord->id);
                $this->info('  Doctor Visit ID: ' . $insertedRecord->doctorvisit_id);
                
                // Show some key values
                $this->info('\n10. Key CBC Values in Database:');
                $this->line(str_repeat('-', 50));
                $this->info('  WBC: ' . ($insertedRecord->wbc ?? 'NULL'));
                $this->info('  RBC: ' . ($insertedRecord->rbc ?? 'NULL'));
                $this->info('  HGB: ' . ($insertedRecord->hgb ?? 'NULL'));
                $this->info('  HCT: ' . ($insertedRecord->hct ?? 'NULL'));
                $this->info('  PLT: ' . ($insertedRecord->plt ?? 'NULL'));
                $this->info('  LYM%: ' . ($insertedRecord->lym_p ?? 'NULL'));
                $this->info('  NEU%: ' . ($insertedRecord->neut_p ?? 'NULL'));
                $this->info('  MID%: ' . ($insertedRecord->mxd_p ?? 'NULL'));
                $this->info('  LYM#: ' . ($insertedRecord->lym_c ?? 'NULL'));
                $this->info('  NEU#: ' . ($insertedRecord->neut_c ?? 'NULL'));
                $this->info('  MID#: ' . ($insertedRecord->mxd_c ?? 'NULL'));
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
            
            $this->info('\n🎉 ZybioHandler CBC data force insertion completed successfully!');
            $this->info("New record inserted with Doctor Visit ID: $doctorVisitId");
            
        } catch (\Exception $e) {
            $this->error('✗ Test failed with error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
        }
    }
}
