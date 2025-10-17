<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    private $apiKey = 'AIzaSyDux8HjIUF9SE3DNFkIloJ2GQHlTemZ8MQ';
    private $baseUrl = 'https://generativelanguage.googleapis.com/v1beta';

    /**
     * Analyze an image using Gemini API
     *
     * @param string $imageUrl
     * @param string $prompt
     * @return array
     */
    public function analyzeImage(string $imageUrl, string $prompt = 'استخرج المبلغ فقط'): array
    {
        try {
            // Convert image to base64
            $imageData = $this->convertImageToBase64($imageUrl);
            
            if (!$imageData) {
                return [
                    'success' => false,
                    'error' => 'Failed to convert image to base64'
                ];
            }

            $requestBody = [
                'contents' => [
                    [
                        'parts' => [
                            [
                                'text' => $prompt
                            ],
                            [
                                'inline_data' => [
                                    'mime_type' => $imageData['mime_type'],
                                    'data' => $imageData['base64']
                                ]
                            ]
                        ]
                    ]
                ],
                'generationConfig' => [
                    'temperature' => 0.4,
                    'topK' => 32,
                    'topP' => 1,
                    'maxOutputTokens' => 4096,
                ]
            ];

            // Try different models
            $models = ['gemini-2.5-flash', 'gemini-pro-vision', 'gemini-1.5-flash'];
            
            foreach ($models as $model) {
                try {
                    $response = Http::withHeaders([
                        'Content-Type' => 'application/json',
                        'x-goog-api-key' => $this->apiKey,
                    ])->timeout(30)->post(
                        "{$this->baseUrl}/models/{$model}:generateContent",
                        $requestBody
                    );

                    if ($response->successful()) {
                        $data = $response->json();
                        Log::debug('Gemini raw response', [
                            'model' => $model,
                            'response' => $data
                        ]);
                        
                        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                            $analysis = $data['candidates'][0]['content']['parts'][0]['text'];
                            
                            Log::info('Gemini analysis successful', [
                                'model' => $model,
                                'analysis' => $analysis
                            ]);
                            
                            return [
                                'success' => true,
                                'data' => [
                                    'analysis' => $analysis
                                ]
                            ];
                        }
                    } else {
                        Log::warning('Gemini API request failed', [
                            'model' => $model,
                            'status' => $response->status(),
                            'response' => $response->body()
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::warning('Gemini API request exception', [
                        'model' => $model,
                        'error' => $e->getMessage()
                    ]);
                    continue;
                }
            }

            return [
                'success' => false,
                'error' => 'All Gemini models failed to respond'
            ];

        } catch (\Exception $e) {
            Log::error('Gemini service error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Convert image URL to base64
     *
     * @param string $imageUrl
     * @return array|null
     */
    private function convertImageToBase64(string $imageUrl): ?array
    {
        try {
            // Handle relative URLs by converting to full URL
            if (strpos($imageUrl, 'http') !== 0) {
                $imageUrl = url('storage/' . $imageUrl);
            }

            $response = Http::timeout(30)->get($imageUrl);
            
            if ($response->successful()) {
                $imageData = $response->body();
                $base64 = base64_encode($imageData);
                
                // Determine MIME type
                $mimeType = $this->getMimeTypeFromImageData($imageData);
                
                return [
                    'base64' => $base64,
                    'mime_type' => $mimeType
                ];
            }

            Log::error('Failed to fetch image for base64 conversion', [
                'url' => $imageUrl,
                'status' => $response->status()
            ]);

            return null;

        } catch (\Exception $e) {
            Log::error('Error converting image to base64', [
                'url' => $imageUrl,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Get MIME type from image data
     *
     * @param string $imageData
     * @return string
     */
    private function getMimeTypeFromImageData(string $imageData): string
    {
        // Check image headers
        if (strpos($imageData, "\xFF\xD8\xFF") === 0) {
            return 'image/jpeg';
        } elseif (strpos($imageData, "\x89PNG") === 0) {
            return 'image/png';
        } elseif (strpos($imageData, "GIF8") === 0) {
            return 'image/gif';
        } elseif (strpos($imageData, "RIFF") === 0 && strpos($imageData, "WEBP", 8) === 8) {
            return 'image/webp';
        }

        // Default to JPEG
        return 'image/jpeg';
    }
}
