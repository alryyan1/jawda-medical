<?php

namespace App\Jobs;

use App\Models\Patient;
use App\Models\Setting;
use App\Services\WhatsAppCloudApiService;
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

        //log start of job
        Log::info('SendAuthWhatsappMessage started for patient '.$this->patientId);

        $settings = Setting::first();
        if (!$settings || !($settings->send_whatsapp_after_auth ?? false)) {
            return; // feature disabled
        }

        $patient = Patient::with('doctorVisit')->find($this->patientId);
        if (!$patient || !$patient->doctorVisit) {
            return;
        }

        $formattedPhone = WhatsAppCloudApiService::formatPhoneNumber($patient->phone ?? '');
        if (!$formattedPhone) {
            Log::warning('SendAuthWhatsappMessage: No valid phone for patient '.$this->patientId);
            return;
        }

        $visitId = $patient->doctorVisit->id;
        
        // Prepare template components with visit ID as parameter
        $components = [
            [
                'type' => 'body',
                'parameters' => [
                    [
                        'type' => 'text',
                        'text' => (string) $visitId
                    ]
                ]
            ]
        ];

        try {
            Log::info('SendAuthWhatsappMessage sending message to patient '.$this->patientId);
            $service = new WhatsAppCloudApiService();
            $result = $service->sendTemplateMessage(
                $formattedPhone,
                'complete_ar',
                'ar',
                // $components
            );
            
            if (!$result['success']) {
                Log::error('SendAuthWhatsappMessage failed: '.($result['error'] ?? 'Unknown error'));
            }
        } catch (\Throwable $e) {
            Log::error('SendAuthWhatsappMessage failed: '.$e->getMessage());
        }
    }
}


