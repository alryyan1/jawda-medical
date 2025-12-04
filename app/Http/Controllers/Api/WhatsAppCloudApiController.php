<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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
        Log::info('WhatsApp Cloud API: Incoming message received.', [
            'message_id' => $message['id'] ?? null,
            'from' => $message['from'] ?? null,
            'type' => $message['type'] ?? null,
            'timestamp' => $message['timestamp'] ?? null,
        ]);

        // Add your business logic here to handle incoming messages
        // For example: save to database, trigger notifications, etc.
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
}

