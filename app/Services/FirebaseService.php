<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    /**
     * Send FCM topic notification using Firebase HTTP v1 API
     */
    public static function sendTopicMessage(string $topic, string $title, string $body): bool
    {
        if ($topic === '') {
            return false;
        }
        
        $projectId = config('firebase.project_id');
        if (!$projectId) {
            Log::warning('Firebase project ID not configured');
            return false;
        }
        
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            Log::warning('FCM access token unavailable');
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $payload = [
            'message' => [
                'topic' => $topic,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];

        try {
            $response = Http::withToken($accessToken)
                ->post($url, $payload);
                
            if (!$response->successful()) {
                Log::warning('FCM send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
            
            Log::info('FCM message sent successfully', [
                'topic' => $topic,
                'title' => $title
            ]);
            
            return true;
        } catch (\Throwable $e) {
            Log::warning('FCM send exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send FCM notification to specific device tokens
     */
    public static function sendToDevices(array $tokens, string $title, string $body, array $data = []): bool
    {
        if (empty($tokens)) {
            return false;
        }
        
        $projectId = config('firebase.project_id');
        if (!$projectId) {
            Log::warning('Firebase project ID not configured');
            return false;
        }
        
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            Log::warning('FCM access token unavailable');
            return false;
        }

        $url = "https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send";
        $payload = [
            'message' => [
                'token' => $tokens[0], // FCM v1 API sends to one token at a time
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
            ],
        ];

        // Add data payload if provided
        if (!empty($data)) {
            $payload['message']['data'] = $data;
        }

        try {
            $response = Http::withToken($accessToken)
                ->post($url, $payload);
                
            if (!$response->successful()) {
                Log::warning('FCM device send failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }
            
            Log::info('FCM device message sent successfully', [
                'token' => $tokens[0],
                'title' => $title
            ]);
            
            return true;
        } catch (\Throwable $e) {
            Log::warning('FCM device send exception', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send FCM notification to multiple devices (batch)
     */
    public static function sendToMultipleDevices(array $tokens, string $title, string $body, array $data = []): array
    {
        $results = [];
        
        foreach ($tokens as $token) {
            $results[$token] = self::sendToDevices([$token], $title, $body, $data);
        }
        
        return $results;
    }

    /**
     * Obtain a Firebase access token using the service account for FCM HTTP v1 API
     */
    public static function getAccessToken(): ?string
    {
        try {
            $serviceAccountPath = config('firebase.service_account_path');
            if (!file_exists($serviceAccountPath)) {
                Log::warning('Firebase service account file not found', ['path' => $serviceAccountPath]);
                return null;
            }
            
            $serviceAccount = json_decode(file_get_contents($serviceAccountPath), true);
            if (!$serviceAccount) {
                Log::warning('Failed to parse Firebase service account JSON');
                return null;
            }
            
            // Create JWT for OAuth 2 access token request
            $now = time();
            $header = [
                'alg' => 'RS256',
                'typ' => 'JWT'
            ];
            
            $payload = [
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging https://www.googleapis.com/auth/cloud-platform https://www.googleapis.com/auth/datastore',
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600
            ];
            
            $headerEncoded = self::base64UrlEncode(json_encode($header));
            $payloadEncoded = self::base64UrlEncode(json_encode($payload));
            $signature = self::createSignature($headerEncoded . '.' . $payloadEncoded, $serviceAccount['private_key']);
            $jwt = $headerEncoded . '.' . $payloadEncoded . '.' . $signature;
            
            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                return $data['access_token'] ?? null;
            } else {
                Log::warning('Failed to get OAuth 2 access token', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to get Firebase access token', ['error' => $e->getMessage()]);
        }
        return null;
    }

    /**
     * Validate Firebase configuration
     */
    public static function validateConfig(): array
    {
        $issues = [];
        
        if (!config('firebase.project_id')) {
            $issues[] = 'Firebase project ID not configured';
        }
        
        if (!config('firebase.service_account_path')) {
            $issues[] = 'Firebase service account path not configured';
        } elseif (!file_exists(config('firebase.service_account_path'))) {
            $issues[] = 'Firebase service account file not found';
        }
        
        return $issues;
    }

    /**
     * Test Firebase connection
     */
    public static function testConnection(): bool
    {
        $configIssues = self::validateConfig();
        if (!empty($configIssues)) {
            Log::warning('Firebase configuration issues', ['issues' => $configIssues]);
            return false;
        }
        
        $accessToken = self::getAccessToken();
        return $accessToken !== null;
    }

    /**
     * Update Firestore document field
     */
    public static function updateFirestoreDocument(string $collection, string $documentId, array $fields): bool
    {
        try {
            $projectId = config('firebase.project_id');
            if (!$projectId) {
                Log::warning('Firebase project ID not configured');
                return false;
            }

            $accessToken = self::getAccessToken();
            if (!$accessToken) {
                Log::warning('FCM access token unavailable for Firestore update');
                return false;
            }

            $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$documentId}";
            
            // Get current document first
            $getResponse = Http::withToken($accessToken)->get($url);
            
            if (!$getResponse->successful()) {
                Log::warning('Failed to get Firestore document', [
                    'collection' => $collection,
                    'documentId' => $documentId,
                    'status' => $getResponse->status(),
                    'body' => $getResponse->body()
                ]);
                return false;
            }

            $currentDoc = $getResponse->json();
            $currentFields = $currentDoc['fields'] ?? [];

            // Merge new fields with existing fields
            foreach ($fields as $key => $value) {
                $currentFields[$key] = self::formatFirestoreValue($value);
            }

            // Update document
            $updatePayload = [
                'fields' => $currentFields
            ];

            $updateResponse = Http::withToken($accessToken)
                ->patch($url, $updatePayload);

            if (!$updateResponse->successful()) {
                Log::warning('Failed to update Firestore document', [
                    'collection' => $collection,
                    'documentId' => $documentId,
                    'status' => $updateResponse->status(),
                    'body' => $updateResponse->body()
                ]);
                return false;
            }

            Log::info('Firestore document updated successfully', [
                'collection' => $collection,
                'documentId' => $documentId,
                'fields' => array_keys($fields)
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Firestore update exception', [
                'error' => $e->getMessage(),
                'collection' => $collection,
                'documentId' => $documentId
            ]);
            return false;
        }
    }

    /**
     * Format value for Firestore
     */
    private static function formatFirestoreValue($value)
    {
        if (is_bool($value)) {
            return ['booleanValue' => $value];
        } elseif (is_int($value)) {
            return ['integerValue' => (string)$value];
        } elseif (is_float($value)) {
            return ['doubleValue' => $value];
        } elseif (is_string($value)) {
            return ['stringValue' => $value];
        } elseif (is_array($value)) {
            return ['arrayValue' => ['values' => array_map([self::class, 'formatFirestoreValue'], $value)]];
        } elseif (is_null($value)) {
            return ['nullValue' => null];
        } else {
            return ['stringValue' => (string)$value];
        }
    }

    /**
     * Base64 URL encode
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Create RSA signature
     */
    private static function createSignature(string $data, string $privateKey): string
    {
        $key = openssl_pkey_get_private($privateKey);
        if (!$key) {
            throw new \Exception('Invalid private key');
        }
        
        $signature = '';
        if (!openssl_sign($data, $signature, $key, OPENSSL_ALGO_SHA256)) {
            throw new \Exception('Failed to create signature');
        }
        
        openssl_pkey_free($key);
        return self::base64UrlEncode($signature);
    }
}
