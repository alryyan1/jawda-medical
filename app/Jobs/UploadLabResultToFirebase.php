<?php

namespace App\Jobs;

use App\Models\Patient;
use App\Services\Pdf\LabResultReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

class UploadLabResultToFirebase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3; // Try 3 times before failing
    public $backoff = [30, 60, 120]; // Wait 30s, 60s, 120s between retries

    protected $patientId;
    protected $visitId;
    protected $hospitalName;

    /**
     * Create a new job instance.
     */
    public function __construct(int $patientId, int $visitId, string $hospitalName = 'Jawda Medical')
    {
        $this->patientId = $patientId;
        $this->visitId = $visitId;
        $this->hospitalName = $hospitalName;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            Log::info("Starting Firebase upload job for patient {$this->patientId}, visit {$this->visitId}");

            // Get patient data
            $patient = Patient::with(['doctorVisit.labRequests.mainTest'])->find($this->patientId);
            
            if (!$patient) {
                Log::error("Patient not found: {$this->patientId}");
                $this->fail(new \Exception("Patient not found: {$this->patientId}"));
                return;
            }

            // Check if patient already has result_url
            if ($patient->result_url) {
                Log::info("Patient {$this->patientId} already has result_url: {$patient->result_url}");
                return; // Job completed successfully
            }

            // Get doctor visit
            $doctorVisit = $patient->doctorVisit;
            if (!$doctorVisit) {
                Log::error("Doctor visit not found for patient {$this->patientId}");
                $this->fail(new \Exception("Doctor visit not found for patient {$this->patientId}"));
                return;
            }

            // Generate PDF content
            $labResultReport = new LabResultReport();
            $pdfContent = $labResultReport->generate($doctorVisit);

            if (!$pdfContent) {
                Log::error("Failed to generate PDF for patient {$this->patientId}");
                $this->fail(new \Exception("Failed to generate PDF for patient {$this->patientId}"));
                return;
            }

            // Generate filename
            $sanitizedPatientName = preg_replace('/[^A-Za-z0-9-_]/', '_', $patient->name);
            $filename = "lab_result_{$this->visitId}_{$sanitizedPatientName}_" . now()->format('Y-m-d') . ".pdf";
            $firebasePath = "results/{$this->hospitalName}/{$this->visitId}/{$filename}";

            // Upload to Firebase using HTTP API
            $downloadUrl = $this->uploadToFirebase($pdfContent, $firebasePath);

            if (!$downloadUrl) {
                throw new \Exception("Failed to upload to Firebase");
            }

            // Update patient with result_url
            $patient->update(['result_url' => $downloadUrl]);

            Log::info("Successfully uploaded lab result to Firebase for patient {$this->patientId}", [
                'firebase_path' => $firebasePath,
                'download_url' => $downloadUrl
            ]);

        } catch (\Exception $e) {
            Log::error("Firebase upload job failed for patient {$this->patientId}: " . $e->getMessage(), [
                'exception' => $e,
                'patient_id' => $this->patientId,
                'visit_id' => $this->visitId
            ]);
            
            // Re-throw to trigger retry mechanism
            throw $e;
        }
    }

    /**
     * Upload file to Firebase Storage using Firebase Admin SDK
     */
    private function uploadToFirebase(string $fileContent, string $firebasePath): ?string
    {
        try {
            // Initialize Firebase
            $firebase = $this->initializeFirebase();
            $storage = $firebase->createStorage();
            $bucket = $storage->getBucket();
            
            // Upload file to Firebase Storage
            $object = $bucket->upload($fileContent, [
                'name' => $firebasePath,
                'metadata' => [
                    'contentType' => 'application/pdf',
                    'cacheControl' => 'public, max-age=31536000',
                ]
            ]);
            
            // Get the download URL
            $downloadUrl = $object->signedUrl(new \DateTime('+1 year'));
            
            Log::info("Successfully uploaded to Firebase Storage", [
                'firebase_path' => $firebasePath,
                'download_url' => $downloadUrl
            ]);
            
            return $downloadUrl;
            
        } catch (\Exception $e) {
            Log::error("Failed to upload to Firebase: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Initialize Firebase Admin SDK
     */
    private function initializeFirebase()
    {
        $serviceAccountPath = config('firebase.service_account_path');
        
        if (!file_exists($serviceAccountPath)) {
            throw new \Exception("Firebase service account file not found at: {$serviceAccountPath}");
        }
        
        $factory = (new Factory)
            ->withServiceAccount($serviceAccountPath)
            ->withProjectId(config('firebase.project_id'));
            
        return $factory;
    }
    

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("Firebase upload job permanently failed for patient {$this->patientId}", [
            'exception' => $exception,
            'patient_id' => $this->patientId,
            'visit_id' => $this->visitId,
            'attempts' => $this->attempts()
        ]);

        // You could send a notification to admin or update patient status here
        // For example, mark patient as needing manual upload
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'firebase-upload',
            'patient:' . $this->patientId,
            'visit:' . $this->visitId,
        ];
    }
}