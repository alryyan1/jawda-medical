<?php

namespace App\Jobs;

use App\Models\VisitDose;
use App\Services\JawdaInpatientClient;
use App\Services\WhatsAppCloudApiService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Notifies an admitted patient's companion (مرافق) over WhatsApp about a
 * dose's status, using the companion's phone number stored on the
 * admission in the separate jawda-inpatient system, unless a phone number
 * is supplied explicitly (e.g. edited by the nurse before sending).
 * Dispatched (queued, fire-and-forget) automatically when a dose
 * transitions to "given", and called synchronously via send() for the
 * doctor portal's "send via WhatsApp" button so the caller can report the
 * outcome back to the user.
 */
class SendDoseGivenWhatsAppNotice implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $visitDoseId, public ?string $phoneOverride = null) {}

    public function handle(JawdaInpatientClient $jawdaInpatientClient): void
    {
        $this->send($jawdaInpatientClient);
    }

    /**
     * @return array{success: bool, error?: string, data?: mixed, message_id?: string}
     */
    public function send(JawdaInpatientClient $jawdaInpatientClient): array
    {
        $dose = VisitDose::with(['doctorVisit', 'patient'])->find($this->visitDoseId);

        if (! $dose) {
            return ['success' => false, 'error' => 'الجرعة غير موجودة.'];
        }

        $phone = $this->phoneOverride;

        if (! $phone) {
            if (! $dose->doctorVisit?->admission_id) {
                return ['success' => false, 'error' => 'الزيارة غير مرتبطة بتنويم، ولا يوجد رقم هاتف للمرافق.'];
            }

            try {
                $contact = $jawdaInpatientClient->getCompanionContact($dose->doctorVisit->admission_id);
            } catch (\Throwable $e) {
                Log::warning('SendDoseGivenWhatsAppNotice: failed to fetch companion contact', [
                    'visit_dose_id' => $this->visitDoseId,
                    'error' => $e->getMessage(),
                ]);

                return ['success' => false, 'error' => 'فشل الحصول على رقم هاتف المرافق.'];
            }

            $phone = $contact['phone'] ?? '';
        }

        $formattedPhone = WhatsAppCloudApiService::formatPhoneNumber($phone);

        if (! $formattedPhone) {
            return ['success' => false, 'error' => 'لا يوجد رقم هاتف صالح لإرسال الإشعار إليه.'];
        }

        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    ['type' => 'text', 'text' => $dose->patient?->name ?? ''],
                    ['type' => 'text', 'text' => $dose->medication_name],
                    ['type' => 'text', 'text' => $dose->dosage],
                    ['type' => 'text', 'text' => $dose->administered_at->format('Y-m-d H:i')],
                    ['type' => 'text', 'text' => $dose->statusLabel()],
                ],
            ],
        ];

        try {
            $result = (new WhatsAppCloudApiService)->sendTemplateMessage(
                $formattedPhone,
                'dose_notification',
                'ar',
                $components
            );

            if (! $result['success']) {
                Log::error('SendDoseGivenWhatsAppNotice failed: '.($result['error'] ?? 'Unknown error'));
            }

            return $result;
        } catch (\Throwable $e) {
            Log::error('SendDoseGivenWhatsAppNotice failed: '.$e->getMessage());

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
