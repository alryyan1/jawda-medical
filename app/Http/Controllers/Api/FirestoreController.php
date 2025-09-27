<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;
use Kreait\Firebase\ServiceAccount;

class FirestoreController extends Controller
{
    /**
     * Update Firestore document directly from backend using REST API
     * This endpoint handles the complete Firestore update process
     * Uses updateMask to merge new fields without removing existing ones
     */
    public function updateFirestoreDocument(Request $request)
    {
        try {
            $validated = $request->validate([
                'lab_to_lab_object_id' => 'required|string',
                'pdf_url' => 'required|string|url',
                'patient_id' => 'required|integer',
            ]);

            $labToLabObjectId = $validated['lab_to_lab_object_id'];
            $pdfUrl = $validated['pdf_url'];
            $patientId = $validated['patient_id'];

            // Get Firebase project ID from config
            $projectId = config('firebase.project_id');
            if (!$projectId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase project ID not configured'
                ], 500);
            }

            // Construct the Firestore REST API URL
            $firestorePath = "labToLap/global/patients/{$labToLabObjectId}";
            $firestoreUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$firestorePath}";

            // Create the update payload
            $updatePayload = [
                'fields' => [
                    'pdf_url' => ['stringValue' => $pdfUrl],
                    'result_updated_at' => ['timestampValue' => date('c')],
                    'lab_to_lab_object_id' => ['stringValue' => $labToLabObjectId]
                ]
            ];

            // Get access token using Firebase Admin SDK
            $serviceAccountPath = config('firebase.service_account_path');
            if (!file_exists($serviceAccountPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase service account file not found'
                ], 500);
            }

            // Use Firebase Admin SDK to get access token
            $factory = (new Factory)->withServiceAccount($serviceAccountPath);
            $auth = $factory->createAuth();
            
            // Create a custom token
            $customToken = $auth->createCustomToken('firebase-service-account');
            
            // Exchange custom token for access token
            $accessToken = $this->exchangeCustomTokenForAccessToken($customToken->toString());
            
            if (!$accessToken) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to get access token'
                ], 500);
            }

            // Make HTTP request to Firestore REST API with merge option
            // Using updateMask as query parameter to only update specific fields, preserving existing ones
            $updateMaskQuery = 'updateMask.fieldPaths=pdf_url&updateMask.fieldPaths=result_updated_at&updateMask.fieldPaths=lab_to_lab_object_id';
            $firestoreUrlWithMask = $firestoreUrl . '?' . $updateMaskQuery;
            
            Log::info("Updating Firestore document with merge", [
                'firestore_url' => $firestoreUrlWithMask,
                'update_mask_fields' => ['pdf_url', 'result_updated_at', 'lab_to_lab_object_id'],
                'fields_to_update' => array_keys($updatePayload['fields'])
            ]);
            
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->patch($firestoreUrlWithMask, $updatePayload);

            if ($response->successful()) {
                Log::info("Firestore document updated successfully", [
                    'patient_id' => $patientId,
                    'lab_to_lab_object_id' => $labToLabObjectId,
                    'pdf_url' => $pdfUrl
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Firestore document updated successfully'
                ]);
            } else {
                Log::error("Firestore update failed", [
                    'status' => $response->status(),
                    'response' => $response->body(),
                    'firestore_url' => $firestoreUrlWithMask,
                    'update_payload' => $updatePayload
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update Firestore document',
                    'error' => 'HTTP ' . $response->status() . ': ' . $response->body(),
                    'debug_info' => [
                        'firestore_url' => $firestoreUrlWithMask,
                        'fields_being_updated' => array_keys($updatePayload['fields'])
                    ]
                ], 500);
            }

        } catch (\Exception $e) {
            Log::error("Failed to update Firestore document: " . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update Firestore document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exchange custom token for access token
     */
    private function exchangeCustomTokenForAccessToken($customToken)
    {
        try {
            $response = Http::post('https://identitytoolkit.googleapis.com/v1/accounts:signInWithCustomToken?key=' . config('firebase.api_key'), [
                'token' => $customToken,
                'returnSecureToken' => true
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['idToken'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error("Failed to exchange custom token: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update patient PDF URL in Firestore
     * This endpoint is called by the frontend to update Firestore documents
     */
    public function updatePatientPdf(Request $request)
    {
        try {
            $validated = $request->validate([
                'lab_to_lab_object_id' => 'required|string',
                'pdf_url' => 'required|string|url',
            ]);

            $labToLabObjectId = $validated['lab_to_lab_object_id'];
            $pdfUrl = $validated['pdf_url'];

            // Get Firebase project ID from config
            $projectId = config('firebase.project_id');
            if (!$projectId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Firebase project ID not configured'
                ], 500);
            }

            // Log the request for monitoring
            Log::info("Frontend Firestore update request received", [
                'lab_to_lab_object_id' => $labToLabObjectId,
                'pdf_url' => $pdfUrl,
                'project_id' => $projectId,
                'firestore_path' => "labToLap/global/patients/{$labToLabObjectId}"
            ]);

            // Return the data that the frontend needs to make the Firestore update
            return response()->json([
                'success' => true,
                'message' => 'Firestore update data prepared',
                'data' => [
                    'lab_to_lab_object_id' => $labToLabObjectId,
                    'pdf_url' => $pdfUrl,
                    'project_id' => $projectId,
                    'firestore_path' => "labToLap/global/patients/{$labToLabObjectId}",
                    'firestore_url' => "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/labToLap/global/patients/{$labToLabObjectId}",
                    'update_payload' => [
                        'fields' => [
                            'pdf_url' => ['stringValue' => $pdfUrl],
                            'result_updated_at' => ['timestampValue' => date('c')]
                        ]
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to process Firestore update request: " . $e->getMessage(), [
                'request_data' => $request->all(),
                'exception' => $e
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process Firestore update request',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}