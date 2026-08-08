<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorVisit;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Http\JsonResponse;

class VisitDocumentsWhatsAppController extends Controller
{
    /**
     * POST /doctor-visits/{doctorVisit}/send-documents-whatsapp
     *
     * Sends the "visit_documents_menu" WhatsApp template: four quick-reply
     * buttons (medical report / diagnosis / prescription / lab result). Tapping
     * one has WhatsApp deliver a button-reply webhook back to us, which
     * WhatsAppCloudApiController::handleIncomingMessage() answers with the
     * matching generated PDF as a document message.
     *
     * NOTE: the "visit_documents_menu" template itself is managed in Meta's
     * WhatsApp Business Manager, not in this codebase — it must be approved
     * there with a matching 4th quick-reply button (e.g. "نتيجة المختبر")
     * before this 4th button parameter will actually be delivered.
     */
    public function sendMenu(DoctorVisit $doctorVisit): JsonResponse
    {
        $doctorVisit->loadMissing('patient');
        $patient = $doctorVisit->patient;

        $formattedPhone = $patient ? WhatsAppCloudApiService::formatPhoneNumber($patient->phone ?? '') : null;

        if (! $formattedPhone) {
            return response()->json([
                'success' => false,
                'error' => 'رقم هاتف المريض غير صالح.',
            ], 422);
        }

        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $patient->name ?: 'عزيزنا المريض'],
                    ['type' => 'text', 'text' => (string) $doctorVisit->id],
                ],
            ],
            $this->quickReplyButton(0, "medical_report:{$doctorVisit->id}"),
            $this->quickReplyButton(1, "diagnosis:{$doctorVisit->id}"),
            $this->quickReplyButton(2, "prescription:{$doctorVisit->id}"),
            $this->quickReplyButton(3, "lab_result:{$doctorVisit->id}"),
        ];

        $result = (new WhatsAppCloudApiService)->sendTemplateMessage(
            $formattedPhone,
            'visit_documents_menu',
            'ar',
            $components
        );

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * @return array<string, mixed>
     */
    protected function quickReplyButton(int $index, string $payload): array
    {
        return [
            'type' => 'button',
            'sub_type' => 'quick_reply',
            'index' => (string) $index,
            'parameters' => [
                ['type' => 'payload', 'payload' => $payload],
            ],
        ];
    }
}
