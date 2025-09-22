<?php

namespace App\Jobs;

use App\Services\Contracts\SmsClient;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWelcomeSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $patientId;
    public string $toPhone;
    public string $patientName;

    public $tries = 3;
    public $backoff = [30, 120, 300];

    /**
     * Create a new job instance.
     */
    public function __construct(int $patientId, string $toPhone, string $patientName)
    {
        $this->patientId = $patientId;
        $this->toPhone = $toPhone;
        $this->patientName = $patientName;
        $this->onQueue('sms');
    }

    /**
     * Execute the job.
     */
    public function handle(SmsClient $sms): void
    {
        $settings = Setting::instance();

        // Respect toggle
        if ($settings && $settings->send_welcome_message === false) {
            return;
        }

        $name = trim($this->patientName) !== '' ? $this->patientName : 'عزيزي الزائر';
        $hospital = optional($settings)->hospital_name ?: ' ';

        // Use custom template if provided; support simple placeholders {name}, {hospital}
        $template = optional($settings)->welcome_message;
        if ($template && trim($template) !== '') {
            $message = strtr($template, [
                '{name}' => $name,
                '{hospital}' => $hospital,
            ]);
        } else {
            $message = "مرحباً {$name}، نرحب بكم في مستشفى {$hospital}. تم تسجيل زيارتكم بنجاح ونسعد بخدمتكم. نتمنى لكم دوام الصحة والعافية.";
        }

        try {
            $sms->send($this->toPhone, $message, false, config('services.airtel_sms.default_sender'));
        } catch (\Throwable $e) {
            \Log::warning('Failed to send welcome SMS', [
                'patient_id' => $this->patientId,
                'phone' => $this->toPhone,
                'error' => $e->getMessage(),
            ]);
            throw $e; // allow retry
        }
    }
}


