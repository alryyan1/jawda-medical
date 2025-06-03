<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorVisitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
  // app/Http/Resources/DoctorVisitResource.php
// ...
// ...
public function toArray(Request $request): array
{
    $totalAmount = 0;
    $totalPaid = 0;
    $totalDiscount = 0; // If you track discount at visit level or sum from services

    if ($this->relationLoaded('requestedServices')) {
        foreach ($this->requestedServices as $rs) {
            $pricePerItem = (float) $rs->price;
            $count = (int) $rs->count;
            $itemSubTotal = $pricePerItem * $count;
            
            $itemDiscountAmount = (float) $rs->discount; // Fixed discount
            if ($rs->discount_per > 0) {
                $itemDiscountAmount += ($itemSubTotal * ((int) $rs->discount_per / 100));
            }

            $totalAmount += $itemSubTotal;
            $totalPaid += (float) $rs->amount_paid;
            $totalDiscount += $itemDiscountAmount;
        }
    }

    return [
        'id' => $this->id,
        // ... other visit fields like visit_date, visit_time, status ...
        'visit_date' => $this->visit_date?->toDateString(),
        'visit_time' => $this->visit_time, // or ->format('H:i A')
        'status' => $this->status,

        'patient_id' => $this->patient_id,
        'patient' => new PatientResource($this->whenLoaded('patient')),
        'doctor_id' => $this->doctor_id,
        'doctor' => new DoctorStrippedResource($this->whenLoaded('doctor')),
        'name' => $this->patient->name,
        
        // Financials for this visit
        'total_amount' => $totalAmount,
        'total_paid' => $totalPaid,
        'total_discount' => $totalDiscount,
        'balance_due' => $totalAmount - $totalDiscount - $totalPaid,
        'number' => $this->number,
        'company' => new CompanyStrippedResource($this->patient->company),

        'requested_services_summary' => RequestedServiceStrippedResource::collection($this->whenLoaded('requestedServices')), // For the dialog
        // ... other fields ...
        'created_at' => $this->created_at?->toIso8601String(),
    ];
}
}