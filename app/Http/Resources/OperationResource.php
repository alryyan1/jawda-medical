<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'admission_id' => $this->admission_id,
            'admission' => $this->whenLoaded('admission', function () {
                return [
                    'id' => $this->admission->id,
                    'patient' => $this->admission->patient ? [
                        'id' => $this->admission->patient->id,
                        'name' => $this->admission->patient->name,
                        'phone' => $this->admission->patient->phone,
                    ] : null,
                ];
            }),
            'operation_date' => $this->operation_date?->toDateString(),
            'operation_time' => $this->operation_time?->format('H:i:s'),
            'operation_type' => $this->operation_type,
            'description' => $this->description,

            // Financial data
            'surgeon_fee' => (float) $this->surgeon_fee,
            'total_staff' => (float) $this->total_staff,
            'total_center' => (float) $this->total_center,
            'total_amount' => (float) $this->total_amount,

            // Payments
            'cash_paid' => (float) $this->cash_paid,
            'bank_paid' => (float) $this->bank_paid,
            'balance' => (float) $this->balance,
            'bank_receipt_image' => $this->bank_receipt_image ? asset('storage/' . $this->bank_receipt_image) : null,

            'notes' => $this->notes,
            'status' => $this->status,

            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'username' => $this->user->username,
                ];
            }),

            'finance_items' => OperationFinanceItemResource::collection($this->whenLoaded('financeItems')),

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
