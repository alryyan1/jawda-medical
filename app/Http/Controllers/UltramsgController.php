<?php

namespace App\Http\Controllers;

use App\Services\UltramsgService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class UltramsgController extends Controller
{
    protected UltramsgService $ultramsgService;

    public function __construct(UltramsgService $ultramsgService)
    {
        $this->ultramsgService = $ultramsgService;
    }

    /**
     * Send a text message via Ultramsg WhatsApp API.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendTextMessage(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|string|max:20',
            'body' => 'required|string|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $to = $request->input('to');
        $body = $request->input('body');

        // Format phone number to international format
        $to = UltramsgService::formatPhoneNumber($to);
        if (!$to) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number format'
            ], 400);
        }

        $result = $this->ultramsgService->sendTextMessage($to, $body);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Send a document via Ultramsg WhatsApp API.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendDocument(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|string|max:20',
            'filename' => 'required|string|max:255',
            'document' => 'required|string',
            'caption' => 'nullable|string|max:1024',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $to = $request->input('to');
        $filename = $request->input('filename');
        $document = $request->input('document');
        $caption = $request->input('caption', '');

        // Format phone number to international format
        $to = UltramsgService::formatPhoneNumber($to);
        if (!$to) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number format'
            ], 400);
        }

        $result = $this->ultramsgService->sendDocument($to, $filename, $document, $caption);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Send a document from uploaded file.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendDocumentFromFile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|string|max:20',
            'file' => 'required|file|max:30720', // 30MB max
            'caption' => 'nullable|string|max:1024',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $to = $request->input('to');
        $file = $request->file('file');
        $caption = $request->input('caption', '');

        // Format phone number to international format
        $to = UltramsgService::formatPhoneNumber($to);
        if (!$to) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number format'
            ], 400);
        }

        // Store file temporarily
        $tempPath = $file->store('temp');
        $fullPath = storage_path('app/' . $tempPath);

        try {
            $result = $this->ultramsgService->sendDocumentFromFile($to, $fullPath, $caption);
        } finally {
            // Clean up temporary file
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Send a document from URL.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendDocumentFromUrl(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'to' => 'required|string|max:20',
            'document_url' => 'required|url',
            'filename' => 'required|string|max:255',
            'caption' => 'nullable|string|max:1024',
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
        $caption = $request->input('caption', '');

        // Format phone number to international format
        $to = UltramsgService::formatPhoneNumber($to);
        if (!$to) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number format'
            ], 400);
        }

        $result = $this->ultramsgService->sendDocumentFromUrl($to, $documentUrl, $filename, $caption);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Get instance status.
     *
     * @return JsonResponse
     */
    public function getInstanceStatus(): JsonResponse
    {
        $result = $this->ultramsgService->getInstanceStatus();

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
            'configured' => $this->ultramsgService->isConfigured(),
            'instance_id' => $this->ultramsgService->getInstanceId(),
        ]);
    }

    /**
     * Send a text message with custom token and instance_id.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendTextMessageWithCredentials(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'instance_id' => 'required|string',
            'phone' => 'required|string|max:20',
            'body' => 'required|string|max:4096',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $token = $request->input('token');
        $instanceId = $request->input('instance_id');
        $to = $request->input('phone');
        $body = $request->input('body');

        // Format phone number to international format
        $to = UltramsgService::formatPhoneNumber($to);
        if (!$to) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number format'
            ], 400);
        }

        // Get base URL from settings or use default
        $appSettings = \App\Models\Setting::first();
        $baseUrl = $appSettings?->ultramsg_base_url ?? 'https://api.ultramsg.com';
        
        $endpoint = "{$baseUrl}/{$instanceId}/messages/chat";

        try {
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->post($endpoint, [
                    'token' => $token,
                    'to' => $to,
                    'body' => $body,
                ]);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['sent']) && $responseData['sent'] === 'true') {
                \Illuminate\Support\Facades\Log::info("UltramsgService: Text message sent successfully with custom credentials.", [
                    'response' => $responseData,
                    'message_id' => $responseData['id'] ?? null
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => $responseData,
                    'message_id' => $responseData['id'] ?? null
                ], 200);
            }

            $errorMessage = "Failed to send text message.";
            if (isset($responseData['message'])) {
                $errorMessage .= " Error: " . $responseData['message'];
            } else {
                $errorMessage .= " HTTP Status: " . $response->status();
            }

            \Illuminate\Support\Facades\Log::error("UltramsgService: {$errorMessage}", [
                'response' => $responseData,
                'status_code' => $response->status()
            ]);

            return response()->json([
                'success' => false,
                'error' => $errorMessage,
                'data' => $responseData
            ], 400);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("UltramsgController sendTextMessageWithCredentials Exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send a document with custom token and instance_id.
     * Accepts either file upload or base64 string.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function sendDocumentWithCredentials(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'instance_id' => 'required|string',
            'phone' => 'required|string|max:20',
            'file' => 'nullable|file|max:30720', // 30MB max
            'base64' => 'nullable|string',
            'caption' => 'nullable|string|max:1024',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Ensure either file or base64 is provided, but not both
        $hasFile = $request->hasFile('file');
        $hasBase64 = $request->filled('base64');

        if (!$hasFile && !$hasBase64) {
            return response()->json([
                'success' => false,
                'error' => 'Either file or base64 must be provided'
            ], 422);
        }

        if ($hasFile && $hasBase64) {
            return response()->json([
                'success' => false,
                'error' => 'Cannot provide both file and base64. Please provide only one.'
            ], 422);
        }

        $token = $request->input('token');
        $instanceId = $request->input('instance_id');
        $to = $request->input('phone');
        $caption = $request->input('caption', 'labresult');

        // Format phone number to international format
        $to = UltramsgService::formatPhoneNumber($to);
        if (!$to) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid phone number format'
            ], 400);
        }

        $document = null;
        $filename = 'result.pdf';

        // Handle file upload
        if ($hasFile) {
            $file = $request->file('file');
            $filename = $file->getClientOriginalName() ?: 'result.pdf';
            
            // Store file temporarily
            $tempPath = $file->store('temp');
            $fullPath = storage_path('app/' . $tempPath);

            try {
                $fileContent = file_get_contents($fullPath);
                if ($fileContent === false) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Could not read file'
                    ], 500);
                }
                $document = base64_encode($fileContent);
            } finally {
                // Clean up temporary file
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
        } else {
            // Handle base64
            $document = $request->input('base64');
        }

        // Get base URL from settings or use default
        $appSettings = \App\Models\Setting::first();
        $baseUrl = $appSettings?->ultramsg_base_url ?? 'https://api.ultramsg.com';
        
        $endpoint = "{$baseUrl}/{$instanceId}/messages/document";

        try {
            $response = \Illuminate\Support\Facades\Http::asForm()
                ->post($endpoint, [
                    'token' => $token,
                    'to' => $to,
                    'filename' => $filename,
                    'document' => $document,
                    'caption' => $caption,
                ]);

            $responseData = $response->json();

            if ($response->successful() && isset($responseData['sent']) && $responseData['sent'] === 'true') {
                \Illuminate\Support\Facades\Log::info("UltramsgService: Document sent successfully with custom credentials.", [
                    'response' => $responseData,
                    'message_id' => $responseData['id'] ?? null
                ]);
                
                return response()->json([
                    'success' => true,
                    'data' => $responseData,
                    'message_id' => $responseData['id'] ?? null
                ], 200);
            }

            $errorMessage = "Failed to send document.";
            if (isset($responseData['message'])) {
                $errorMessage .= " Error: " . $responseData['message'];
            } else {
                $errorMessage .= " HTTP Status: " . $response->status();
            }

            \Illuminate\Support\Facades\Log::error("UltramsgService: {$errorMessage}", [
                'response' => $responseData,
                'status_code' => $response->status()
            ]);

            return response()->json([
                'success' => false,
                'error' => $errorMessage,
                'data' => $responseData
            ], 400);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error("UltramsgController sendDocumentWithCredentials Exception: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
