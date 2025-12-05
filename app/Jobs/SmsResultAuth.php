<?php

namespace App\Jobs;

use App\Models\Patient;
use App\Models\Setting;
use App\Services\Contracts\SmsClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SmsResultAuth implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $patientId;

    public $tries = 3;
    public $backoff = [30, 120, 300];

    public function __construct(int $patientId)
    {
        $this->patientId = $patientId;
        $this->onQueue('sms');
    }

    public function handle(SmsClient $sms): void
    {
        $settings = Setting::first();
        if (!$settings || !($settings->send_sms_after_auth ?? false)) {
            return; // feature disabled
        }

        $patient = Patient::with('doctorVisit')->find($this->patientId);
        if (!$patient || !$patient->doctorVisit) {
            Log::warning('SmsResultAuth: Patient or doctor visit not found for patient ' . $this->patientId);
            return;
        }

        if (empty($patient->phone)) {
            Log::warning('SmsResultAuth: No phone number for patient ' . $this->patientId);
            return;
        }

        $visitId = $patient->doctorVisit->id;
        $message = <<<EOD
عزيزي الزائر نفيدك بانتهاء التحاليل الطبيه
شكرا لزيارتك
EOD;

        // Create WhatsApp click-to-chat link
        // Format: https://wa.me/<number>?text=<encoded_message>
        // Number should be in international format without +, zeros, brackets, or dashes
        $whatsappNumber = '96878622990'; // WhatsApp number in international format
        $whatsappMessage = urlencode($message . "\n" . $visitId);
        $whatsappLink = "https://wa.me/{$whatsappNumber}?text={$visitId}";
        
        // Add WhatsApp link to the SMS message
        $messageWithLink = $message . "\n"  . "\n\n" . "للحصول على النتيجة واتساب  اضغط علي الرابط:  \n" . $whatsappLink;

        try {
            $sms->send($patient->phone, $messageWithLink, false, config('services.airtel_sms.default_sender'));
            Log::info('SmsResultAuth: SMS sent successfully to patient ' . $this->patientId, [
                'visit_id' => $visitId,
                'whatsapp_link' => $whatsappLink
            ]);
        } catch (\Throwable $e) {
            Log::error('SmsResultAuth failed: ' . $e->getMessage(), [
                'patient_id' => $this->patientId,
                'phone' => $patient->phone,
            ]);
            throw $e; // allow retry
        }
    }
}

