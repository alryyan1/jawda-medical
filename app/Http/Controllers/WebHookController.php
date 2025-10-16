<?php

namespace App\Http\Controllers;

use App\Models\Doctorvisit;
use App\Models\BankakImage;
use App\Jobs\EmitBankakImageInsertedJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use App\Services\Pdf\LabResultReport;
use App\Services\UltramsgService;

class WebHookController extends Controller
{
	public function webhook(Request $request)
	{
		try {
			// Read raw body (works for UltraMsg and most webhooks)
			$raw = $request->getContent();
			if ($raw === '' || $raw === null) {
				$raw = @file_get_contents('php://input');
			}

			$event = json_decode($raw ?? 'null', true);

			// Log incoming webhook data
			Log::info('Webhook received', ['event' => $event]);

			if (!is_array($event)) {
				Log::warning('Webhook received but no event data found', ['raw_data' => $raw]);
				return response()->json(['ok' => true]);
			}

			// Persist raw log (optional)
			try {
				file_put_contents(base_path('storage/logs/webhook-ultramsg.log'), json_encode($event) . PHP_EOL, FILE_APPEND | LOCK_EX);
			} catch (\Throwable $e) {
				Log::warning('Failed writing webhook file log', ['error' => $e->getMessage()]);
			}

			// UltraMsg structure handling
			$from = $event['data']['from'] ?? null;
			$msg  = $event['data']['body'] ?? null;
			$type = $event['data']['type'] ?? null;
			$mediaId = $event['data']['mediaId'] ?? $event['data']['media'] ?? null;
			$mimeType = $event['data']['mimeType'] ?? null;
			$messageId = $event['data']['id'] ?? null;

			if (!$from) {
				Log::warning('Missing required fields in webhook', ['from' => $from, 'event' => $event]);
				return response()->json(['ok' => true]);
			}

			$from_sms = str_replace(['c.us', '@'], '', $from);
			Log::info('Processing message', ['from' => $from, 'message' => $msg, 'type' => $type, 'from_sms' => $from_sms]);

			// Handle image messages
			if ($type === 'image' && ($mediaId || $messageId)) {
				return $this->handleImageMessage($from_sms, $mediaId ?: $messageId, $mimeType, $event);
			}

			// If the message is numeric, treat it as a Doctorvisit id
			if (is_numeric($msg)) {
				try {
					$id = (int) $msg;
					$patient = Doctorvisit::find($id);

					if (!$patient) {
						Log::warning('Patient not found', ['id' => $id, 'from' => $from]);
                        // reply to sender via Ultramsg
                        $to = UltramsgService::formatPhoneNumber($from_sms);
                        if ($to) {
                            (new UltramsgService())->sendTextMessage($to, 'عذرا، لا يوجد مريض بهذا الكود');
                        }
						return response()->json(['ok' => true]);
					}

					$name = $patient->patient->name;
					$txt = <<<EOD
مرحبا بك عزيز الزائر
{$name}
سيتم ارسال النتيجه ...
EOD;

                    // Send welcome text via UltramsgService
                    $to = UltramsgService::formatPhoneNumber($from_sms);
                    if ($to) {
                        (new UltramsgService())->sendTextMessage($to, $txt);
                    }

                    // Check if results are ready before generating PDF
                    $labRequestIds = $patient->patientLabRequests->pluck('id');
                    $totalResultsCount = 0;
                    $pendingResultsCount = 0;
                    
                    if ($labRequestIds->isNotEmpty()) {
                        $totalResultsCount = \App\Models\RequestedResult::whereIn('lab_request_id', $labRequestIds)->count();
                        $pendingResultsCount = \App\Models\RequestedResult::whereIn('lab_request_id', $labRequestIds)
                            ->where(function ($query) {
                                $query->whereNull('result')
                                      ->orWhere('result', '=', '');
                            })
                            ->count();
                    }
                    
                    // Check if results are ready
                    $allResultsReady = ($totalResultsCount > 0 && $pendingResultsCount === 0);
                    
                    if (!$allResultsReady) {
                        Log::info('Results not ready for patient', [
                            'patient_id' => $id,
                            'total_results' => $totalResultsCount,
                            'pending_results' => $pendingResultsCount
                        ]);
                        
                        // Send clarification message
                        $clarificationMessage = <<<EOD
عذراً، النتائج غير جاهزة بعد
{$name}
عدد النتائج المطلوبة: {$totalResultsCount}
عدد النتائج المتبقية: {$pendingResultsCount}
يرجى المحاولة مرة أخرى لاحقاً
EOD;
                        
                        if ($to) {
                            (new UltramsgService())->sendTextMessage($to, $clarificationMessage);
                        }
                        
                        return response()->json(['ok' => true]);
                    }

                    // Generate PDF using LabResultReport service
                    $pdfContent = (new LabResultReport())->generate($patient, false, true);
                    
                    if (empty($pdfContent)) {
                        Log::error('PDF generation failed - empty content', ['patient_id' => $id]);
                        throw new \Exception('PDF generation failed');
                    }

                    // Store PDF to temporary file for sending
                    $filename = 'lab_' . $id . '_' . Str::uuid() . '.pdf';
                    $tempPath = storage_path('app/temp/' . $filename);
                    
                    // Ensure temp directory exists
                    $tempDir = dirname($tempPath);
                    if (!file_exists($tempDir)) {
                        if (!mkdir($tempDir, 0755, true)) {
                            Log::error('Failed to create temp directory', ['path' => $tempDir]);
                            throw new \Exception('Failed to create temp directory');
                        }
                    }
                    
                    // Write PDF content to temporary file
                    $bytesWritten = file_put_contents($tempPath, $pdfContent);
                    if ($bytesWritten === false) {
                        Log::error('Failed to write PDF to temp file', ['path' => $tempPath]);
                        throw new \Exception('Failed to write PDF to temp file');
                    }
                    
                    Log::info('PDF created successfully', [
                        'patient_id' => $id,
                        'filename' => $filename,
                        'size' => $bytesWritten,
                        'path' => $tempPath
                    ]);
                    
                    // Send PDF via UltramsgService using file path
                    if ($to) {
                        $result = (new UltramsgService())->sendDocumentFromFile($to, $tempPath, 'Lab Result');
                        
                        // Clean up temporary file
                        if (file_exists($tempPath)) {
                            unlink($tempPath);
                        }
                        
                        // Log the result
                        if ($result['success']) {
                            Log::info('PDF sent successfully via WhatsApp', [
                                'patient_id' => $id,
                                'to' => $to,
                                'message_id' => $result['message_id'] ?? null
                            ]);
                        } else {
                            Log::error('Failed to send PDF via WhatsApp', [
                                'patient_id' => $id,
                                'to' => $to,
                                'error' => $result['error'] ?? 'Unknown error',
                                'response' => $result['data'] ?? null
                            ]);
                        }
                    }

                    return response()->json(['ok' => true]);

				} catch (\Throwable $e) {
					Log::error('Error processing numeric message', [
						'id' => $msg,
						'from' => $from,
						'error' => $e->getMessage(),
						'trace' => $e->getTraceAsString(),
					]);
                    $to = UltramsgService::formatPhoneNumber($from_sms);
                    if ($to) {
                        (new UltramsgService())->sendTextMessage($to, 'عذراً، حدث خطأ في معالجة طلبك. يرجى المحاولة مرة أخرى.');
                    }
				}
			} else {
				// Non-numeric message => instruct user
				try {
                    $to = UltramsgService::formatPhoneNumber($from_sms);
                    if ($to) {
                        (new UltramsgService())->sendTextMessage($to, 'عذرا   ,, الرجاء ادخال الكود اعلاه فقط لاستلام النتيجة  ');
                    }
				} catch (\Throwable $e) {
					Log::error('Error sending error message', ['from' => $from, 'error' => $e->getMessage()]);
				}
			}

			return response()->json(['ok' => true]);

		} catch (\Throwable $e) {
			Log::error('Critical error in webhook', [
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString(),
			]);
			
			// Try to send error message back to sender if we have the phone number
			try {
				if (isset($from_sms) && !empty($from_sms)) {
					$to = UltramsgService::formatPhoneNumber($from_sms);
					if ($to) {
						(new UltramsgService())->sendTextMessage($to, 'عذراً، حدث خطأ في النظام. يرجى المحاولة مرة أخرى لاحقاً.');
					}
				}
			} catch (\Throwable $sendError) {
				Log::error('Failed to send error message to user', [
					'original_error' => $e->getMessage(),
					'send_error' => $sendError->getMessage()
				]);
			}
			
			return response()->json(['error' => 'An unexpected error occurred. Please check the logs.'], 500);
		}
	}

