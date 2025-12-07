<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppCloudApiService;
use App\Services\FirebaseService;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class WhatsAppCloudApiController extends Controller
{
    protected WhatsAppCloudApiService $whatsappService;

    public function __construct(WhatsAppCloudApiService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    /**
     * Send a text message via WhatsApp Cloud API.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendTextMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|string|max:20',
            'text' => 'required|string|max:4096',
            'access_token' => 'nullable|string',
            'phone_number_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $to = $request->input('to');
        $text = $request->input('text');
        $accessToken = $request->input('access_token') ?? $this->whatsappService->getAccessToken();
        $phoneNumberId = $request->input('phone_number_id') ?? $this->whatsappService->getPhoneNumberId();

        // Format phone number to international format
        $to = WhatsAppCloudApiService::formatPhoneNumber($to);
        if (!$to) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number format'
            ], 400);
        }

        $result = $this->whatsappService->sendTextMessage($to, $text, $accessToken, $phoneNumberId);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Send a template message via WhatsApp Cloud API.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendTemplateMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|string|max:20',
            'template_name' => 'required|string|max:255',
            'language_code' => 'nullable|string|max:10',
            'components' => 'nullable|array',
            'access_token' => 'nullable|string',
            'phone_number_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $to = $request->input('to');
        $templateName = $request->input('template_name');
        $languageCode = $request->input('language_code', 'en_US');
        $components = $request->input('components', []);
        $accessToken = $request->input('access_token') ?? $this->whatsappService->getAccessToken();
        $phoneNumberId = $request->input('phone_number_id') ?? $this->whatsappService->getPhoneNumberId();

        // Format phone number to international format
        $to = WhatsAppCloudApiService::formatPhoneNumber($to);
        if (!$to) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number format'
            ], 400);
        }

        $result = $this->whatsappService->sendTemplateMessage(
            $to,
            $templateName,
            $languageCode,
            $components,
            $accessToken,
            $phoneNumberId
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Send a document via WhatsApp Cloud API.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendDocument(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|string|max:20',
            'document_url' => 'required|url',
            'filename' => 'nullable|string|max:255',
            'caption' => 'nullable|string|max:1024',
            'access_token' => 'nullable|string',
            'phone_number_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $to = $request->input('to');
        $documentUrl = $request->input('document_url');
        $filename = $request->input('filename');
        $caption = $request->input('caption');
        $accessToken = $request->input('access_token') ?? $this->whatsappService->getAccessToken();
        $phoneNumberId = $request->input('phone_number_id') ?? $this->whatsappService->getPhoneNumberId();

        // Format phone number to international format
        $to = WhatsAppCloudApiService::formatPhoneNumber($to);
        if (!$to) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number format'
            ], 400);
        }

        $result = $this->whatsappService->sendDocument(
            $to,
            $documentUrl,
            $filename,
            $caption,
            $accessToken,
            $phoneNumberId
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Send an image via WhatsApp Cloud API.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendImage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|string|max:20',
            'image_url' => 'required|url',
            'caption' => 'nullable|string|max:1024',
            'access_token' => 'nullable|string',
            'phone_number_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $to = $request->input('to');
        $imageUrl = $request->input('image_url');
        $caption = $request->input('caption');
        $accessToken = $request->input('access_token') ?? $this->whatsappService->getAccessToken();
        $phoneNumberId = $request->input('phone_number_id') ?? $this->whatsappService->getPhoneNumberId();

        // Format phone number to international format
        $to = WhatsAppCloudApiService::formatPhoneNumber($to);
        if (!$to) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number format'
            ], 400);
        }

        $result = $this->whatsappService->sendImage(
            $to,
            $imageUrl,
            $caption,
            $accessToken,
            $phoneNumberId
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get phone numbers for a WABA.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function getPhoneNumbers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'waba_id' => 'nullable|string',
            'access_token' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $wabaId = $request->input('waba_id') ?? $this->whatsappService->getWabaId();
        $accessToken = $request->input('access_token') ?? $this->whatsappService->getAccessToken();

        $result = $this->whatsappService->getPhoneNumbers($wabaId, $accessToken);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Check if service is configured.
     *
     * @return JsonResponse
     */
    public function isConfigured(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'configured' => $this->whatsappService->isConfigured(),
            'phone_number_id' => $this->whatsappService->getPhoneNumberId(),
            'waba_id' => $this->whatsappService->getWabaId(),
        ]);
    }

    /**
     * Webhook verification endpoint for WhatsApp Cloud API.
     * This is called by Meta when setting up webhooks.
     * 
     * According to Meta's webhook documentation:
     * - Verification requests are GET requests with hub.mode=subscribe, hub.challenge, and hub.verify_token
     * - PHP converts periods (.) to underscores (_) in parameter names
     * - Must verify that hub.verify_token matches the token set in App Dashboard
     * - Must respond with the hub.challenge value
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|JsonResponse
     */
    public function verifyWebhook(Request $request)
    {
        // PHP converts hub.mode to hub_mode, hub.verify_token to hub_verify_token, etc.
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // Get verify token from settings
        $appSettings = \App\Models\Setting::first();
        $verifyToken = 'alryyan';

        // Validate verification request according to Meta's documentation
        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp Cloud API: Webhook verified successfully.', [
                'mode' => $mode,
                'challenge' => $challenge
            ]);
            
            // Respond with the challenge value as plain text (200 OK)
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('WhatsApp Cloud API: Webhook verification failed.', [
            'mode' => $mode,
            'token_received' => $token,
            'token_expected' => $verifyToken ? '***' : 'NULL'
        ]);

        return response()->json(['error' => 'Forbidden'], 403);
    }

    /**
     * Webhook callback endpoint for receiving WhatsApp event notifications.
     * 
     * According to Meta's webhook documentation:
     * - Event notifications are POST requests with JSON payloads
     * - All payloads are signed with SHA256 signature in X-Hub-Signature-256 header
     * - Signature format: sha256={signature}
     * - Must validate signature using App Secret
     * - Must respond with 200 OK for all notifications
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function webhook(Request $request): JsonResponse
    {
        // Get the raw request body for signature validation
        $payload = $request->getContent();
        $data = $request->all();

        // Validate the webhook signature (X-Hub-Signature-256)
        if (!$this->validateWebhookSignature($request, $payload)) {
            Log::warning('WhatsApp Cloud API: Webhook signature validation failed.', [
                'signature_header' => $request->header('X-Hub-Signature-256'),
                'ip' => $request->ip()
            ]);
            
            // Still return 200 OK to prevent retries, but log the issue
            return response()->json(['success' => false, 'error' => 'Invalid signature'], 200);
        }

        Log::info('WhatsApp Cloud API: Webhook received and validated.', [
            'object' => $data['object'] ?? null,
            'entry_count' => isset($data['entry']) ? count($data['entry']) : 0
        ]);

        // Handle webhook events
        if (isset($data['entry'])) {
            foreach ($data['entry'] as $entry) {
                if (isset($entry['changes'])) {
                    foreach ($entry['changes'] as $change) {
                        // Handle incoming messages
                        if (isset($change['value']['messages'])) {
                            foreach ($change['value']['messages'] as $message) {
                                $this->handleIncomingMessage($message, $change['value']);
                            }
                        }

                        // Handle message status updates
                        if (isset($change['value']['statuses'])) {
                            foreach ($change['value']['statuses'] as $status) {
                                $this->handleMessageStatus($status);
                            }
                        }
                    }
                }
            }
        }

        // Always return 200 OK as per Meta's documentation
        return response()->json(['success' => true], 200);
    }

    /**
     * Validate the webhook signature using SHA256 and App Secret.
     * 
     * According to Meta's documentation:
     * 1. Generate SHA256 signature using payload and App Secret
     * 2. Compare with signature in X-Hub-Signature-256 header (everything after sha256=)
     * 
     * @param Request $request
     * @param string $payload Raw request body
     * @return bool
     */
    protected function validateWebhookSignature(Request $request, string $payload): bool
    {
        $signatureHeader = $request->header('X-Hub-Signature-256');
        
        if (!$signatureHeader) {
            Log::warning('WhatsApp Cloud API: Missing X-Hub-Signature-256 header.');
            return false;
        }

        // Extract signature (everything after "sha256=")
        $signature = str_replace('sha256=', '', $signatureHeader);
        
        if (empty($signature)) {
            return false;
        }

        // Get App Secret from settings or config
        $appSettings = \App\Models\Setting::first();
        $appSecret = $appSettings?->whatsapp_cloud_app_secret ?? config('services.whatsapp.app_secret');
        
        if (!$appSecret) {
            Log::warning('WhatsApp Cloud API: App Secret not configured. Skipping signature validation.');
            // If App Secret is not configured, we can't validate, but allow the request
            // In production, you should configure the App Secret
            return true; // Change to false in production if you want strict validation
        }

        // Generate expected signature: SHA256 hash of payload using App Secret as key
        $expectedSignature = hash_hmac('sha256', $payload, $appSecret);

        // Compare signatures using hash_equals to prevent timing attacks
        $isValid = hash_equals($expectedSignature, $signature);

        if (!$isValid) {
            Log::error('WhatsApp Cloud API: Signature mismatch.', [
                'expected' => substr($expectedSignature, 0, 10) . '...',
                'received' => substr($signature, 0, 10) . '...'
            ]);
        }

        return $isValid;
    }

    /**
     * Handle incoming WhatsApp messages.
     *
     * @param array $message
     * @param array $value
     * @return void
     */
    protected function handleIncomingMessage(array $message, array $value): void
    {
        $messageId = $message['id'] ?? null;
        $from = $message['from'] ?? null;
        $type = $message['type'] ?? null;
        $timestamp = $message['timestamp'] ?? null;

        Log::info('WhatsApp Cloud API: Incoming message received.', [
            'message_id' => $messageId,
            'from' => $from,
            'type' => $type,
            'timestamp' => $timestamp,
        ]);

        // Handle interactive messages (button replies) or button type messages
        // Check for button/interactive messages in multiple possible formats
        $isButtonMessage = false;
        $buttonData = null;
        
        if ($type === 'interactive' && isset($message['interactive'])) {
            $interactive = $message['interactive'];
            $interactiveType = $interactive['type'] ?? null;
            
            Log::info('WhatsApp Cloud API: Interactive message received.', [
                'interactive_type' => $interactiveType,
                'from' => $from,
                'interactive_data' => $interactive
            ]);

            // Handle button reply in interactive format
            if ($interactiveType === 'button_reply' && isset($interactive['button_reply'])) {
                $isButtonMessage = true;
                $buttonData = $interactive['button_reply'];
                
                Log::info('WhatsApp Cloud API: Button reply received (interactive format).', [
                    'button_id' => $buttonData['id'] ?? null,
                    'button_title' => $buttonData['title'] ?? null,
                    'from' => $from
                ]);
            }
        }
        // Handle button type (alternative format - when type is directly "button")
        elseif ($type === 'button') {
            // Check for button data in various possible locations
            if (isset($message['button'])) {
                $isButtonMessage = true;
                $buttonData = $message['button'];
            } elseif (isset($message['interactive']['button_reply'])) {
                $isButtonMessage = true;
                $buttonData = $message['interactive']['button_reply'];
            }
            
            if ($isButtonMessage) {
                Log::info('WhatsApp Cloud API: Button message received (button type).', [
                    'button_id' => $buttonData['id'] ?? null,
                    'button_text' => $buttonData['text'] ?? $buttonData['title'] ?? null,
                    'from' => $from,
                    'full_message' => $message
                ]);
            }
        }
        
        // Process button message if detected
        if ($isButtonMessage && $buttonData !== null) {
            // Get collection from settings
            $settings = Setting::first();
            $collection = $settings?->firestore_result_collection ?? 'altamayoz_branch2';

            // Fetch PDF URL from Firestore using the sender's phone number
            $pdfUrl = $this->getResultUrlFromFirestoreByPhone($from, $collection);
            
            if ($pdfUrl) {
                // Send notification message before sending the PDF document
                $this->sendTextToUser($from, "سيتم إرسال النتيجة إليكم خلال لحظات");
                // Send the PDF document back to the sender
                $this->sendDocumentToUser($from, $pdfUrl);
            } else {
                // Send error message if PDF not found
                $this->sendTextToUser($from, "عذراً، لم يتم العثور على النتيجة لرقم الهاتف: {$from}");
            }
        }
        // Handle text messages that may contain a code/visit ID
        elseif ($type === 'text' && isset($message['text']['body'])) {
            $messageText = trim($message['text']['body']);
            $this->sendTextToUser($from, "سيتم إرسال النتيجة إليكم خلال لحظات");
            
            // Extract code/visit ID from message (assuming it's a numeric code)
            // You can modify this regex pattern based on your code format
            if (preg_match('/\b(\d+)\b/', $messageText, $matches)) {
                $code = $matches[1];
                
                Log::info('WhatsApp Cloud API: Code extracted from message.', [
                    'code' => $code,
                    'from' => $from,
                    'message_text' => $messageText
                ]);

                // Fetch PDF URL from Firestore using the code
                $pdfUrl = $this->getResultUrlFromFirestore($code);
                
                if ($pdfUrl) {
                    // Send the PDF document back to the sender
                    $this->sendDocumentToUser($from, $pdfUrl, $code);
                } else {
                    // Send error message if PDF not found
                    $this->sendTextToUser($from, "عذراً، لم يتم العثور على النتيجة للرقم: {$code}");
                }
            } else {
                Log::info('WhatsApp Cloud API: No code found in message.', [
                    'message_text' => $messageText,
                    'from' => $from
                ]);
            }
        }
    }

    /**
     * Get result URL from Firestore using visit ID/code.
     * Based on UltramsgController::getResultUrlFromFirestore method.
     *
     * @param string $visitId The visit ID or code to look up
     * @param string|null $collection Optional collection name (defaults to settings.firestore_result_collection)
     * @return string|null The result URL or null if not found
     */
    protected function getResultUrlFromFirestore(string $visitId, ?string $collection = null): ?string
    {
        try {
            $projectId = config('firebase.project_id');
            if (!$projectId) {
                Log::warning('Firebase project ID not configured for Firestore read');
                return null;
            }

            $accessToken = FirebaseService::getAccessToken();
            if (!$accessToken) {
                Log::warning('FCM access token unavailable for Firestore read');
                return null;
            }

            // Get collection name from settings if not provided
            if (!$collection) {
                $settings = Setting::first();
                $collection = $settings?->firestore_result_collection ?? 'lab_results';
            }

            $documentId = (string) $visitId;
            $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$documentId}";

            $response = Http::withToken($accessToken)->get($url);

            if ($response->successful()) {
                $document = $response->json();
                $fields = $document['fields'] ?? [];
                
                // Extract result_url from Firestore document
                if (isset($fields['result_url']['stringValue'])) {
                    $resultUrl = $fields['result_url']['stringValue'];
                    Log::info("Retrieved result URL from Firestore", [
                        'collection' => $collection,
                        'document_id' => $documentId,
                        'result_url' => $resultUrl
                    ]);
                    return $resultUrl;
                } else {
                    Log::warning("Result URL not found in Firestore document", [
                        'collection' => $collection,
                        'document_id' => $documentId,
                        'available_fields' => array_keys($fields)
                    ]);
                    return null;
                }
            } else if ($response->status() === 404) {
                Log::warning("Firestore document not found", [
                    'collection' => $collection,
                    'document_id' => $documentId
                ]);
                return null;
            } else {
                Log::warning("Failed to get Firestore document", [
                    'collection' => $collection,
                    'document_id' => $documentId,
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                return null;
            }

        } catch (\Exception $e) {
            Log::error("Failed to get result URL from Firestore", [
                'visit_id' => $visitId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Get the most recent result URL from Firestore by searching for patient phone number.
     * Queries documents where patient_phone matches, sorted by updated_at descending.
     *
     * @param string $phoneNumber The patient phone number to search for
     * @param string|null $collection Optional collection name (defaults to settings.firestore_result_collection)
     * @return string|null The most recent result URL or null if not found
     */
    protected function getResultUrlFromFirestoreByPhone(string $phoneNumber, ?string $collection = null): ?string
    {
        try {
            $projectId = config('firebase.project_id');
            if (!$projectId) {
                Log::warning('Firebase project ID not configured for Firestore query');
                return null;
            }

            $accessToken = FirebaseService::getAccessToken();
            if (!$accessToken) {
                Log::warning('FCM access token unavailable for Firestore query');
                return null;
            }

            // Get collection name from settings if not provided
            if (!$collection) {
                $settings = Setting::first();
                $collection = $settings?->firestore_result_collection ?? 'lab_results';
            }

            // Normalize phone number (remove +, spaces, dashes, etc.)
            $normalizedPhone = preg_replace('/[^0-9]/', '', $phoneNumber);
            
            // Build phone variants to try
            // Start with original (in case it's stored exactly as provided, e.g., "0117613099")
            $phoneVariants = [$phoneNumber];
            
            // Add normalized version if different
            if ($phoneNumber !== $normalizedPhone) {
                $phoneVariants[] = $normalizedPhone;
            }
            
            // Since phone numbers are stored WITHOUT country code, try removing common country codes
            // Common country codes: 249 (Sudan), 968 (Oman), 966 (Saudi), 971 (UAE), 974 (Qatar), etc.
            $countryCodes = ['249', '968', '966', '971', '974', '965', '973', '961', '962', '964', '963', '961'];
            
            foreach ($countryCodes as $code) {
                if (strlen($normalizedPhone) > strlen($code) && substr($normalizedPhone, 0, strlen($code)) === $code) {
                    $phoneWithoutCountryCode = substr($normalizedPhone, strlen($code));
                    // Only add if it's a reasonable length (at least 8 digits)
                    if (strlen($phoneWithoutCountryCode) >= 8) {
                        $phoneVariants[] = $phoneWithoutCountryCode;
                    }
                }
            }

            // Remove duplicates while preserving order
            $phoneVariants = array_values(array_unique($phoneVariants));

            Log::info("Searching Firestore by phone number", [
                'collection' => $collection,
                'original_phone' => $phoneNumber,
                'normalized_phone' => $normalizedPhone,
                'variants_to_try' => $phoneVariants
            ]);

            // Firestore runQuery endpoint - must use database path, not collection path
            $parent = "projects/{$projectId}/databases/(default)";
            $url = "https://firestore.googleapis.com/v1/{$parent}/documents:runQuery";

            // Try each phone variant until we find a match
            $foundDocument = null;
            $phoneVariantUsed = null;
            
            foreach ($phoneVariants as $phoneToSearch) {
                // Build structured query: filter by patient_phone, limit 10
                // Note: Removed orderBy to avoid index requirement - we'll sort results in PHP if needed
                // Must include 'parent' and 'from' collection in the query
                $query = [
                    'parent' => $parent,
                    'structuredQuery' => [
                        'from' => [
                            ['collectionId' => $collection]
                        ],
                        'where' => [
                            'fieldFilter' => [
                                'field' => [
                                    'fieldPath' => 'patient_phone'
                                ],
                                'op' => 'EQUAL',
                                'value' => [
                                    'stringValue' => $phoneToSearch
                                ]
                            ]
                        ],
                        'limit' => 10  // Get multiple results, then sort by updated_at in PHP
                    ]
                ];

                Log::debug("Querying Firestore", [
                    'collection' => $collection,
                    'phone_variant' => $phoneToSearch,
                    'query_url' => $url
                ]);

                // Send as raw JSON body to ensure proper formatting
                $response = Http::withToken($accessToken)
                    ->withBody(json_encode($query), 'application/json')
                    ->post($url);

                if ($response->successful()) {
                    $results = $response->json();
                    
                    Log::debug("Firestore query response", [
                        'collection' => $collection,
                        'phone_variant' => $phoneToSearch,
                        'response_status' => $response->status(),
                        'results_count' => is_array($results) ? count($results) : 0,
                        'results' => $results
                    ]);
                    
                    // Check if we have any results
                    // Firestore returns an array, and each result has a 'document' key if found
                    if (is_array($results) && !empty($results)) {
                        // Filter to only documents (not empty results)
                        $documents = array_filter($results, function($result) {
                            return isset($result['document']);
                        });
                        
                        if (!empty($documents)) {
                            // Sort by updated_at descending if available
                            usort($documents, function($a, $b) {
                                $aTime = $a['document']['fields']['updated_at']['timestampValue'] ?? '';
                                $bTime = $b['document']['fields']['updated_at']['timestampValue'] ?? '';
                                return strcmp($bTime, $aTime); // Descending
                            });
                            
                            // Get the most recent document
                            $foundDocument = $documents[0]['document'];
                            $phoneVariantUsed = $phoneToSearch;
                            Log::info("Found matching document in Firestore", [
                                'collection' => $collection,
                                'phone_variant_used' => $phoneVariantUsed,
                                'document_name' => $foundDocument['name'] ?? 'unknown',
                                'total_matches' => count($documents)
                            ]);
                            break;
                        }
                    } else {
                        Log::debug("No documents found for phone variant", [
                            'collection' => $collection,
                            'phone_variant' => $phoneToSearch,
                            'results' => $results
                        ]);
                    }
                } else {
                    Log::warning("Failed to query Firestore by phone variant", [
                        'collection' => $collection,
                        'phone_variant' => $phoneToSearch,
                        'status' => $response->status(),
                        'body' => $response->body()
                    ]);
                }
            }

            // Check if we found any results after trying all variants
            if ($foundDocument === null) {
                Log::info("No Firestore documents found for phone number after trying all variants", [
                    'collection' => $collection,
                    'phone_number' => $phoneNumber,
                    'normalized_phone' => $normalizedPhone,
                    'variants_tried' => $phoneVariants
                ]);
                return null;
            }

            // Get the first (most recent) document
            $document = $foundDocument;
            $fields = $document['fields'] ?? [];
            
            // Extract result_url from Firestore document
            if (isset($fields['result_url']['stringValue'])) {
                $resultUrl = $fields['result_url']['stringValue'];
                $documentId = $document['name'] ?? 'unknown';
                
                Log::info("Retrieved result URL from Firestore by phone", [
                    'collection' => $collection,
                    'phone_number' => $phoneNumber,
                    'phone_variant_used' => $phoneVariantUsed,
                    'document_id' => $documentId,
                    'result_url' => $resultUrl
                ]);
                return $resultUrl;
            } else {
                Log::warning("Result URL not found in Firestore document", [
                    'collection' => $collection,
                    'phone_number' => $phoneNumber,
                    'phone_variant_used' => $phoneVariantUsed,
                    'available_fields' => array_keys($fields)
                ]);
                return null;
            }

        } catch (\Exception $e) {
            Log::error("Failed to get result URL from Firestore by phone", [
                'phone_number' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return null;
        }
    }

    /**
     * Send a document to a user via WhatsApp Cloud API.
     *
     * @param string $to Phone number in international format
     * @param string $documentUrl URL of the document to send
     * @param string|null $code Optional code/visit ID for filename
     * @return void
     */
    protected function sendDocumentToUser(string $to, string $documentUrl, ?string $code = null): void
    {
        try {
            $filename = $code ? "result_{$code}.pdf" : 'result.pdf';
            
            $result = $this->whatsappService->sendDocument(
                $to,
                $documentUrl,
                $filename,
                'نتيجة المختبر - Lab Result'
            );

            if ($result['success']) {
                Log::info('WhatsApp Cloud API: Document sent successfully to user.', [
                    'to' => $to,
                    'document_url' => $documentUrl,
                    'message_id' => $result['message_id'] ?? null
                ]);
            } else {
                Log::error('WhatsApp Cloud API: Failed to send document to user.', [
                    'to' => $to,
                    'document_url' => $documentUrl,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp Cloud API: Exception while sending document to user.', [
                'to' => $to,
                'document_url' => $documentUrl,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Send a text message to a user via WhatsApp Cloud API.
     *
     * @param string $to Phone number in international format
     * @param string $text Message text to send
     * @return void
     */
    protected function sendTextToUser(string $to, string $text): void
    {
        try {
            $result = $this->whatsappService->sendTextMessage($to, $text);

            if ($result['success']) {
                Log::info('WhatsApp Cloud API: Text message sent successfully to user.', [
                    'to' => $to,
                    'message_id' => $result['message_id'] ?? null
                ]);
            } else {
                Log::error('WhatsApp Cloud API: Failed to send text message to user.', [
                    'to' => $to,
                    'error' => $result['error'] ?? 'Unknown error'
                ]);
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp Cloud API: Exception while sending text message to user.', [
                'to' => $to,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Handle message status updates.
     *
     * @param array $status
     * @return void
     */
    protected function handleMessageStatus(array $status): void
    {
        Log::info('WhatsApp Cloud API: Message status updated.', [
            'message_id' => $status['id'] ?? null,
            'status' => $status['status'] ?? null,
            'timestamp' => $status['timestamp'] ?? null,
        ]);

        // Add your business logic here to handle status updates
        // For example: update message status in database
    }

    /**
     * Test method to get result URL from Firestore by phone number.
     * This is a public method for testing purposes.
     *
     * @param Request $request
     * @param string|null $phoneNumber Optional phone number from route parameter
     * @return JsonResponse
     */
    public function testGetResultUrlByPhone(Request $request, ?string $phoneNumber = null): JsonResponse
    {
        // Get phone number from route parameter or query parameter, with default fallback
        $phoneNumber = $phoneNumber ?? $request->query('phone', '0117613099');
        
        // Get collection from query parameter or use default
        $collection = $request->query('collection', 'alroomy_results');
        
        $resultUrl = $this->getResultUrlFromFirestoreByPhone($phoneNumber, $collection);
        
        return response()->json([
            'success' => $resultUrl !== null,
            'phone_number' => $phoneNumber,
            'collection' => $collection,
            'result_url' => $resultUrl,
        ]);
    }
}

