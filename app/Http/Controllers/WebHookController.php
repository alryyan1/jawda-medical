<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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

			if (!$from) {
				Log::warning('Missing required fields in webhook', ['from' => $from, 'event' => $event]);
				return response()->json(['ok' => true]);
			}

			$from_sms = str_replace(['c.us', '@'], '', $from);
			Log::info('Processing message', ['from' => $from, 'message' => $msg, 'type' => $type, 'from_sms' => $from_sms]);

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
					$settings = Setting::first();

					// Send document from Firebase
					$request = new Request(['visit_id' => (string) $id, 'phone' => $from_sms, ]);
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

}


