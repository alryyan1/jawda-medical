<?php

namespace App\Jobs;

use App\Models\Patient;
use App\Models\Setting;
use App\Services\UltramsgService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAuthWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $patientId;

    public function __construct(int $patientId)
    {
        $this->patientId = $patientId;
    }

    public function handle(): void
    {
        $settings = Setting::first();
        if (!$settings || !($settings->send_whatsapp_after_auth ?? false)) {
            return; // feature disabled
        }

        $patient = Patient::with('doctorVisit')->find($this->patientId);
        if (!$patient || !$patient->doctorVisit) {
            return;
        }

        $formattedPhone = UltramsgService::formatPhoneNumber($patient->phone ?? '');
        if (!$formattedPhone) {
            Log::warning('SendAuthWhatsappMessage: No valid phone for patient '.$this->patientId);
            return;
        }

        $visitId = $patient->doctorVisit->id;
        $msg = <<<EOD
عزيزي الزائر نفيدك بانتهاء التحاليل الطبيه
شكرا لزيارتك
لاستلام النتيجه ارسل الكود {$visitId}
EOD;

        try {
            $service = new UltramsgService();
            $service->sendTextMessage($formattedPhone, $msg);
        } catch (\Throwable $e) {
            Log::error('SendAuthWhatsappMessage failed: '.$e->getMessage());
        }
    }
}


