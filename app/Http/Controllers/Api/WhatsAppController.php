<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use App\Models\Setting; // To get instanceId and token if not passed
use App\Models\Patient; // To get patient phone number
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File; // For handling file path if PDF is generated on server

class WhatsAppController extends Controller
{
    protected WhatsAppService $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
        // Add middleware for permissions if needed
        // $this->middleware('can:send whatsapp_messages');
    }

    /**
     * Send a text message.
     */
    public function sendText(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required_without:chat_id|exists:patients,id',
            'chat_id' => 'required_without:patient_id|string', // E.g., 249xxxxxxxxx@c.us
            'message' => 'required|string|max:4096', // Max length for WhatsApp messages
            // 'template_id' => 'nullable|string', // If you use templates stored in your DB
        ]);

        if (!$this->whatsAppService->isConfigured()) {
            return response()->json(['message' => 'خدمة الواتساب غير مهيأة في الإعدادات.'], 503); // Service Unavailable
        }

        $chatId = $validated['chat_id'] ?? null;
        if (isset($validated['patient_id'])) {
            $patient = Patient::find($validated['patient_id']);
            if (!$patient || !$patient->phone) {
                return response()->json(['message' => 'رقم هاتف المريض غير موجود أو غير صالح.'], 422);
            }
            $chatId = WhatsAppService::formatPhoneNumberForWaApi($patient->phone);
            if (!$chatId) {
                 return response()->json(['message' => 'تعذر تهيئة رقم هاتف المريض لإرسال الواتساب.'], 422);
            }
        }
        
        if (!$chatId) {
             return response()->json(['message' => 'معرف الدردشة أو بيانات المريض مطلوبة.'], 422);
        }

        // Here you could fetch a template content from DB if template_id is provided
        // and replace placeholders.
        // $messageToSend = $this->processMessageTemplate($validated['message'], $patient ?? null);

        $result = $this->whatsAppService->sendTextMessage($chatId, $validated['message']);

        if ($result['success']) {
            return response()->json(['message' => 'تم إرسال الرسالة النصية بنجاح.', 'data' => $result['data']]);
        } else {
            return response()->json(['message' => $result['error'] ?? 'فشل إرسال الرسالة النصية.', 'details' => $result['data']], 500);
        }
    }

    /**
     * Send a media message (e.g., PDF report from base64 or server path).
     */
    public function sendMedia(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required_without:chat_id|exists:patients,id',
            'chat_id' => 'required_without:patient_id|string',
            'media_base64' => 'required_without:media_path|string', // Base64 encoded content
            'media_path' => 'required_without:media_base64|string', // Path to a file on the server
            'media_name' => 'required|string', // e.g., "lab_report.pdf"
            'media_caption' => 'nullable|string|max:1000',
            'as_document' => 'sometimes|boolean',
        ]);

        if (!$this->whatsAppService->isConfigured()) {
            return response()->json(['message' => 'خدمة الواتساب غير مهيأة في الإعدادات.'], 503);
        }

        $chatId = $validated['chat_id'] ?? null;
        if (isset($validated['patient_id'])) {
            $patient = Patient::find($validated['patient_id']);
            if (!$patient || !$patient->phone) {
                return response()->json(['message' => 'رقم هاتف المريض غير موجود أو غير صالح.'], 422);
            }
            $chatId = WhatsAppService::formatPhoneNumberForWaApi($patient->phone);
             if (!$chatId) {
                 return response()->json(['message' => 'تعذر تهيئة رقم هاتف المريض لإرسال الواتساب.'], 422);
            }
        }
        
        if (!$chatId) {
             return response()->json(['message' => 'معرف الدردشة أو بيانات المريض مطلوبة.'], 422);
        }

        $mediaBase64 = $validated['media_base64'] ?? null;
        if (isset($validated['media_path'])) {
            if (!File::exists(storage_path('app/' . $validated['media_path']))) { // Assuming path is relative to storage/app
                return response()->json(['message' => 'ملف الوسائط المحدد غير موجود.'], 404);
            }
            $mediaBase64 = base64_encode(File::get(storage_path('app/' . $validated['media_path'])));
        }

        if (!$mediaBase64) {
             return response()->json(['message' => 'محتوى الوسائط مطلوب (base64 أو مسار الملف).'], 422);
        }

        $result = $this->whatsAppService->sendMediaMessage(
            $chatId,
            $mediaBase64,
            $validated['media_name'],
            $validated['media_caption'] ?? null,
            $validated['as_document'] ?? true // Default to true for things like PDFs
        );

        if ($result['success']) {
            return response()->json(['message' => 'تم إرسال الوسائط بنجاح.', 'data' => $result['data']]);
        } else {
            return response()->json(['message' => $result['error'] ?? 'فشل إرسال الوسائط.', 'details' => $result['data']], 500);
        }
    }

    // Placeholder for message template processing
    // private function processMessageTemplate(string $rawMessage, ?Patient $patient): string
    // {
    //     if ($patient) {
    //         $rawMessage = str_replace('{{patientName}}', $patient->name, $rawMessage);
    //         // Add more placeholder replacements
    //     }
    //     $settings = Setting::instance();
    //     $rawMessage = str_replace('{{clinicName}}', $settings->hospital_name ?? config('app.name'), $rawMessage);
    //     return $rawMessage;
    // }

    /**
     * Get stored WhatsApp templates (if you implement storing them in DB).
     */
    // public function getMessageTemplates() { /* ... */ }
}