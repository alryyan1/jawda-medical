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
use Illuminate\Http\Request;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;
use App\Services\FirebaseService;

class UploadLabResultToFirebase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutes timeout
    public $tries = 3; // Try 3 times before failing
    public $backoff = [30, 60, 120]; // Wait 30s, 60s, 120s between retries

    protected $patientId;
    protected $visitId;
    protected $hospitalName;
    protected $sendWhatsappMessage;
    /**
     * Create a new job instance.
     */
    public function __construct(int $patientId, int $visitId, string $hospitalName = 'Jawda Medical', bool $sendWhatsappMessage = false)
    {
        $this->patientId = $patientId;
        $this->visitId = $visitId;
        $this->hospitalName = $hospitalName;
        $this->sendWhatsappMessage = $sendWhatsappMessage;
        // Set the queue name for filtering in jobs management
        $this->onQueue('resultsUpload');
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

            // Log if patient already has result_url (we will overwrite it)
            if ($patient->result_url) {
                Log::info("Patient {$this->patientId} already has result_url, will overwrite: {$patient->result_url}");
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
            $pdfContent = $labResultReport->generate($doctorVisit, false, true);

            if (!$pdfContent) {
                Log::error("Failed to generate PDF for patient {$this->patientId}");
                $this->fail(new \Exception("Failed to generate PDF for patient {$this->patientId}"));
                return;
            }

            // Generate filename
            $filename = "result.pdf";
            $firebasePath = "results/{$this->hospitalName}/{$this->visitId}/{$filename}";

            // Delete old file if it exists
            $this->deleteOldFileFromFirebase($firebasePath);

            // Upload to Firebase using HTTP API
            $downloadUrl = $this->uploadToFirebase($pdfContent, $firebasePath);

            // Store download URL in Firestore
            $this->storeResultUrlInFirestore($this->visitId, $downloadUrl, $patient->name);

            // Update patient with result_url
            $patient->update(['result_url' => $downloadUrl]);

            // If this patient came from lab-to-lab system, log the information for frontend
            if ($patient->lab_to_lab_object_id) {
                Log::info("Lab-to-lab patient detected - frontend should update Firestore", [
                    'lab_to_lab_object_id' => $patient->lab_to_lab_object_id,
                    'pdf_url' => $downloadUrl,
                    'patient_id' => $patient->id
                ]);

                // Trigger Firestore document update via controller method
                try {
                    /** @var \App\Http\Controllers\Api\FirestoreController $firestoreController */
                    $firestoreController = app(\App\Http\Controllers\Api\FirestoreController::class);
                    $firestoreRequest = new Request([
                        'lab_to_lab_object_id' => (string) $patient->lab_to_lab_object_id,
                        'pdf_url' => (string) $downloadUrl,
                        'patient_id' => (int) $patient->id,
                    ]);
                    $firestoreResponse = $firestoreController->updateFirestoreDocument($firestoreRequest);
                    Log::info('Triggered Firestore update from job', [
                        'status' => method_exists($firestoreResponse, 'status') ? $firestoreResponse->status() : null,
                    ]);

                    // Send completion notification after successful Firestore update
                    try {
                        \App\Services\FirebaseService::sendTopicMessage(
                            $patient->lab_to_lab_id,
                            'النتائج مكتملة',
                            "تم الانتهاء من  ادخال النتائج  
                             \n 
                             $patient->name"
                        );
                        Log::info('Sent lab results completion notification');
                    } catch (\Throwable $notificationError) {
                        Log::warning('Failed to send lab results completion notification', [
                            'error' => $notificationError->getMessage(),
                        ]);
                    }
                } catch (\Throwable $e) {
                    Log::error('Failed to trigger Firestore update from job', [
                        'error' => $e->getMessage(),
                        'patient_id' => $patient->id,
                        'lab_to_lab_object_id' => $patient->lab_to_lab_object_id,
                    ]);
                }
            }
            if ($this->sendWhatsappMessage) {
                SendAuthWhatsappMessage::dispatch($this->patientId)->onQueue('notifications');
            }

            Log::info("Successfully uploaded lab result to Firebase for patient {$this->patientId}", [
                'firebase_path' => $firebasePath,
                'download_url' => $downloadUrl,
                'lab_to_lab_object_id' => $patient->lab_to_lab_object_id
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
     * Delete old file from Firebase Storage if it exists
     */
    private function deleteOldFileFromFirebase(string $firebasePath): void
    {
        // Check if Firebase service account file exists
        $serviceAccountPath = config('firebase.service_account_path');

        if (!file_exists($serviceAccountPath)) {
            throw new \Exception("Firebase service account file not found at: {$serviceAccountPath}. Please configure Firebase properly.");
        }

        // Initialize Firebase
        $firebase = $this->initializeFirebase();
        $storage = $firebase->createStorage();
        $bucketName = config('firebase.storage_bucket');
        $bucket = $storage->getBucket($bucketName);

        // Check if file exists and delete it
        $object = $bucket->object($firebasePath);
        if ($object->exists()) {
            $object->delete();
            Log::info("Deleted old file from Firebase Storage", [
                'firebase_path' => $firebasePath
            ]);
        } else {
            Log::info("No old file found to delete", [
                'firebase_path' => $firebasePath
            ]);
        }
    }

    /**
     * Upload file to Firebase Storage using Firebase Admin SDK
     */
    private function uploadToFirebase(string $fileContent, string $firebasePath): string
    {
        // Check if Firebase service account file exists
        $serviceAccountPath = config('firebase.service_account_path');

        if (!file_exists($serviceAccountPath)) {
            throw new \Exception("Firebase service account file not found at: {$serviceAccountPath}. Please configure Firebase properly.");
        }

        // Initialize Firebase
        $firebase = $this->initializeFirebase();
        $storage = $firebase->createStorage();

        // Debug: Log the bucket name being used
        $bucketName = config('firebase.storage_bucket');
        Log::info("Using Firebase bucket: " . $bucketName);

        $bucket = $storage->getBucket($bucketName); // Use specific bucket

        // Upload file to Firebase Storage
        $object = $bucket->upload($fileContent, [
            'name' => $firebasePath,
            'metadata' => [
                'contentType' => 'application/pdf',
                'cacheControl' => 'public, max-age=31536000',
            ]
        ]);
        $object->acl()->add('allUsers', 'READER');
        // Get the download URL
        $downloadUrl = $object->signedUrl(new \DateTime('+1 year'));

        // Public URL
        // $publicUrl = self::generatePublicUrl($firebasePath);

       

        // return $publicUrl;
        return $downloadUrl;
    }


    /**
     * Generate public URL for Firebase Storage object
     *
     * @param string $firebasePath
     * @return string
     */
    public static function generatePublicUrl(string $firebasePath): string
    {
        // Check if Firebase service account file exists
        $serviceAccountPath = config('firebase.service_account_path');

        if (!file_exists($serviceAccountPath)) {
            throw new \Exception("Firebase service account file not found at: {$serviceAccountPath}. Please configure Firebase properly.");
        }

        // Initialize Firebase
        $factory = (new Factory)
            ->withServiceAccount($serviceAccountPath)
            ->withProjectId(config('firebase.project_id'));

        $storage = $factory->createStorage();
        $bucketName = config('firebase.storage_bucket');
        $bucket = $storage->getBucket($bucketName);

        return "https://storage.googleapis.com/" . $bucket->name() . "/" . $firebasePath;
    }

    /**
     * Initialize Firebase Admin SDK
     */
    private function initializeFirebase()
    {
        $serviceAccountPath = config('firebase.service_account_path');

        $factory = (new Factory)
            ->withServiceAccount($serviceAccountPath)
            ->withProjectId(config('firebase.project_id'));

        return $factory;
    }

    /**
     * Store the download URL in Firestore at /altamayoz_branch_2 collection
     * Document ID is the doctor visit ID, with result_url and patient_name properties
     * Creates the document if it doesn't exist, updates it if it does
     * Uses REST API approach to avoid Firestore SDK dependency
     *
     * @param int $doctorVisitId
     * @param string $resultUrl
     * @param string $patientName
     * @return void
     */
    private function storeResultUrlInFirestore(int $doctorVisitId, string $resultUrl, string $patientName): void
    {
        try {
            $projectId = config('firebase.project_id');
            if (!$projectId) {
                Log::warning('Firebase project ID not configured for Firestore update');
                return;
            }

            $accessToken = FirebaseService::getAccessToken();
            if (!$accessToken) {
                Log::warning('FCM access token unavailable for Firestore update');
                return;
            }

            $collection = 'altamayoz_branch_2';
            $documentId = (string) $doctorVisitId;
            $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$documentId}";

            // Try to get current document first to merge with existing fields
            $getResponse = Http::withToken($accessToken)->get($url);
            
            $fields = [
                'result_url' => ['stringValue' => $resultUrl],
                'patient_name' => ['stringValue' => $patientName]
            ];

            if ($getResponse->successful()) {
                // Document exists - merge with existing fields
                $currentDoc = $getResponse->json();
                $currentFields = $currentDoc['fields'] ?? [];
                
                // Merge new fields with existing fields (preserve other fields)
                $currentFields['result_url'] = $fields['result_url'];
                $currentFields['patient_name'] = $fields['patient_name'];
                $fields = $currentFields;

                // Update existing document using PATCH (merge)
                $updatePayload = ['fields' => $fields];
                $response = Http::withToken($accessToken)->patch($url, $updatePayload);
                
                if ($response->successful()) {
                    Log::info("Updated result URL in Firestore", [
                        'collection' => $collection,
                        'document_id' => $documentId,
                        'result_url' => $resultUrl,
                        'patient_name' => $patientName
                    ]);
                } else {
                    Log::warning("Failed to update Firestore document", [
                        'collection' => $collection,
                        'document_id' => $documentId,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                }
            } else if ($getResponse->status() === 404) {
                // Document doesn't exist - create it using PATCH with updateMask
                // PATCH with updateMask allows creating the document if it doesn't exist
                $createPayload = [
                    'fields' => $fields
                ];
                $response = Http::withToken($accessToken)->patch($url . '?updateMask.fieldPaths=result_url&updateMask.fieldPaths=patient_name', $createPayload);
                
                if ($response->successful()) {
                    Log::info("Created result URL in Firestore", [
                        'collection' => $collection,
                        'document_id' => $documentId,
                        'result_url' => $resultUrl,
                        'patient_name' => $patientName
                    ]);
                } else {
                    // If PATCH fails, try POST to collection (but this won't set specific ID)
                    // Actually, let's try a different approach - use the batch write API
                    // For now, log the error and try alternative
                    Log::warning("Failed to create Firestore document with PATCH, trying alternative", [
                        'collection' => $collection,
                        'document_id' => $documentId,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                    
                    // Alternative: Use POST to create document (Firestore will auto-generate ID)
                    // But we need specific ID, so let's use a workaround: POST then update
                    // Actually, the simplest is to just use PATCH without updateMask for creation
                    $simpleCreatePayload = ['fields' => $fields];
                    $altResponse = Http::withToken($accessToken)->patch($url, $simpleCreatePayload);
                    
                    if ($altResponse->successful()) {
                        Log::info("Created result URL in Firestore (alternative method)", [
                            'collection' => $collection,
                            'document_id' => $documentId,
                            'result_url' => $resultUrl,
                            'patient_name' => $patientName
                        ]);
                    } else {
                        Log::error("Failed to create Firestore document with all methods", [
                            'collection' => $collection,
                            'document_id' => $documentId,
                            'status' => $altResponse->status(),
                            'body' => $altResponse->body()
                        ]);
                    }
                }
            } else {
                // Other error - log and try to create/update anyway
                Log::warning("Failed to get Firestore document, attempting to create/update", [
                    'collection' => $collection,
                    'document_id' => $documentId,
                    'status' => $getResponse->status(),
                    'body' => $getResponse->body()
                ]);
                
                // Try PATCH which should work for both create and update
                $upsertPayload = ['fields' => $fields];
                $response = Http::withToken($accessToken)->patch($url, $upsertPayload);
                
                if ($response->successful()) {
                    Log::info("Created/updated result URL in Firestore (after get failed)", [
                        'collection' => $collection,
                        'document_id' => $documentId,
                        'result_url' => $resultUrl,
                        'patient_name' => $patientName
                    ]);
                } else {
                    Log::warning("Failed to create/update Firestore document", [
                        'collection' => $collection,
                        'document_id' => $documentId,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                }
            }

        } catch (\Exception $e) {
            Log::error("Failed to store result URL in Firestore", [
                'doctor_visit_id' => $doctorVisitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // Don't throw exception - allow job to continue even if Firestore update fails
        }
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