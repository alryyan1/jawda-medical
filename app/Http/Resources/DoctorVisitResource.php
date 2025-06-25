<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class DoctorVisitResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totalDiscount = 0;
        // Calculate discount from services
        foreach ($this->whenLoaded('requestedServices') as $rs) {
            $itemPrice = (float) $rs->price;
            $itemCount = (int) ($rs->count ?? 1);
            $itemSubTotal = $itemPrice * $itemCount;
            $discountFromPercentage = ($itemSubTotal * (intval($rs->discount_per) ?? 0)) / 100;
            $fixedDiscount = intval($rs->discount) ?? 0; // Assuming 'discount' is fixed amount
            $totalDiscount += ($discountFromPercentage + $fixedDiscount);
        }
        // Calculate discount from lab requests (if applicable and tracked similarly)
        foreach ($this->whenLoaded('patientLabRequests') as $lr) {
            $labPrice = (float) $lr->price;
            $labCount = (int) ($lr->count ?? 1); // Assuming lab requests can also have a count
            $labItemSubTotal = $labPrice * $labCount;
            $labDiscountFromPercentage = ($labItemSubTotal * (intval($lr->discount_per) ?? 0)) / 100;
            // If lab requests have a fixed discount field, add it here.
            // $labFixedDiscount = intval($lr->fixed_discount_field_name ?? 0);
            // $totalDiscount += ($labDiscountFromPercentage + $labFixedDiscount);
            $totalDiscount += $labDiscountFromPercentage;
        }


        // Calculate total amount and paid for the summary needed by PatientVisitSummary type
        $totalAmount = 0;
        $totalPaid = 0;
        $balanceDue = 0;

        foreach ($this->whenLoaded('requestedServices') as $rs) {
            $price = (float)($rs->price ?? 0);
            $count = (int)($rs->count ?? 1);
            $itemSubTotal = $price * $count;
            $discountPercent = (float)($rs->discount_per ?? 0);
            $discountFixed = (float)($rs->discount ?? 0);
            $itemDiscount = ($itemSubTotal * $discountPercent / 100) + $discountFixed;
            $itemEndurance = (float)($rs->endurance ?? 0) * $count; // endurance per item * count
            $isCompanyPatientForService = !!$this->patient?->company_id;

            $netPayableForItem = $itemSubTotal - $itemDiscount - ($isCompanyPatientForService ? $itemEndurance : 0);
            $totalAmount += $netPayableForItem; // This is net payable by patient/company combined for this service
            $totalPaid += (float)($rs->amount_paid ?? 0);
        }

        foreach ($this->whenLoaded('patientLabRequests') as $lr) {
            $price = (float)($lr->price ?? 0);
            $count = (int)($lr->count ?? 1);
            $itemSubTotal = $price * $count;
            $discountPercent = (float)($lr->discount_per ?? 0);
            $itemDiscount = ($itemSubTotal * $discountPercent / 100);
            $itemEndurance = (float)($lr->endurance ?? 0) * $count;
            $isCompanyPatientForLab = !!$this->patient?->company_id;
            
            $netPayableForItem = $itemSubTotal - $itemDiscount - ($isCompanyPatientForLab ? $itemEndurance : 0);
            $totalAmount += $netPayableForItem;
            $totalPaid += (float)($lr->amount_paid ?? 0);
        }
        $balanceDue = $totalAmount - $totalPaid;


        return [
            'id' => $this->id,
            'created_at' => $this->created_at->format('Y-m-d'),
            'visit_time' => $this->visit_time, // Keep as string HH:mm:ss or HH:mm
            'visit_time_formatted' => $this->visit_time ? Carbon::parse($this->visit_time)->format('h:i A') : null, // Formatted time
            'status' => $this->status,
            'visit_type' => $this->visit_type,
            'company' => $this->patient?->company,
            'queue_number' => $this->queue_number,
            'number' => $this->number, // If this is also queue number or visit specific number
            'reason_for_visit' => $this->reason_for_visit,
            'visit_notes' => $this->visit_notes,
            'is_new' => (bool) $this->is_new,
            'only_lab' => (bool) $this->only_lab,
            'requested_services_count' => $this->requested_services_count,
            'patient_id' => $this->patient_id,
            'patient' => new PatientStrippedResource($this->whenLoaded('patient')), // Assuming PatientStrippedResource
            
            'doctor_id' => $this->doctor_id,
            'doctor' => new DoctorStrippedResource($this->whenLoaded('doctor')), // NEW
            'doctor_name' => $this->whenLoaded('doctor', $this->doctor?->name), // Direct doctor name

            'user_id' => $this->user_id,
            'created_by_user' => new UserStrippedResource($this->whenLoaded('createdByUser')),
            
            'shift_id' => $this->shift_id,
            'general_shift_details' => new ShiftResource($this->whenLoaded('generalShift')),
            
            'doctor_shift_id' => $this->doctor_shift_id,
            'doctor_shift_details' => new DoctorShiftResource($this->whenLoaded('doctorShift')), // Assuming DoctorShiftResource

            // For PatientVisitSummary type compatibility on frontend
            'total_amount' => round($totalAmount, 2),
            'total_paid' => round($totalPaid, 2),
            'total_discount' => round($totalDiscount, 2), // NEW
            'balance_due' => round($balanceDue, 2),

            'requested_services' => RequestedServiceResource::collection($this->whenLoaded('requestedServices')),
            'lab_requests' => LabRequestResource::collection($this->whenLoaded('patientLabRequests')),
            // For the services summary dialog
            'requested_services_summary' => $this->whenLoaded('requestedServices', function() {
                return $this->requestedServices->map(function ($rs) {
                    return [
                        'id' => $rs->id,
                        'service_name' => $rs->service?->name,
                        'price' => (float) $rs->price,
                        'count' => (int) $rs->count,
                        'amount_paid' => (float) $rs->amount_paid,
                        'is_paid' => (bool) $rs->is_paid,
                        'done' => (bool) $rs->done,
                    ];
                });
            }),
            
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
       
            
        ];
    }
}