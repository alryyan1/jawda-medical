<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response;

class WhatsAppCloudApiService
{
    protected string $baseUrl = 'https://graph.facebook.com';
    protected string $apiVersion = 'v22.0';
    protected ?string $accessToken;
    protected ?string $phoneNumberId;
    protected ?string $wabaId;

    public function __construct()
    {
        $appSettings = Setting::first();
        
        $this->accessToken = $appSettings?->cloud_api_token ?? null;
        $this->phoneNumberId = $appSettings?->phone_number_id ?? null;
        $this->wabaId = $appSettings?->whatsapp_cloud_waba_id ?? null;
        $this->apiVersion = $appSettings?->whatsapp_cloud_api_version ?? 'v22.0';
    }

    /**
     * Check if the WhatsApp Cloud API service is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->accessToken) && !empty($this->phoneNumberId);
    }

    /**
     * Get the configured access token.
     */
    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    /**
     * Get the configured phone number ID.
     */
    public function getPhoneNumberId(): ?string
    {
        return $this->phoneNumberId;
    }

    /**
     * Get the configured WABA ID.
     */
    public function getWabaId(): ?string
    {
        return $this->wabaId;
    }

    /**
     * Send a text message via WhatsApp Cloud API.
     *
     * @param string $to Phone number with international format (e.g., 249991961111)
     * @param string $text Message text
     * @param string|null $accessToken Optional access token (overrides default)
     * @param string|null $phoneNumberId Optional phone number ID (overrides default)
     * @return array{success: bool, data: mixed, error?: string, message_id?: string}
     */
    public function sendTextMessage(string $to, string $text, ?string $accessToken = null, ?string $phoneNumberId = null): array
    {
        $accessToken = $accessToken ?? $this->accessToken;
        $phoneNumberId = $phoneNumberId ?? $this->phoneNumberId;

        if (!$accessToken || !$phoneNumberId) {
            Log::error('WhatsAppCloudApiService: Service not configured.');
            return ['success' => false, 'error' => 'WhatsApp Cloud API service not configured.', 'data' => null];
        }

        // Remove + from phone number if present (WhatsApp Cloud API expects numbers without +)
        $to = ltrim($to, '+');

        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        try {
            $response = Http::withToken($accessToken)
                ->asJson()
                ->post($endpoint, [
                    'messaging_product' => 'whatsapp',
                    'to' => $to,
                    'type' => 'text',
                    'text' => [
                        'body' => $text
                    ]
                ]);

            return $this->handleResponse($response, 'Text message');

        } catch (\Exception $e) {
            Log::error("WhatsAppCloudApiService sendTextMessage Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Send a template message via WhatsApp Cloud API.
     *
     * @param string $to Phone number with international format
     * @param string $templateName Template name (e.g., "hello_world")
     * @param string $languageCode Language code (e.g., "en_US")
     * @param array $components Optional template components/parameters
     * @param string|null $accessToken Optional access token (overrides default)
     * @param string|null $phoneNumberId Optional phone number ID (overrides default)
     * @return array{success: bool, data: mixed, error?: string, message_id?: string}
     */
    public function sendTemplateMessage(
        string $to,
        string $templateName,
        string $languageCode = 'en_US',
        array $components = [],
        ?string $accessToken = null,
        ?string $phoneNumberId = null
    ): array {
        $accessToken = $accessToken ?? $this->accessToken;
        $phoneNumberId = $phoneNumberId ?? $this->phoneNumberId;

        if (!$accessToken || !$phoneNumberId) {
            Log::error('WhatsAppCloudApiService: Service not configured.');
            return ['success' => false, 'error' => 'WhatsApp Cloud API service not configured.', 'data' => null];
        }

        // Remove + from phone number if present
        $to = ltrim($to, '+');

        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode
                ]
            ]
        ];

        // Add components if provided
        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        try {
            $response = Http::withToken($accessToken)
                ->asJson()
                ->post($endpoint, $payload);

            return $this->handleResponse($response, 'Template message');

        } catch (\Exception $e) {
            Log::error("WhatsAppCloudApiService sendTemplateMessage Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Send a document via WhatsApp Cloud API.
     *
     * @param string $to Phone number with international format
     * @param string $documentUrl HTTP URL to the document or media ID
     * @param string|null $filename Optional filename
     * @param string|null $caption Optional caption
     * @param string|null $accessToken Optional access token (overrides default)
     * @param string|null $phoneNumberId Optional phone number ID (overrides default)
     * @return array{success: bool, data: mixed, error?: string, message_id?: string}
     */
    public function sendDocument(
        string $to,
        string $documentUrl,
        ?string $filename = null,
        ?string $caption = null,
        ?string $accessToken = null,
        ?string $phoneNumberId = null
    ): array {
        $accessToken = $accessToken ?? $this->accessToken;
        $phoneNumberId = $phoneNumberId ?? $this->phoneNumberId;

        if (!$accessToken || !$phoneNumberId) {
            Log::error('WhatsAppCloudApiService: Service not configured.');
            return ['success' => false, 'error' => 'WhatsApp Cloud API service not configured.', 'data' => null];
        }

        // Remove + from phone number if present
        $to = ltrim($to, '+');

        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'document',
            'document' => [
                'link' => $documentUrl
            ]
        ];

        if ($filename) {
            $payload['document']['filename'] = $filename;
        }

        if ($caption) {
            $payload['document']['caption'] = $caption;
        }

        try {
            $response = Http::withToken($accessToken)
                ->asJson()
                ->post($endpoint, $payload);

            return $this->handleResponse($response, 'Document');

        } catch (\Exception $e) {
            Log::error("WhatsAppCloudApiService sendDocument Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Send an image via WhatsApp Cloud API.
     *
     * @param string $to Phone number with international format
     * @param string $imageUrl HTTP URL to the image or media ID
     * @param string|null $caption Optional caption
     * @param string|null $accessToken Optional access token (overrides default)
     * @param string|null $phoneNumberId Optional phone number ID (overrides default)
     * @return array{success: bool, data: mixed, error?: string, message_id?: string}
     */
    public function sendImage(
        string $to,
        string $imageUrl,
        ?string $caption = null,
        ?string $accessToken = null,
        ?string $phoneNumberId = null
    ): array {
        $accessToken = $accessToken ?? $this->accessToken;
        $phoneNumberId = $phoneNumberId ?? $this->phoneNumberId;

        if (!$accessToken || !$phoneNumberId) {
            Log::error('WhatsAppCloudApiService: Service not configured.');
            return ['success' => false, 'error' => 'WhatsApp Cloud API service not configured.', 'data' => null];
        }

        // Remove + from phone number if present
        $to = ltrim($to, '+');

        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => [
                'link' => $imageUrl
            ]
        ];

        if ($caption) {
            $payload['image']['caption'] = $caption;
        }

        try {
            $response = Http::withToken($accessToken)
                ->asJson()
                ->post($endpoint, $payload);

            return $this->handleResponse($response, 'Image');

        } catch (\Exception $e) {
            Log::error("WhatsAppCloudApiService sendImage Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Get phone numbers for a WABA.
     *
     * @param string|null $wabaId Optional WABA ID (overrides default)
     * @param string|null $accessToken Optional access token (overrides default)
     * @return array{success: bool, data: mixed, error?: string}
     */
    public function getPhoneNumbers(?string $wabaId = null, ?string $accessToken = null): array
    {
        $accessToken = $accessToken ?? $this->accessToken;
        $wabaId = $wabaId ?? $this->wabaId;
        $phoneNumberId = $this->phoneNumberId;

        if (!$accessToken) {
            Log::error('WhatsAppCloudApiService: Access token not configured.');
            return ['success' => false, 'error' => 'WhatsApp Cloud API service not configured. Missing access token.', 'data' => null];
        }

        // If WABA ID is not provided, try to get phone number details using phone number ID
        if (!$wabaId && $phoneNumberId) {
            // Get phone number details directly
            $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$phoneNumberId}";
            
            try {
                $response = Http::withToken($accessToken)
                    ->get($endpoint, [
                        'fields' => 'id,display_phone_number,verified_name,quality_rating'
                    ]);

                $responseData = $response->json();

                if ($response->successful() && isset($responseData['id'])) {
                    // Return single phone number in the same format as the list endpoint
                    Log::info("WhatsAppCloudApiService: Phone number retrieved successfully using phone number ID.", [
                        'response' => $responseData
                    ]);
                    
                    return [
                        'success' => true,
                        'data' => [
                            'data' => [$responseData]
                        ]
                    ];
                }

                // If that fails, try to get WABA ID from the phone number response
                if (isset($responseData['account_id'])) {
                    $wabaId = $responseData['account_id'];
                    Log::info("WhatsAppCloudApiService: Retrieved WABA ID from phone number details.", [
                        'waba_id' => $wabaId
                    ]);
                } else {
                    // If we can't get WABA ID, return the single phone number we got
                    $errorMessage = "Failed to get phone numbers.";
                    if (isset($responseData['error']['message'])) {
                        $errorMessage .= " Error: " . $responseData['error']['message'];
                    }
                    Log::error("WhatsAppCloudApiService: {$errorMessage}", [
                        'response' => $responseData
                    ]);
                    return ['success' => false, 'error' => $errorMessage, 'data' => $responseData];
                }
            } catch (\Exception $e) {
                Log::error("WhatsAppCloudApiService: Could not get phone number details: " . $e->getMessage());
                return ['success' => false, 'error' => 'Could not retrieve phone number details: ' . $e->getMessage(), 'data' => null];
            }
        }

        // If still no WABA ID, return error
        if (!$wabaId) {
            Log::error('WhatsAppCloudApiService: WABA ID not configured and could not be retrieved.');
            return ['success' => false, 'error' => 'WhatsApp Cloud API service not configured. Missing WABA ID. Please configure whatsapp_cloud_waba_id in settings or provide it in the request.', 'data' => null];
        }

        $endpoint = "{$this->baseUrl}/{$this->apiVersion}/{$wabaId}/phone_numbers";

        try {
            $response = Http::withToken($accessToken)
                ->get($endpoint);

            $responseData = $response->json();

            if ($response->successful()) {
                Log::info("WhatsAppCloudApiService: Phone numbers retrieved successfully.", [
                    'response' => $responseData
                ]);

                return [
                    'success' => true,
                    'data' => $responseData
                ];
            }

            $errorMessage = "Failed to get phone numbers.";
            if (isset($responseData['error']['message'])) {
                $errorMessage .= " Error: " . $responseData['error']['message'];
            } else {
                $errorMessage .= " HTTP Status: " . $response->status();
            }

            Log::error("WhatsAppCloudApiService: {$errorMessage}", [
                'response' => $responseData,
                'status_code' => $response->status()
            ]);

            return ['success' => false, 'error' => $errorMessage, 'data' => $responseData];

        } catch (\Exception $e) {
            Log::error("WhatsAppCloudApiService getPhoneNumbers Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Handles the response from the WhatsApp Cloud API.
     *
     * @param Response $response
     * @param string $actionDescription
     * @return array{success: bool, data: mixed, error?: string, message_id?: string}
     */
    protected function handleResponse(Response $response, string $actionDescription): array
    {
        $responseData = $response->json();

        if ($response->successful() && isset($responseData['messages']) && !empty($responseData['messages'])) {
            $messageId = $responseData['messages'][0]['id'] ?? null;
            
            Log::info("WhatsAppCloudApiService: {$actionDescription} sent successfully.", [
                'response' => $responseData,
                'message_id' => $messageId
            ]);
            
            return [
                'success' => true,
                'data' => $responseData,
                'message_id' => $messageId
            ];
        }

        $errorMessage = "Failed to send {$actionDescription}.";
        
        if (isset($responseData['error']['message'])) {
            $errorMessage .= " Error: " . $responseData['error']['message'];
        } elseif (!$response->successful()) {
            $errorMessage .= " HTTP Status: " . $response->status();
        }

        Log::error("WhatsAppCloudApiService: {$errorMessage}", [
            'response' => $responseData,
            'status_code' => $response->status()
        ]);
        
        return ['success' => false, 'error' => $errorMessage, 'data' => $responseData];
    }

    /**
     * Format a phone number to international format for WhatsApp Cloud API.
     * WhatsApp Cloud API expects numbers without + prefix.
     *
     * @param string $phoneNumber
     * @param string $defaultCountryCode
     * @return string|null
     */
    public static function formatPhoneNumber(string $phoneNumber, string $defaultCountryCode = '249'): ?string
    {
        if (empty(trim($phoneNumber))) {
            return null;
        }

        // Remove common characters like +, -, spaces, parentheses
        $cleanedNumber = preg_replace('/[^\d]/', '', $phoneNumber);

        // If it starts with 0, remove it (common for local numbers like 0991961111)
        if (str_starts_with($cleanedNumber, '0')) {
            $cleanedNumber = substr($cleanedNumber, 1);
        }

        // If it doesn't start with the default country code, prepend it
        if (!str_starts_with($cleanedNumber, $defaultCountryCode)) {
            $cleanedNumber = $defaultCountryCode . $cleanedNumber;
        }

        // Basic length check (country code + 8-10 digits)
        if (strlen($cleanedNumber) < 10 || strlen($cleanedNumber) > 15) {
            Log::warning("WhatsAppCloudApiService: Potentially invalid phone number format: {$phoneNumber} -> {$cleanedNumber}");
        }

        // WhatsApp Cloud API expects numbers without + prefix
        return $cleanedNumber;
    }
}

