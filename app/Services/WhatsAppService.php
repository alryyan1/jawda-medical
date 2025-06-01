<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\Response; // For type hinting

class WhatsAppService
{
    protected string $baseUrl; // This might still come from config/services.php or be hardcoded if it never changes
    protected ?string $instanceId;
    protected ?string $token;
    protected string $defaultCountryCode;

    public function __construct()
    {
        // $appSettings = Setting::instance(); // Your helper to get the settings model instance
        // If Setting::instance() is not a static method, you might need to resolve it via the service container
        // For simplicity, assuming you can get it:
        $appSettings = Setting::first(); // Or your method to get the single settings record

        $this->baseUrl = config('services.waapi.base_url'); // WAAPI base URL is less likely to change per clinic instance
        $this->instanceId =  config('services.waapi.instance_id') ?? $appSettings?->instance_id;
        $this->token =  config('services.waapi.token') ?? $appSettings?->token; // DECRYPT if stored encrypted
        $this->defaultCountryCode = $appSettings?->whatsapp_default_country_code ?? config('services.waapi.default_country_code', '249');
    }

    /**
     * Check if the WhatsApp service is configured.
     */
    public function isConfigured(): bool
    {
        return !empty($this->instanceId) && !empty($this->token) && !empty($this->baseUrl);
    }

    /**
     * Get the configured instance ID.
     */
    public function getInstanceId(): ?string
    {
        return $this->instanceId;
    }

    /**
     * Get the configured token.
     */
    public function getToken(): ?string
    {
        return $this->token;
    }


    /**
     * Sends a text message.
     *
     * @param string $chatId Example: "249912345678@c.us"
     * @param string $message
     * @return array{success: bool, data: mixed, error?: string}
     */
    public function sendTextMessage(string $chatId, string $message): array
    {
        if (!$this->isConfigured()) {
            Log::error('WhatsAppService: Service not configured (Instance ID or Token missing).');
            return ['success' => false, 'error' => 'WhatsApp service not configured.', 'data' => null];
        }

        $endpoint = "{$this->baseUrl}/{$this->instanceId}/client/action/send-message";
        
        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, [
                    'chatId' => $chatId,
                    'message' => $message,
                ]);

            return $this->handleResponse($response, 'Text message');

        } catch (\Exception $e) {
            Log::error("WhatsAppService sendTextMessage Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Sends a media message (e.g., PDF, image).
     *
     * @param string $chatId
     * @param string $mediaBase64 Base64 encoded media content
     * @param string $mediaName Filename with extension
     * @param string|null $mediaCaption
     * @param bool $asDocument
     * @return array{success: bool, data: mixed, error?: string}
     */
    public function sendMediaMessage(
        string $chatId,
        string $mediaBase64,
        string $mediaName,
        ?string $mediaCaption = null,
        bool $asDocument = false // Set to true for PDFs, false for images to be sent as images
    ): array {
        if (!$this->isConfigured()) {
            Log::error('WhatsAppService: Service not configured.');
            return ['success' => false, 'error' => 'WhatsApp service not configured.', 'data' => null];
        }

        $endpoint = "{$this->baseUrl}/{$this->instanceId}/client/action/send-media";
        $payload = [
            'chatId' => $chatId,
            'mediaBase64' => $mediaBase64,
            'mediaName' => $mediaName,
            'asDocument' => $asDocument,
        ];
        if ($mediaCaption) {
            $payload['mediaCaption'] = $mediaCaption;
        }

        try {
            $response = Http::withToken($this->token)
                ->acceptJson()
                ->asJson()
                ->post($endpoint, $payload);
            
            return $this->handleResponse($response, 'Media message');

        } catch (\Exception $e) {
            Log::error("WhatsAppService sendMediaMessage Exception: " . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage(), 'data' => null];
        }
    }

    /**
     * Handles the response from the waapi.app API.
     *
     * @param \Illuminate\Http\Client\Response $response
     * @param string $actionDescription
     * @return array{success: bool, data: mixed, error?: string}
     */
    protected function handleResponse(Response $response, string $actionDescription): array
    {
        if ($response->successful() && isset($response->json()['status']) && $response->json()['status'] === 'success') {
            Log::info("WhatsAppService: {$actionDescription} sent successfully.", ['response' => $response->json()]);
            return ['success' => true, 'data' => $response->json()['data'] ?? $response->json()];
        }
        
        $errorMessage = "Failed to send {$actionDescription}.";
        $responseData = $response->json();

        if (isset($responseData['message'])) {
            $errorMessage .= " Error: " . $responseData['message'];
        } elseif (isset($responseData['error']['message'])) {
             $errorMessage .= " Error: " . $responseData['error']['message'];
        } elseif (!$response->successful()) {
            $errorMessage .= " Status: " . $response->status();
        }
        
        Log::error("WhatsAppService: {$errorMessage}", ['response' => $responseData, 'status_code' => $response->status()]);
        return ['success' => false, 'error' => $errorMessage, 'data' => $responseData];
    }

    /**
     * Formats a phone number to the E.164 like format with @c.us for waapi.
     * Example: 249912345678@c.us
     * This needs to be robust based on your stored phone number formats.
     *
     * @param string $phoneNumber
     * @param string $defaultCountryCode Example '249' for Sudan
     * @return string|null
     */
    public static function formatPhoneNumberForWaApi(string $phoneNumber, string $defaultCountryCode = '249'): ?string
    {
        if (empty(trim($phoneNumber))) {
            return null;
        }

        // Remove common characters like +, -, spaces, parentheses
        $cleanedNumber = preg_replace('/[^\d]/', '', $phoneNumber);

        // If it starts with 0, remove it (common for local numbers)
        if (str_starts_with($cleanedNumber, '0')) {
            $cleanedNumber = substr($cleanedNumber, 1);
        }

        // If it doesn't start with the default country code, prepend it
        if (!str_starts_with($cleanedNumber, $defaultCountryCode)) {
            $cleanedNumber = $defaultCountryCode . $cleanedNumber;
        }
        
        // Basic length check (e.g. Sudan mobile numbers are 9 digits after country code)
        // Country code (3) + local number (9) = 12 digits
        if (strlen($cleanedNumber) < 10 || strlen($cleanedNumber) > 15) { // Adjust min/max length as needed
            Log::warning("WhatsAppService: Potentially invalid phone number format after cleaning: {$phoneNumber} -> {$cleanedNumber}");
            // return null; // Or attempt to send anyway
        }

        return $cleanedNumber . '@c.us';
    }
}