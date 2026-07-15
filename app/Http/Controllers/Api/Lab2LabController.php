<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PatientResource;
use App\Models\Company;
use App\Models\DoctorVisit;
use App\Models\File;
use App\Models\LabRequest;
use App\Models\Patient;
use App\Models\RequestedResult;
use App\Models\Setting;
use App\Models\Shift;
use App\Services\FirebaseService;
use App\Services\WhatsAppCloudApiService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class Lab2LabController extends Controller
{
    /**
     * Return the Firestore object IDs of lab2lab patients already saved today,
     * so the frontend can mark them instead of offering to save them again.
     */
    public function todaySavedObjectIds()
    {
        $objectIds = Patient::whereDate('created_at', Carbon::today())
            ->whereNotNull('lab_to_lab_object_id')
            ->pluck('lab_to_lab_object_id');

        return response()->json(['data' => $objectIds]);
    }

    /**
     * Save a patient from online lab system to local system
     */
    public function saveFromOnlineLab(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'lab_phone' => 'nullable|string|max:20',
            'lab_requests' => 'required|array',
            'lab_requests.*.name' => 'required|string|max:255',
            'lab_requests.*.price' => 'required|numeric|min:0',
            'lab_requests.*.testId' => 'required|string',
            'lab_requests.*.container_id' => 'nullable|integer',
            'external_lab_id' => 'required|string',
            'external_patient_id' => 'required|string',
            'created_at' => 'nullable',
            'labId' => 'required|string',
            'lab2lab_id' => 'required',
            'lab2lab_barcode' => 'required',
        ]);

        $currentGeneralShift = Shift::open()->latest('created_at')->first();
        if (!$currentGeneralShift) {
            return response()->json(['message' => 'لا توجد وردية مفتوحة حالياً.'], 400);
        }

        $company = Company::where('lab2lab_firestore_id', $validated['labId'])->first();
        if (!$company) {
            return response()->json(['message' => 'العقد غير مرتبط مع الشركات يجب ربط العقد مع الشركه'], 400);
        }

        $existingPatient = Patient::where('lab_to_lab_object_id', $validated['external_patient_id'])->first();
        if ($existingPatient) {
            return response()->json(['message' => 'المريض موجود بالفعل في النظام.'], 400);
        }

        DB::beginTransaction();
        try {
            $visitLabNumber = Patient::where('shift_id', $currentGeneralShift->id)
                ->lockForUpdate()
                ->count() + 1;

            $patient = $this->createPatient($validated, $company, $currentGeneralShift, $visitLabNumber);
            $doctorVisit = $this->createDoctorVisit($patient, $currentGeneralShift, $visitLabNumber);
            $this->createLabRequests($validated['lab_requests'], $patient, $doctorVisit);

            DB::commit();

            $patient->load(['doctorVisit', 'company', 'primaryDoctor', 'sampleCollectedBy']);

            $this->markSampleDeliveredInFirestore($validated['external_patient_id'] ?? null);

            $notifications = [
                'fcm' => $this->sendLabTopicNotification($validated),
                'whatsapp_lab2lab' => ['attempted' => false, 'success' => false, 'error' => null],
                'whatsapp_owner' => ['attempted' => false, 'success' => false, 'error' => null],
            ];
            $notifications = array_merge(
                $notifications,
                $this->sendSampleReceivedWhatsAppConfirmations($validated,$patient)
            );

            return response()->json([
                'message' => 'تم حفظ المريض بنجاح',
                'data' => new PatientResource($patient),
                'notifications' => $notifications,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to save online lab patient: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'فشل في حفظ بيانات المريض',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function createPatient(array $validated, Company $company, Shift $shift, int $visitLabNumber): Patient
    {
        return Patient::create([
            'name' => $validated['name'],
            'phone' => $validated['phone'] ?? 0,
            'company_id' => $company->id,
            'gender' => 'male', // Default gender, could be made configurable
            'age_year' => 0, // Default age, could be made configurable
            'age_month' => 0,
            'age_day' => 0,
            'address' => '',
            'doctor_id' => 1, // Default doctor, should be configurable
            'user_id' => Auth::id(),
            'shift_id' => $shift->id,
            'visit_number' => $visitLabNumber,
            'lab_to_lab_id' => $validated['labId'],
            'result_auth' => false,
            'referred' => 'من مختبر خارجي',
            'labId' => $validated['labId'],
            'discount_comment' => 'مختبر خارجي: ' . ' - مريض: ' . $validated['lab2lab_id'],
            'lab_to_lab_object_id' => $validated['external_patient_id'], // Store the Firestore document ID
        ]);
    }

    private function createDoctorVisit(Patient $patient, Shift $shift, int $visitLabNumber): DoctorVisit
    {
        return $patient->doctorVisit()->create([
            'doctor_id' => 1, // Default doctor
            'user_id' => Auth::id(),
            'shift_id' => $shift->id,
            'doctor_shift_id' => null,
            'file_id' => File::create()->id,
            'visit_date' => Carbon::today(),
            'visit_time' => Carbon::now()->format('H:i:s'),
            'status' => 'lab_pending',
            'reason_for_visit' => 'طلب من مختبر خارجي',
            'is_new' => true,
            'only_lab' => true,
            'number' => $visitLabNumber,
        ]);
    }

    private function createLabRequests(array $labRequests, Patient $patient, DoctorVisit $doctorVisit): void
    {
        foreach ($labRequests as $labRequestData) {
            $labRequest = LabRequest::create([
                'main_test_id' => $labRequestData['testId'],
                'pid' => $patient->id,
                'doctor_visit_id' => $doctorVisit->id,
                'hidden' => 0,
                'is_lab2lab' => true, // Mark as lab-to-lab request
                'valid' => true,
                'no_sample' => false,
                'price' => $labRequestData['price'],
                'amount_paid' => 0,
                'discount_per' => 0,
                'is_bankak' => false,
                'comment' => 'لاب تو' . $labRequestData['testId'],
                'user_requested' => Auth::id(),
                'approve' => 0,
                'endurance' => 0,
                'is_paid' => false,
            ]);

            if ($labRequest->mainTest->childTests->isNotEmpty()) {
                $this->createRequestedResults($labRequest, $patient);
            }
        }
    }

    private function createRequestedResults(LabRequest $labRequest, Patient $patient): void
    {
        $requestedResultsData = [];
        foreach ($labRequest->mainTest->childTests as $childTest) {
            $requestedResultsData[] = [
                'lab_request_id' => $labRequest->id,
                'patient_id' => $patient->id,
                'main_test_id' => $labRequest->main_test_id,
                'child_test_id' => $childTest->id,
                'result' => '', // Initial empty result
                // Capture normal range and unit AT THE TIME OF REQUEST
                'normal_range' => $childTest->normalRange ?? ($childTest->low !== null && $childTest->upper !== null ? $childTest->low . ' - ' . $childTest->upper : null),
                'unit_id' => $childTest->unit?->id, // From eager loaded unit
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        RequestedResult::insert($requestedResultsData);
    }

    /**
     * Mark the Firestore document as delivered (non-blocking best-effort).
     */
    private function markSampleDeliveredInFirestore(?string $externalPatientId): void
    {
        if (!$externalPatientId) {
            return;
        }

        try {
            FirebaseService::updateFirestoreDocument(
                'labToLap/global/patients',
                $externalPatientId,
                [
                    'sample_delivered' => true,
                    'delivered_at' => now()->toISOString(),
                    'delivered_by' => 'jawda-medical-system',
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Failed to update Firestore document', [
                'error' => $e->getMessage(),
                'external_patient_id' => $externalPatientId,
            ]);
        }
    }

    /**
     * Send FCM topic notification to the lab on success (non-blocking best-effort).
     */
    private function sendLabTopicNotification(array $validated): array
    {
        $notification = ['attempted' => false, 'success' => false, 'error' => null];

        try {
            $labNameForTopic = (string) ($validated['labId'] ?? '');
            $safeTopic = preg_replace('/[^A-Za-z0-9\-_]/u', '_', trim($labNameForTopic));
            $testsNames = collect($validated['lab_requests'] ?? [])->pluck('name')->filter()->values()->all();
            $testsList = implode(' و ', $testsNames);
            $title = 'تم توصيل العينات الي المختبر';
            $body = 'تم توصيل العينات للمريض ' . $validated['name'] . ' صاحب التحاليل التاليه ' . $testsList;

            $notification['attempted'] = true;
            $notification['success'] = FirebaseService::sendTopicMessage($safeTopic, $title, $body);
            if (!$notification['success']) {
                $notification['error'] = 'Failed to send FCM topic notification';
            }
        } catch (\Throwable $e) {
            $notification['error'] = $e->getMessage();
            Log::warning('Failed to send lab topic notification', [
                'error' => $e->getMessage(),
            ]);
        }

        return $notification;
    }

    /**
     * Send WhatsApp "sample received" confirmation — once to the lab2lab patient phone
     * and once to the clinic's own notification phone from settings (non-blocking best-effort).
     */
    private function sendSampleReceivedWhatsAppConfirmations(
        array $validated,
        Patient $patient,
    ): array {
        $notifications = [
            'whatsapp_lab2lab' => ['attempted' => false, 'success' => false, 'error' => null],
            'whatsapp_owner' => ['attempted' => false, 'success' => false, 'error' => null],
        ];

        try {
            $settings = Setting::first();
            $components = [
                [
                    'type' => 'body',
                    'parameters' => [
                        ['type' => 'text', 'text' => (string) $validated['lab2lab_id']],
                        ['type' => 'text', 'text' => (string) $patient->name],
                        ['type' => 'text', 'text' => (string) $validated['lab2lab_barcode']],
                        ['type' => 'text', 'text' => Carbon::now()->format('Y-m-d')],
                        ['type' => 'text', 'text' => Carbon::now()->format('g:iA')],
                        ['type' => 'text', 'text' => $settings->lab_name ?? ''],
                    ],
                ],
            ];

            $whatsappService = new WhatsAppCloudApiService();

            // 1. Lab2lab contracted phone (submitted with the request)
            $lab2labPhone = WhatsAppCloudApiService::formatPhoneNumber($validated['lab_phone'] ?? '');
            if ($lab2labPhone) {
                $notifications['whatsapp_lab2lab']['attempted'] = true;
                $result = $whatsappService->sendTemplateMessage($lab2labPhone, 'sample_received_confirmation', 'ar', $components);
                $notifications['whatsapp_lab2lab']['success'] = $result['success'] ?? false;
                if (!$notifications['whatsapp_lab2lab']['success']) {
                    $notifications['whatsapp_lab2lab']['error'] = $result['error'] ?? 'Unknown error';
                    Log::warning('Failed to send sample_received_confirmation WhatsApp template to lab2lab phone', [
                        'error' => $notifications['whatsapp_lab2lab']['error'],
                        'phone' => $lab2labPhone,
                    ]);
                }
            } else {
                $notifications['whatsapp_lab2lab']['error'] = 'No phone number provided';
            }

            // 2. Clinic notification phone from settings, so the owner knows
            $ownerPhone = WhatsAppCloudApiService::formatPhoneNumber($settings->phone ?? '');
            if ($ownerPhone) {
                $notifications['whatsapp_owner']['attempted'] = true;
                $result = $whatsappService->sendTemplateMessage($ownerPhone, 'sample_received_confirmation', 'ar', $components);
                $notifications['whatsapp_owner']['success'] = $result['success'] ?? false;
                if (!$notifications['whatsapp_owner']['success']) {
                    $notifications['whatsapp_owner']['error'] = $result['error'] ?? 'Unknown error';
                    Log::warning('Failed to send sample_received_confirmation WhatsApp template to settings phone', [
                        'error' => $notifications['whatsapp_owner']['error'],
                        'phone' => $ownerPhone,
                    ]);
                }
            } else {
                $notifications['whatsapp_owner']['error'] = 'No settings phone number configured';
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to send sample received WhatsApp confirmation', [
                'error' => $e->getMessage(),
            ]);
        }

        return $notifications;
    }
}
