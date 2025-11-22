<?php

namespace App\Services;

use App\Services\Contracts\SmsClient;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AirtelSmsClient implements SmsClient
{
    private string $baseUrl;
    private string $endpoint;
    private string $apiKey;
    private string $defaultSender;
    private int $timeoutSeconds;

    public function __construct()
    {
        $config = config('services.airtel_sms');
        $this->baseUrl = rtrim((string)($config['base_url'] ?? 'https://www.airtel.sd'), '/');
        $this->endpoint = '/' . ltrim((string)($config['endpoint'] ?? '/api/rest_send_sms/'), '/');
        $this->apiKey = (string)($config['api_key'] ?? '');
        $this->defaultSender = (string)($config['default_sender'] ?? 'Jawda');
        $this->timeoutSeconds = (int)($config['timeout'] ?? 10);
    }

    public function send(string $to, string $message, bool $isOtp = false, ?string $sender = null): array
    {
        $senderId = $sender ?: $this->defaultSender;
        
        // Note: Sender ID must be registered with Airtel SMS service
        // If you get "Invalid sender" error, contact Airtel to register your sender ID
        // Sender ID format: typically 3-11 alphanumeric characters
    
        $messageData = [
            'to' => $to,
            'message' => $message,
        ];
        
        // Only include is_otp if it's true
        if ($isOtp) {
            $messageData['is_otp'] = true;
        }
        
        $payload = [
            'sender' => $senderId,
            'messages' => [$messageData],
        ];

        $response = $this->request($payload);
        return $this->normalizeSingleResponse($to, $response);
    }

    public function sendBulk(array $messages, ?string $sender = null): array
    {
        $senderId = $sender ?: $this->defaultSender;
        
    
        $normalized = [];
        foreach ($messages as $msg) {
            $messageData = [
                'to' => (string)$msg['to'],
                'message' => (string)$msg['message'],
            ];
            
            // Only include is_otp if it's true
            if (!empty($msg['is_otp'])) {
                $messageData['is_otp'] = true;
            }
            
            $normalized[] = $messageData;
        }

        $payload = [
            'sender' => $senderId,
            'messages' => $normalized,
        ];

        $response = $this->request($payload);
        return $this->normalizeBulkResponse($response);
    }

    private function request(array $payload)
    {
        $url = $this->baseUrl . $this->endpoint;
        Log::debug('SMS Payload', $payload);
        Log::debug('SMS URL', ['url' => $url, 'api_key' => $this->apiKey]);
        
        $http = Http::timeout($this->timeoutSeconds)
            ->acceptJson()
            ->withHeaders([
                'X-API-KEY' => $this->apiKey,
                'Content-Type' => 'application/json',
            ]);

        $response = $http->post($url, $payload);
        
        // Log the response for debugging
        Log::debug('SMS Response', [
            'status' => $response->status(),
            'successful' => $response->successful(),
            'body' => $response->body(),
            'json' => $response->json(),
        ]);
        
        return $response;
    }

    private function normalizeSingleResponse(string $to, $response): array
    {
        $json = $response->json();
        
        // Check if API returned an error response (even with 200 status)
        if (is_array($json) && isset($json['status']) && strtolower($json['status']) === 'failed') {
            $errorMessage = $json['detail'] ?? $json['message'] ?? $response->body();
            return [
                'success' => false,
                'error' => $errorMessage,
                'raw' => $json,
            ];
        }
        
        if (!$response->successful()) {
            $errorMessage = $json['detail'] ?? $json['message'] ?? $response->body();
            return [
                'success' => false,
                'error' => $errorMessage,
                'raw' => $json,
            ];
        }

        $success = true;
        $providerId = null;

        if (is_array($json)) {
            // Handle response shape:
            // {
            //   "status":"completed",
            //   "results":[{"to":"249...","status":"sent","units":1,"apiMsgId":197589}],
            //   "total_units":1
            // }
            if (isset($json['results'][0])) {
                $first = $json['results'][0];
                $providerId = $first['apiMsgId'] ?? ($first['message_id'] ?? null);
                $msgStatus = strtolower((string)($first['status'] ?? 'sent'));
                $success = in_array($msgStatus, ['sent', 'queued', 'delivered', 'success', 'ok'], true);
            } else {
                // Fallbacks for other shapes
                $providerId = $json['apiMsgId'] ?? ($json['message_id'] ?? ($json['data']['message_id'] ?? null));
                $overall = strtolower((string)($json['status'] ?? 'completed'));
                $success = in_array($overall, ['completed', 'success', 'ok'], true);
            }
        }

        return [
            'success' => (bool)$success,
            'provider_id' => $providerId,
            'raw' => $json,
        ];
    }

    private function normalizeBulkResponse($response): array
    {
        $json = $response->json();
        
        // Check if API returned an error response (even with 200 status)
        if (is_array($json) && isset($json['status']) && strtolower($json['status']) === 'failed') {
            $errorMessage = $json['detail'] ?? $json['message'] ?? $response->body();
            return [
                'success' => false,
                'error' => $errorMessage,
                'results' => [],
                'raw' => $json,
            ];
        }
        
        if (!$response->successful()) {
            $errorMessage = $json['detail'] ?? $json['message'] ?? $response->body();
            return [
                'success' => false,
                'error' => $errorMessage,
                'results' => [],
                'raw' => $json,
            ];
        }

        $results = [];
        // Map provider results
        if (isset($json['results']) && is_array($json['results'])) {
            foreach ($json['results'] as $item) {
                $msgStatus = strtolower((string)($item['status'] ?? 'sent'));
                $isSuccess = isset($item['success']) ? (bool)$item['success'] : in_array($msgStatus, ['sent', 'queued', 'delivered', 'success', 'ok'], true);
                $results[] = [
                    'to' => (string)($item['to'] ?? ''),
                    'success' => $isSuccess,
                    'provider_id' => $item['apiMsgId'] ?? ($item['message_id'] ?? null),
                    'error' => $item['error'] ?? null,
                ];
            }
        }

        return [
            'success' => empty($results) ? true : !collect($results)->contains(fn($r) => $r['success'] === false),
            'results' => $results,
            'raw' => $json,
        ];
    }
}


