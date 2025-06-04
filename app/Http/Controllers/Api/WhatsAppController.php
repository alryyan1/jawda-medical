<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientStrippedResource;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use App\Models\Setting; // To get instanceId and token if not passed
use App\Models\Patient; // To get patient phone number
use Carbon\Carbon;
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
    public function getPatientsForBulkMessage(Request $request)
    {
        // $this->authorize('send_bulk_whatsapp_messages');
        $validated = $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'service_id' => 'nullable|integer|exists:services,id',
            'specialist_id' => 'nullable|integer|exists:specialists,id',
        ]);

        $query = Patient::query()
            ->select('patients.id', 'patients.name', 'patients.phone')
            ->whereNotNull('patients.phone')->where('patients.phone', '!=', '')
            ->join('doctorvisits', 'patients.id', '=', 'doctorvisits.patient_id'); // Join with the single DoctorVisit

        if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
            $startDate = Carbon::parse($validated['date_from'])->startOfDay();
            $endDate = Carbon::parse($validated['date_to'])->endOfDay();
            $query->whereBetween('doctorvisits.visit_date', [$startDate, $endDate]);
        }

        if (!empty($validated['doctor_id'])) {
            $query->where('doctorvisits.doctor_id', $validated['doctor_id']);
            // If date range should also apply specifically to this doctor's visits:
            // (already handled by the general date filter on doctorvisits.visit_date)
        }

        if (!empty($validated['service_id'])) {
            // We need to check if the patient's single DoctorVisit has the requested service
            $query->whereHas('doctorVisit.requestedServices', function ($q) use ($validated) {
                $q->where('service_id', $validated['service_id']);
                // If date range should also apply to when service was requested:
                // if (!empty($validated['date_from']) && !empty($validated['date_to'])) {
                //     $q->whereBetween('requested_services.created_at', [Carbon::parse($validated['date_from'])->startOfDay(), Carbon::parse($validated['date_to'])->endOfDay()]);
                // }
            });
        }
        
        if (!empty($validated['specialist_id'])) {
            // Check if the doctor of the patient's single DoctorVisit has the specified specialist
            $query->whereHas('doctorVisit.doctor.specialist', function ($q) use ($validated) {
                $q->where('specialists.id', $validated['specialist_id']);
            });
        }

        if (!empty($validated['unique_phones_only']) && $validated['unique_phones_only']) {
            // This makes the query more complex if you need to select one patient per unique phone
            // while still respecting all other join-based filters.
            // A common approach is to get all matching patient IDs first, then get unique phones from those,
            // then select one patient per unique phone.
            // For now, a simpler approach:
            $patients = $query->distinct('patients.phone') // This might not give you the specific patient record you want per phone
                               ->orderBy('patients.name')
                               ->get(['patients.id', 'patients.name', 'patients.phone']);
            // To get one full patient record per unique phone, you might need a subquery or window function in raw SQL,
            // or fetch all and then filter in PHP (less ideal for large sets).
            // A more robust unique_phones_only with other filters:
            // $allMatchingPatientIds = (clone $query)->distinct('patients.id')->pluck('patients.id');
            // $uniquePhones = Patient::whereIn('id', $allMatchingPatientIds)->distinct()->pluck('phone');
            // $patients = Patient::select('id', 'name', 'phone')
            //                     ->whereIn('phone', $uniquePhones)
            //                     ->groupBy('phone') // May need to adjust for DB strict mode
            //                     ->orderBy('name')
            //                     ->get();

        } else {
            $patients = $query->distinct('patients.id')->orderBy('patients.name')->get(['patients.id', 'patients.name', 'patients.phone']);
        }
        
        return PatientStrippedResource::collection($patients);
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