<?php

namespace App\Jobs;

use App\Models\RequestedServiceDepositDeletion;
use App\Services\UltramsgService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendDeletedServiceDepositWhatsappMessage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $deletionId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $deletionId)
    {
        $this->deletionId = $deletionId;
        $this->onQueue('ServicePaymentCancel');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $deletion = RequestedServiceDepositDeletion::with([
            'requestedService.service',
            'user:id,name',
            'deletedByUser:id,name',
        ])->find($this->deletionId);

        if (!$deletion) {
            Log::warning('SendDeletedServiceDepositWhatsappMessage: deletion record not found', [
                'deletion_id' => $this->deletionId,
            ]);
            return;
        }

        $serviceName = optional($deletion->requestedService->service)->name ?? ('خدمة رقم ' . $deletion->requested_service_id);
        $amount = number_format((float) $deletion->amount, 2);
        $paymentMethod = $deletion->is_bank ? 'بنكك' : 'كاش';
        $createdAt = optional($deletion->original_created_at)->format('Y-m-d H:i');
        $deletedAt = optional($deletion->deleted_at)->format('Y-m-d H:i');
        $createdBy = optional($deletion->user)->name ?? 'غير معروف';
        $deletedBy = optional($deletion->deletedByUser)->name ?? 'غير معروف';

        $message = <<<EOT
تم إلغاء سداد خدمة.

الخدمة: {$serviceName}
مبلغ السداد: {$amount}
طريقة الدفع: {$paymentMethod}
المستخدم الذي سجل السداد: {$createdBy}
تاريخ إنشاء السداد: {$createdAt}
المستخدم الذي ألغى السداد: {$deletedBy}
تاريخ الإلغاء: {$deletedAt}
EOT;

        try {
            $service = new UltramsgService();

            // ثابت حسب طلبك: 249991961111
            $phone = UltramsgService::formatPhoneNumber('249991961111', '249');

            if (!$phone) {
                Log::warning('SendDeletedServiceDepositWhatsappMessage: invalid target phone.');
                return;
            }

            $service->sendTextMessage($phone, $message);
        } catch (\Throwable $e) {
            Log::error('SendDeletedServiceDepositWhatsappMessage failed: ' . $e->getMessage(), [
                'deletion_id' => $this->deletionId,
            ]);
        }
    }
}


