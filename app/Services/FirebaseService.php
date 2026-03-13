<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Factory;

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
     * Get Firebase project ID (from config or service account file).
     */
    public static function getProjectId(): ?string
    {
        $projectId = config('firebase.project_id');
        if ($projectId) {
            return $projectId;
        }
        $path = config('firebase.service_account_path');
        if (!$path || !file_exists($path)) {
            return null;
        }
        $data = json_decode(file_get_contents($path), true);
        return $data['project_id'] ?? null;
    }

    /**
     * List root-level Firestore collection IDs.
     * Returns array of collection IDs or null on failure.
     */
    public static function listRootCollectionIds(): ?array
    {
        $projectId = self::getProjectId();
        if (!$projectId) {
            Log::warning('Firebase project ID not available for listCollectionIds');
            return null;
        }
        $accessToken = self::getAccessToken();
        if (!$accessToken) {
            Log::warning('FCM access token unavailable for listCollectionIds');
            return null;
        }
        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents:listCollectionIds";
        try {
            $response = Http::withToken($accessToken)
                ->asJson()
                ->post($url, []);
            if (!$response->successful()) {
                Log::warning('Firestore listCollectionIds failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }
            $data = $response->json();
            return $data['collectionIds'] ?? [];
        } catch (\Throwable $e) {
            Log::warning('Firestore listCollectionIds exception', ['error' => $e->getMessage()]);
            return null;
        }
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
     * Create or update a Firestore document by full path (supports nested paths).
     * Document path format: pharmacies/one_care/admissions/{admissionId}
     *
     * @param  string  $documentPath  Full document path (e.g. pharmacies/one_care/admissions/123)
     * @param  array  $fields  Key-value pairs to write (values are formatted for Firestore)
     * @return bool
     */
    public static function createOrUpdateFirestoreDocumentByPath(string $documentPath, array $fields): bool
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

            $formattedFields = [];
            foreach ($fields as $key => $value) {
                $formattedFields[$key] = self::formatFirestoreValue($value);
            }

            $payload = ['fields' => $formattedFields];
            $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$documentPath}";

            $getResponse = Http::withToken($accessToken)->get($url);

            if ($getResponse->successful()) {
                $currentDoc = $getResponse->json();
                $currentFields = $currentDoc['fields'] ?? [];
                foreach ($formattedFields as $key => $formatted) {
                    $currentFields[$key] = $formatted;
                }
                $updatePayload = ['fields' => $currentFields];
                $response = Http::withToken($accessToken)->patch($url, $updatePayload);
            } elseif ($getResponse->status() === 404) {
                $pathParts = explode('/', $documentPath);
                $documentId = array_pop($pathParts);
                $parentPath = implode('/', $pathParts);
                $createUrl = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$parentPath}?documentId={$documentId}";
                $response = Http::withToken($accessToken)->post($createUrl, $payload);
            } else {
                Log::warning('Failed to get Firestore document', [
                    'documentPath' => $documentPath,
                    'status' => $getResponse->status(),
                ]);
                return false;
            }

            if (!$response->successful()) {
                Log::warning('Failed to create/update Firestore document by path', [
                    'documentPath' => $documentPath,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return false;
            }

            Log::info('Firestore document created/updated by path', [
                'documentPath' => $documentPath,
                'fields' => array_keys($fields),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Firestore create/update by path exception', [
                'error' => $e->getMessage(),
                'documentPath' => $documentPath,
            ]);
            return false;
        }
    }

    /**
     * Upload PDF content to Firebase Storage and return the public download URL.
     *
     * @param  string  $content  Raw PDF binary content
     * @param  string  $storagePath  Path in bucket (e.g. one_care/admissions/123/surgery_456.pdf)
     * @return string  Public download URL
     *
     * @throws \Exception
     */
    public static function uploadPdfToStorage(string $content, string $storagePath): string
    {
        $serviceAccountPath = config('firebase.service_account_path');

        if (!file_exists($serviceAccountPath)) {
            throw new \Exception("Firebase service account file not found at: {$serviceAccountPath}. Please configure Firebase properly.");
        }

        $factory = (new Factory)
            ->withServiceAccount($serviceAccountPath)
            ->withProjectId(config('firebase.project_id'));

        $storage = $factory->createStorage();
        $bucketName = config('firebase.storage_bucket');
        $bucket = $storage->getBucket($bucketName);

        // Delete existing file if present so the new one replaces it
        try {
            $existing = $bucket->object($storagePath);
            if ($existing->exists()) {
                $existing->delete();
            }
        } catch (\Throwable $e) {
            // Object may not exist, ignore
        }

        $object = $bucket->upload($content, [
            'name' => $storagePath,
            'metadata' => [
                'contentType' => 'application/pdf',
                'cacheControl' => 'public, max-age=31536000',
            ],
        ]);

        try {
            $object->acl()->add('allUsers', 'READER');
        } catch (\Throwable $e) {
            Log::info('Firebase Storage ACL skipped (uniform bucket-level access may be enabled)', [
                'path' => $storagePath,
                'message' => $e->getMessage(),
            ]);
        }

        return self::generatePublicUrlForPath($storagePath);
    }

    /**
     * Generate public URL for a Firebase Storage path.
     */
    public static function generatePublicUrlForPath(string $storagePath): string
    {
        $serviceAccountPath = config('firebase.service_account_path');

        if (!file_exists($serviceAccountPath)) {
            throw new \Exception("Firebase service account file not found at: {$serviceAccountPath}. Please configure Firebase properly.");
        }

        $factory = (new Factory)
            ->withServiceAccount($serviceAccountPath)
            ->withProjectId(config('firebase.project_id'));

        $storage = $factory->createStorage();
        $bucketName = config('firebase.storage_bucket');
        $bucket = $storage->getBucket($bucketName);

        return 'https://storage.googleapis.com/'.$bucket->name().'/'.$storagePath;
    }

    /**
     * Get Firestore document fields as plain PHP array (decoded).
     *
     * @return array<string, mixed>|null
     */
    public static function getFirestoreDocumentFields(string $documentPath): ?array
    {
        try {
            $projectId = config('firebase.project_id');
            if (!$projectId) {
                return null;
            }
            $accessToken = self::getAccessToken();
            if (!$accessToken) {
                return null;
            }
            $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$documentPath}";
            $response = \Illuminate\Support\Facades\Http::withToken($accessToken)->get($url);
            if (!$response->successful()) {
                return null;
            }
            $document = $response->json();
            $fields = $document['fields'] ?? [];
            $decoded = [];
            foreach ($fields as $key => $encoded) {
                $decoded[$key] = self::parseFirestoreValue($encoded);
            }
            return $decoded;
        } catch (\Throwable $e) {
            Log::warning('Firestore getDocumentFields exception', [
                'path' => $documentPath,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Parse Firestore encoded value to PHP.
     *
     * @param  array<string, mixed>  $v
     * @return mixed
     */
    public static function parseFirestoreValue(array $v): mixed
    {
        if (isset($v['stringValue'])) {
            return $v['stringValue'];
        }
        if (isset($v['integerValue'])) {
            return (int) $v['integerValue'];
        }
        if (isset($v['doubleValue'])) {
            return (float) $v['doubleValue'];
        }
        if (isset($v['booleanValue'])) {
            return $v['booleanValue'];
        }
        if (isset($v['nullValue'])) {
            return null;
        }
        if (isset($v['timestampValue'])) {
            return \Carbon\Carbon::parse($v['timestampValue']);
        }
        if (isset($v['arrayValue']['values'])) {
            return array_map([self::class, 'parseFirestoreValue'], $v['arrayValue']['values']);
        }
        if (isset($v['mapValue']['fields'])) {
            $result = [];
            foreach ($v['mapValue']['fields'] as $k => $fv) {
                $result[$k] = self::parseFirestoreValue($fv);
            }
            return $result;
        }
        return null;
    }

    /**
     * Format value for Firestore
     */
    private static function formatFirestoreValue($value)
    {
        if (is_bool($value)) {
            return ['booleanValue' => $value];
        } elseif (is_int($value)) {
            return ['integerValue' => (string) $value];
        } elseif (is_float($value)) {
            return ['doubleValue' => $value];
        } elseif (is_string($value)) {
            return ['stringValue' => $value];
        } elseif (is_array($value) && !isset($value['stringValue']) && !isset($value['integerValue']) && !isset($value['timestampValue']) && !isset($value['mapValue'])) {
            $keys = array_keys($value);
            $isList = $keys === range(0, count($value) - 1);
            if ($isList) {
                return ['arrayValue' => ['values' => array_map([self::class, 'formatFirestoreValue'], $value)]];
            }
            // Use stdClass so mapValue.fields serializes as JSON object, not array.
            // PHP arrays with numeric keys would otherwise become JSON arrays, causing
            // "Cannot bind a list to map for field 'fields'" from Firestore.
            $fields = new \stdClass();
            foreach ($value as $k => $v) {
                $fields->{$k} = self::formatFirestoreValue($v);
            }
            return ['mapValue' => ['fields' => $fields]];
        } elseif ($value instanceof \DateTimeInterface) {
            $utc = $value instanceof \Carbon\Carbon
                ? $value->utc()
                : \Carbon\Carbon::parse($value)->utc();
            return ['timestampValue' => $utc->format('Y-m-d\TH:i:s.v\Z')];
        } elseif (is_null($value)) {
            return ['nullValue' => null];
        } else {
            return ['stringValue' => (string) $value];
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