	/**
	 * Handle incoming image messages and save them to storage
	 * 
	 * Note: Ultramsg provides media URLs in webhook data when available.
	 * Images are downloaded from the provided S3 URL and stored in the bankak folder.
	 * If no media URL is provided, the user is informed about the limitation.
	 *
	 * @param string $from_sms
	 * @param string $mediaId
	 * @param string|null $mimeType
	 * @param array $event
	 * @return \Illuminate\Http\JsonResponse
	 */
	private function handleImageMessage(string $from_sms, string $mediaId, ?string $mimeType, array $event)
	{
		try {
			Log::info('Processing image message', [
				'from' => $from_sms,
				'mediaId' => $mediaId,
				'mimeType' => $mimeType,
				'event_data' => $event
			]);

			// Get UltramsgService instance to access configuration
			$ultramsgService = new UltramsgService();
			
			if (!$ultramsgService->isConfigured()) {
				Log::error('UltramsgService not configured for image download');
				return response()->json(['ok' => true]);
			}

			// Try to get media URL from the webhook data first
			$mediaUrl = $event['data']['media'] ?? null;
			
			if (!empty($mediaUrl)) {
				// If we have a direct media URL, download from it
				Log::info('Downloading image from direct media URL', ['url' => $mediaUrl]);
				$imageData = $this->downloadImageFromUrl($mediaUrl);
			} else {
				// Ultramsg doesn't provide media download API for webhook messages
				// This is a known limitation - we can only acknowledge receipt
				Log::info('Ultramsg webhook received image message but media download is not supported', [
					'messageId' => $mediaId,
					'from' => $from_sms,
					'note' => 'Ultramsg API does not provide media download for webhook messages'
				]);
				
				// Send a message to the user explaining the limitation
				$to = UltramsgService::formatPhoneNumber($from_sms);
				if ($to) {
					$limitationMessage = 'تم استلام الصورة، لكن لا يمكن حفظها حالياً. يرجى إرسال الصورة مرة أخرى أو التواصل معنا مباشرة.';
					(new UltramsgService())->sendTextMessage($to, $limitationMessage);
				}
				
				return response()->json(['ok' => true]);
			}
			
			if (!$imageData) {
				Log::error('Failed to download image from URL', ['mediaUrl' => $mediaUrl]);
				return response()->json(['ok' => true]);
			}

			// Determine file extension from mime type
			$extension = $this->getExtensionFromMimeType($mimeType);
			
			// Generate unique filename
			$filename = 'whatsapp_image_' . $from_sms . '_' . time() . '_' . Str::random(8) . '.' . $extension;
			
			// Store the image
			$storagePath = 'bankak/' . date('Y/m/d') . '/' . $filename;
			$stored = Storage::disk('public')->put($storagePath, $imageData);
			
			if (!$stored) {
				Log::error('Failed to store image to storage', ['filename' => $filename]);
				return response()->json(['ok' => true]);
			}

			// Log successful storage
			Log::info('Image stored successfully', [
				'from' => $from_sms,
				'mediaId' => $mediaId,
				'filename' => $filename,
				'storage_path' => $storagePath,
				'file_size' => strlen($imageData)
			]);

			// Save image record to database
			try {
				$bankakImage = BankakImage::create([
					'image_url' => $storagePath, // Store the relative path to the image
					'doctorvisit_id' => null, // Set to null for now as requested
					'phone' => $from_sms,
				]);

				Log::info('Image record saved to database', [
					'bankak_image_id' => $bankakImage->id,
					'phone' => $from_sms,
					'image_url' => $storagePath
				]);

				// Emit real-time event for new bankak image
				EmitBankakImageInsertedJob::dispatch($bankakImage->id);
			} catch (\Throwable $e) {
				Log::error('Failed to save image record to database', [
					'from' => $from_sms,
					'storage_path' => $storagePath,
					'error' => $e->getMessage()
				]);
			}

			// Send acknowledgment message
			$to = UltramsgService::formatPhoneNumber($from_sms);
			if ($to) {
				$ackMessage = 'تم استلام وحفظ الصورة بنجاح في بنك الصور. شكراً لك!';
				(new UltramsgService())->sendTextMessage($to, $ackMessage);
			}

			return response()->json(['ok' => true]);

		} catch (\Throwable $e) {
			Log::error('Error processing image message', [
				'from' => $from_sms,
				'mediaId' => $mediaId,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);

			// Try to send error message to user
			try {
				$to = UltramsgService::formatPhoneNumber($from_sms);
				if ($to) {
					(new UltramsgService())->sendTextMessage($to, 'عذراً، حدث خطأ في معالجة الصورة. يرجى المحاولة مرة أخرى.');
				}
			} catch (\Throwable $sendError) {
				Log::error('Failed to send error message for image processing', [
					'original_error' => $e->getMessage(),
					'send_error' => $sendError->getMessage()
				]);
			}

			return response()->json(['ok' => true]);
		}
	}


	/**
	 * Download image from a direct URL
	 *
	 * @param string $url
	 * @return string|null
	 */
	private function downloadImageFromUrl(string $url): ?string
	{
		try {
			// Use cURL directly to handle content encoding issues
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($ch, CURLOPT_TIMEOUT, 30);
			curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
			
			// Handle content encoding - don't set CURLOPT_ENCODING for base64
			curl_setopt($ch, CURLOPT_HTTPHEADER, [
				'Accept: image/*,*/*;q=0.8',
				'Accept-Language: en-US,en;q=0.5',
				'Connection: keep-alive',
			]);
			
			$imageData = curl_exec($ch);
			$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$error = curl_error($ch);
			curl_close($ch);
			
			if ($error) {
				Log::error('cURL error while downloading image from URL', [
					'url' => $url,
					'error' => $error
				]);
				return null;
			}
			
			if ($httpCode === 200 && $imageData !== false) {
				// Check if the content is base64 encoded (common with S3)
				$decodedData = $imageData;
				if (base64_encode(base64_decode($imageData, true)) === $imageData) {
					// Content is base64 encoded, decode it
					$decodedData = base64_decode($imageData);
					Log::info('Decoded base64 content from URL', [
						'url' => $url,
						'original_size' => strlen($imageData),
						'decoded_size' => strlen($decodedData)
					]);
				}
				
				// Check if it's a valid image by looking at file headers
				if (strlen($decodedData) > 10 && (
					strpos($decodedData, "\xFF\xD8\xFF") === 0 || // JPEG
					strpos($decodedData, "\x89PNG") === 0 || // PNG
					strpos($decodedData, "GIF8") === 0 // GIF
				)) {
					Log::info('Image downloaded successfully from URL', [
						'url' => $url,
						'size' => strlen($decodedData),
						'http_code' => $httpCode,
						'was_base64' => $decodedData !== $imageData
					]);
					return $decodedData;
				} else {
					Log::warning('Downloaded data from URL is not a valid image', [
						'url' => $url,
						'size' => strlen($decodedData),
						'http_code' => $httpCode,
						'was_base64' => $decodedData !== $imageData,
						'first_bytes' => bin2hex(substr($decodedData, 0, 10))
					]);
				}
			} else {
				Log::error('Failed to download image from URL', [
					'url' => $url,
					'http_code' => $httpCode,
					'response_size' => strlen($imageData)
				]);
			}
			
			return null;
			
		} catch (\Throwable $e) {
			Log::error('Exception while downloading image from URL', [
				'url' => $url,
				'error' => $e->getMessage()
			]);
			return null;
		}
	}

	/**
	 * Get file extension from MIME type
	 *
	 * @param string|null $mimeType
	 * @return string
	 */
	private function getExtensionFromMimeType(?string $mimeType): string
	{
		$mimeToExtension = [
			'image/jpeg' => 'jpg',
			'image/jpg' => 'jpg',
			'image/png' => 'png',
			'image/gif' => 'gif',
			'image/webp' => 'webp',
			'image/bmp' => 'bmp'
		];

		return $mimeToExtension[$mimeType] ?? 'jpg';
	}
}


