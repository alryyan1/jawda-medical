<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientAppointmentRequest;
use App\Http\Resources\PatientAppointmentResource;
use App\Models\Patient;
use App\Models\PatientAppointment;
use App\Models\Setting;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class PatientAppointmentController extends Controller
{
    /**
     * GET /patients/{patient}/appointments
     * Lists appointments across every visit of this patient's File (see Patient::siblingPatientIds).
     */
    public function index(Patient $patient): AnonymousResourceCollection
    {
        $appointments = PatientAppointment::whereIn('patient_id', $patient->siblingPatientIds())
            ->with(['doctor', 'createdBy'])
            ->orderByDesc('scheduled_at')
            ->get();

        return PatientAppointmentResource::collection($appointments);
    }

    /**
     * POST /patients/{patient}/appointments
     * Creates the appointment and, unless opted out, sends a WhatsApp notification immediately.
     */
    public function store(StorePatientAppointmentRequest $request, Patient $patient): PatientAppointmentResource
    {
        $validated = $request->validated();

        $appointment = PatientAppointment::create([
            'patient_id' => $patient->id,
            'doctor_id' => $validated['doctor_id'] ?? null,
            'created_by_user_id' => Auth::id(),
            'scheduled_at' => $validated['scheduled_at'],
            'notes' => $validated['notes'] ?? null,
            'status' => 'scheduled',
        ]);

        if ($validated['send_whatsapp'] ?? true) {
            $this->sendWhatsappNotification($appointment);
        }

        return new PatientAppointmentResource($appointment->fresh()->load(['doctor', 'createdBy']));
    }

    /**
     * POST /patient-appointments/{patientAppointment}/resend-whatsapp
     */
    public function resendWhatsapp(PatientAppointment $patientAppointment): PatientAppointmentResource
    {
        $this->sendWhatsappNotification($patientAppointment);

        return new PatientAppointmentResource($patientAppointment->fresh()->load(['doctor', 'createdBy']));
    }

    /**
     * PUT /patient-appointments/{patientAppointment}/cancel
     */
    public function cancel(PatientAppointment $patientAppointment): PatientAppointmentResource
    {
        $patientAppointment->update(['status' => 'cancelled']);

        return new PatientAppointmentResource($patientAppointment->fresh()->load(['doctor', 'createdBy']));
    }

    protected function sendWhatsappNotification(PatientAppointment $appointment): void
    {
        $appointment->loadMissing(['patient', 'doctor']);
        $patient = $appointment->patient;

        $formattedPhone = $patient ? WhatsAppCloudApiService::formatPhoneNumber($patient->phone ?? '') : null;

        if (! $formattedPhone) {
            $appointment->update(['whatsapp_send_error' => 'رقم هاتف المريض غير صالح.']);

            return;
        }

        $service = new WhatsAppCloudApiService;
        $result = $service->sendTemplateMessage(
            $formattedPhone,
            'appointment_scheduled',
            'ar',
            $this->buildTemplateComponents($appointment)
        );

        if ($result['success']) {
            $appointment->update(['whatsapp_sent_at' => now(), 'whatsapp_send_error' => null]);
        } else {
            $appointment->update(['whatsapp_send_error' => $result['error'] ?? 'فشل إرسال رسالة واتساب.']);
        }
    }

    /**
     * Body parameters for the "appointment_scheduled" WhatsApp Cloud API
     * template — {{1}} patient name, {{2}} combined appointment details
     * (clinic name, date, time, doctor). Meta rejects templates with too
     * many variables relative to their static text, so the details are
     * merged into a single parameter rather than one each. See the template
     * body text this controller expects to be approved in Meta Business Manager.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function buildTemplateComponents(PatientAppointment $appointment): array
    {
        $hospitalName = Setting::first()?->hospital_name ?: 'العيادة';
        $patientName = $appointment->patient?->name ?: 'عزيزنا المريض';
        $date = $appointment->scheduled_at->format('Y-m-d');
        $time = $appointment->scheduled_at->format('h:i A');
        $doctorLine = $appointment->doctor ? "الدكتور {$appointment->doctor->name}" : 'الطبيب المختص';

        $details = "{$hospitalName} - بتاريخ {$date} الساعة {$time} مع {$doctorLine}";

        return [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $patientName],
                    ['type' => 'text', 'text' => $details],
                ],
            ],
        ];
    }
}
