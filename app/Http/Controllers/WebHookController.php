<?php

namespace App\Http\Controllers;

use App\Models\Doctorvisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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

			if (!$from || !$msg) {
				Log::warning('Missing required fields in webhook', ['from' => $from, 'msg' => $msg, 'event' => $event]);
				return response()->json(['ok' => true]);
			}

			$from_sms = str_replace(['c.us', '@'], '', $from);
			Log::info('Processing message', ['from' => $from, 'message' => $msg, 'from_sms' => $from_sms]);

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
                    $pdfContent = (new LabResultReport())->generate($patient, false);
                    
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
}


