<?php

namespace App\Jobs;

use App\Models\BankakImage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmitBankakImageInsertedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $bankakImageId)
    {
    }

    public function handle(): void
    {
        Log::info('EmitBankakImageInsertedJob: started', ['bankak_image_id' => $this->bankakImageId]);
        
        $bankakImage = BankakImage::with('doctorvisit.patient')->find($this->bankakImageId);

        if (!$bankakImage) {
            Log::warning('EmitBankakImageInsertedJob: bankak image not found', ['bankak_image_id' => $this->bankakImageId]);
            return;
        }
        

        $payload = [
            'id' => $bankakImage->id,
            'image_url' => $bankakImage->image_url,
            'full_image_url' => url('/storage/' . $bankakImage->image_url),
            'phone' => $bankakImage->phone,
            'doctorvisit_id' => $bankakImage->doctorvisit_id,
            'patient_name' => $bankakImage->doctorvisit?->patient?->name ?? 'غير محدد',
            'created_at' => $bankakImage->created_at->format('Y-m-d H:i:s'),
            'created_at_human' => $bankakImage->created_at->diffForHumans(),
        ];

        $baseUrl = rtrim((string) config('services.realtime.url'), '/');
        if ($baseUrl === '') {
            Log::warning('EmitBankakImageInsertedJob: realtime URL not configured');
            return;
        }

        $response = Http::withHeaders([
            'x-internal-token' => (string) config('services.realtime.token'),
        ])->post($baseUrl . '/emit/bankak-image-inserted', $payload);

        Log::info('EmitBankakImageInsertedJob: emit response', [
            'status' => $response->status(),
            'ok' => $response->successful(),
            'response_body' => $response->body(),
        ]);
    }
}
