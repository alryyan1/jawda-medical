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
use App\Services\GeminiService;

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

			// Handle "بنكك اليوم" message
			if ($msg === 'بنكك اليوم') {
				return $this->handleBankakTodayMessage($from_sms);
			}

			// Handle "الاشعارات" message
			if ($msg === 'الاشعارات') {
				return $this->handleNotificationsMessage($from_sms);
			}

			// If the message is numeric, treat it as a Doctorvisit id
			if (is_numeric($msg)) {
				try {
					$id = (int) $msg;

					$txt = <<<EOD
مرحبا بك عزيز الزائر
سيتم ارسال النتيجه ...
EOD;

					// Send welcome message first
					$to = UltramsgService::formatPhoneNumber($from_sms);
					if ($to) {
						(new UltramsgService())->sendTextMessage($to, $txt);
					}

					// Send document from Firebase
					$request = new Request(['visit_id' => (string) $id, 'phone' => $from_sms]);
					$ultramsgController = app(UltramsgController::class);
					$result = $ultramsgController->sendDocumentFromFirebase($request);

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

	/**
	 * Handle "بنكك اليوم" message - fetch today's images and analyze amounts
	 *
	 * @param string $from_sms
	 * @return \Illuminate\Http\JsonResponse
	 */
	private function handleBankakTodayMessage(string $from_sms)
	{
		try {
			Log::info('Processing "بنكك اليوم" request', ['from' => $from_sms]);

			// Send immediate preparing message to the user
			$to = UltramsgService::formatPhoneNumber($from_sms);
			if ($to) {
				(new UltramsgService())->sendTextMessage($to, 'نقوم الآن بتحضير وجمع المعلومات، يرجى الانتظار قليلًا...');
			}

			// Fetch all images for today
			$today = now()->format('Y-m-d');
			$todayImages = BankakImage::whereDate('created_at', $today)->get();

			if ($todayImages->isEmpty()) {
				$message = 'لا توجد صور في بنك الصور لهذا اليوم';
				$to = UltramsgService::formatPhoneNumber($from_sms);
				if ($to) {
					(new UltramsgService())->sendTextMessage($to, $message);
				}
				return response()->json(['ok' => true]);
			}

			Log::info('Found images for today', [
				'count' => $todayImages->count(),
				'date' => $today
			]);

			// Initialize Gemini service
			$geminiService = new GeminiService();
			$amounts = [];
			$totalAmount = 0;

			// Analyze each image
			foreach ($todayImages as $index => $image) {
				try {
					// Get full image URL
					$imageUrl = url('storage/' . $image->image_url);
					
					Log::info('Analyzing image', [
						'index' => $index + 1,
						'image_id' => $image->id,
						'url' => $imageUrl
					]);

					// Analyze image with Gemini
					$result = $geminiService->analyzeImage($imageUrl, 'استخرج المبلغ فقط');
					
					if ($result['success'] && isset($result['data']['analysis'])) {
						$analysis = $result['data']['analysis'];
						
						// Extract numeric amount from analysis
						$amount = $this->extractAmountFromAnalysis($analysis);
						
						if ($amount > 0) {
							$amounts[] = [
								'notification_number' => $index + 1,
								'amount' => $amount,
								'analysis' => $analysis
							];
							$totalAmount += $amount;
						}
						
						Log::info('Image analysis completed', [
							'image_id' => $image->id,
							'analysis' => $analysis,
							'extracted_amount' => $amount
						]);
					} else {
						Log::warning('Failed to analyze image', [
							'image_id' => $image->id,
							'error' => $result['error'] ?? 'Unknown error'
						]);
					}
				} catch (\Exception $e) {
					Log::error('Error analyzing individual image', [
						'image_id' => $image->id,
						'error' => $e->getMessage()
					]);
				}
			}

			// Format response message
			$message = $this->formatBankakTodayMessage($amounts, $totalAmount);
			
			// Send message to user
			$to = UltramsgService::formatPhoneNumber($from_sms);
			if ($to) {
				(new UltramsgService())->sendTextMessage($to, $message);
			}

			Log::info('Bankak today message sent', [
				'from' => $from_sms,
				'total_images' => $todayImages->count(),
				'analyzed_amounts' => count($amounts),
				'total_amount' => $totalAmount
			]);

			return response()->json(['ok' => true]);

		} catch (\Exception $e) {
			Log::error('Error processing "بنكك اليوم" message', [
				'from' => $from_sms,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);

			// Send error message to user
			try {
				$to = UltramsgService::formatPhoneNumber($from_sms);
				if ($to) {
					(new UltramsgService())->sendTextMessage($to, 'عذراً، حدث خطأ في معالجة طلب "بنكك اليوم". يرجى المحاولة مرة أخرى.');
				}
			} catch (\Exception $sendError) {
				Log::error('Failed to send error message for bankak today', [
					'original_error' => $e->getMessage(),
					'send_error' => $sendError->getMessage()
				]);
			}

			return response()->json(['ok' => true]);
		}
	}

	/**
	 * Extract numeric amount from Gemini analysis text
	 *
	 * @param string $analysis
	 * @return float
	 */
	private function extractAmountFromAnalysis(string $analysis): float
	{
		// Normalize Arabic-Indic digits to Western digits
		$digitsMap = [
			'٠' => '0','١' => '1','٢' => '2','٣' => '3','٤' => '4',
			'٥' => '5','٦' => '6','٧' => '7','٨' => '8','٩' => '9',
			'۰' => '0','۱' => '1','۲' => '2','۳' => '3','۴' => '4',
			'۵' => '5','۶' => '6','۷' => '7','۸' => '8','۹' => '9'
		];
		$normalized = strtr($analysis, $digitsMap);

		// Keep only digits and separators
		$onlyNums = preg_replace('/[^0-9.,]/u', '', $normalized);
		if ($onlyNums === null) {
			return 0.0;
		}

		// Decide on decimal vs thousands separators
		$hasDot = strpos($onlyNums, '.') !== false;
		$hasComma = strpos($onlyNums, ',') !== false;

		$candidate = $onlyNums;
		if ($hasDot && $hasComma) {
			// Assume comma is thousands, dot is decimal
			$candidate = str_replace(',', '', $candidate);
		} elseif ($hasComma && !$hasDot) {
			// Only comma present: determine if it's thousands (groups of 3) or decimal (1-2 digits at end)
			if (preg_match('/,\d{1,2}$/', $candidate)) {
				// Treat as decimal comma -> convert to dot
				$candidate = str_replace(',', '.', $candidate);
			} else {
				// Treat as thousands -> remove
				$candidate = str_replace(',', '', $candidate);
			}
		} else {
			// Only dot or no separator -> leave as is
		}

		// If there are multiple dots, keep only the last as decimal and remove others
		if (substr_count($candidate, '.') > 1) {
			$lastDotPos = strrpos($candidate, '.');
			$candidate = str_replace('.', '', substr($candidate, 0, $lastDotPos)) . substr($candidate, $lastDotPos);
		}

		// If there's no decimal context, remove all dots (treat as thousands)
		if (strpos($candidate, '.') !== false && !preg_match('/\.\d{1,2}$/', $candidate)) {
			$candidate = str_replace('.', '', $candidate);
		}

		// Finally, extract the longest numeric sequence possibly with a single decimal
		if (preg_match_all('/\d+(?:\.\d{1,2})?/', $candidate, $matches)) {
			$numbers = $matches[0];
			usort($numbers, static function ($a, $b) { return strlen($b) <=> strlen($a); });
			$number = $numbers[0];
			return (float) $number;
		}

		return 0.0;
	}

	/**
	 * Format the bankak today message
	 *
	 * @param array $amounts
	 * @param float $totalAmount
	 * @return string
	 */
	private function formatBankakTodayMessage(array $amounts, float $totalAmount): string
	{
		$message = "بسم الله الرحمن الرحيم\n\n";
		
		if (empty($amounts)) {
			$message .= "لا توجد مبالغ محددة في الصور لهذا اليوم";
		} else {
			foreach ($amounts as $item) {
				$message .= "الاشعار رقم {$item['notification_number']} المبلغ يساوي {$item['amount']}\n";
			}
			
			$message .= "\nالمجموع {$totalAmount}";
		}
		
		return $message;
	}

	/**
	 * Handle "الاشعارات" message - send today's images back to user
	 *
	 * @param string $from_sms
	 * @return \Illuminate\Http\JsonResponse
	 */
	private function handleNotificationsMessage(string $from_sms)
	{
		try {
			Log::info('Processing "الاشعارات" request', ['from' => $from_sms]);

			// Send immediate preparing message to the user
			$to = UltramsgService::formatPhoneNumber($from_sms);
			if ($to) {
				(new UltramsgService())->sendTextMessage($to, 'نقوم الآن بجمع صور الاشعارات لهذا اليوم، يرجى الانتظار قليلًا...');
			}

			// Fetch all images for today
			$today = now()->format('Y-m-d');
			$todayImages = BankakImage::whereDate('created_at', $today)->get();

			if ($todayImages->isEmpty()) {
				$message = 'لا توجد صور اشعارات في بنك الصور لهذا اليوم';
				$to = UltramsgService::formatPhoneNumber($from_sms);
				if ($to) {
					(new UltramsgService())->sendTextMessage($to, $message);
				}
				return response()->json(['ok' => true]);
			}

			Log::info('Found images for notifications', [
				'count' => $todayImages->count(),
				'date' => $today
			]);

			// Send each image back to the user
			$sentCount = 0;
			foreach ($todayImages as $index => $image) {
				try {
					// Get full image URL
					$imageUrl = url('storage/' . $image->image_url);
					
					Log::info('Sending image back to user', [
						'index' => $index + 1,
						'image_id' => $image->id,
						'url' => $imageUrl
					]);

					// Send image directly via URL using the new image method
					$to = UltramsgService::formatPhoneNumber($from_sms);
					if ($to) {
						$result = (new UltramsgService())->sendImageFromUrl($to, $imageUrl, 'اشعار ' . ($index + 1));
						
						if ($result['success']) {
							$sentCount++;
							Log::info('Image sent successfully', [
								'image_id' => $image->id,
								'index' => $index + 1,
								'message_id' => $result['message_id'] ?? null
							]);
						} else {
							Log::error('Failed to send image', [
								'image_id' => $image->id,
								'index' => $index + 1,
								'error' => $result['error'] ?? 'Unknown error'
							]);
						}
					}
					
					// Add small delay between images to avoid rate limiting
					sleep(1);
					
				} catch (\Exception $e) {
					Log::error('Error sending individual image', [
						'image_id' => $image->id,
						'index' => $index + 1,
						'error' => $e->getMessage()
					]);
				}
			}

			// Send summary message
			$summaryMessage = "تم إرسال {$sentCount} من أصل " . $todayImages->count() . " صورة اشعارات لهذا اليوم";
			$to = UltramsgService::formatPhoneNumber($from_sms);
			if ($to) {
				(new UltramsgService())->sendTextMessage($to, $summaryMessage);
			}

			Log::info('Notifications message processing completed', [
				'from' => $from_sms,
				'total_images' => $todayImages->count(),
				'sent_images' => $sentCount
			]);

			return response()->json(['ok' => true]);

		} catch (\Exception $e) {
			Log::error('Error processing "الاشعارات" message', [
				'from' => $from_sms,
				'error' => $e->getMessage(),
				'trace' => $e->getTraceAsString()
			]);

			// Send error message to user
			try {
				$to = UltramsgService::formatPhoneNumber($from_sms);
				if ($to) {
					(new UltramsgService())->sendTextMessage($to, 'عذراً، حدث خطأ في معالجة طلب "الاشعارات". يرجى المحاولة مرة أخرى.');
				}
			} catch (\Exception $sendError) {
				Log::error('Failed to send error message for notifications', [
					'original_error' => $e->getMessage(),
					'send_error' => $sendError->getMessage()
				]);
			}

			return response()->json(['ok' => true]);
		}
	}
}


