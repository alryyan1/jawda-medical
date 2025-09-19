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
}
