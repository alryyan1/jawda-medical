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
        // Calculate financial totals once
        $financialSummary = $this->calculateFinancialSummary();
        
        // Cache company status to avoid multiple checks
        $isCompanyPatient = !empty($this->patient?->company_id);

        return [
            'id' => $this->id,
            'created_at' => $this->created_at?->format('Y-m-d'),
            'visit_time' => $this->visit_time,
            'visit_time_formatted' => $this->formatVisitTime(),
            'status' => $this->status,
            'visit_type' => $this->visit_type,
            'company' => $this->patient?->company,
            'queue_number' => $this->queue_number,
            'number' => $this->number,
            'reason_for_visit' => $this->reason_for_visit,
            'visit_notes' => $this->visit_notes,
            'is_new' => (bool) $this->is_new,
            'only_lab' => (bool) $this->only_lab,
            'requested_services_count' => $this->requested_services_count,
            
            // Patient information
            'patient_id' => $this->patient_id,
            'patient' => new PatientResource($this->whenLoaded('patient')),
            
            // Doctor information
            'doctor_id' => $this->doctor_id,
            'doctor' => new DoctorStrippedResource($this->whenLoaded('doctor')),
            'doctor_name' => $this->whenLoaded('doctor', $this->doctor?->name),

            // User information
            'user_id' => $this->user_id,
            'created_by_user' => new UserStrippedResource($this->whenLoaded('createdByUser')),
            
            // Shift information
            'shift_id' => $this->shift_id,
            'general_shift_details' => new ShiftResource($this->whenLoaded('generalShift')),
            'doctor_shift_id' => $this->doctor_shift_id,
            'doctor_shift_details' => new DoctorShiftResource($this->whenLoaded('doctorShift')),

            // Financial summary
            'total_amount' => $financialSummary['total_amount'],
            'total_paid' => $financialSummary['total_paid'],
            'total_discount' => $financialSummary['total_discount'],
            'balance_due' => $financialSummary['balance_due'],

            // Related resources
            'requested_services' => RequestedServiceResource::collection($this->whenLoaded('requestedServices')),
            'lab_requests' => LabRequestResource::collection($this->whenLoaded('patientLabRequests')),
            'requested_services_summary' => $this->getRequestedServicesSummary(),
            
            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Calculate financial summary for the visit
     *
     * @return array
     */
    private function calculateFinancialSummary(): array
    {
        $totalAmount = 0;
        $totalPaid = 0;
        $totalDiscount = 0;
        $isCompanyPatient = !empty($this->patient?->company_id);

        // Calculate from requested services
        if ($this->relationLoaded('requestedServices')) {
            foreach ($this->requestedServices as $service) {
                $serviceCalculation = $this->calculateServiceFinancials($service, $isCompanyPatient);
                $totalAmount += $serviceCalculation['net_payable'];
                $totalPaid += $serviceCalculation['amount_paid'];
                $totalDiscount += $serviceCalculation['discount'];
            }
        }

        // Calculate from lab requests
        if ($this->relationLoaded('patientLabRequests')) {
            foreach ($this->patientLabRequests as $labRequest) {
                $labCalculation = $this->calculateLabRequestFinancials($labRequest, $isCompanyPatient);
                $totalAmount += $labCalculation['net_payable'];
                $totalPaid += $labCalculation['amount_paid'];
                $totalDiscount += $labCalculation['discount'];
            }
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'total_paid' => round($totalPaid, 2),
            'total_discount' => round($totalDiscount, 2),
            'balance_due' => round($totalAmount - $totalPaid, 2),
        ];
    }

    /**
     * Calculate financial details for a service
     *
     * @param object $service
     * @param bool $isCompanyPatient
     * @return array
     */
    private function calculateServiceFinancials($service, bool $isCompanyPatient): array
    {
        $price = (float) ($service->price ?? 0);
        $count = (int) ($service->count ?? 1);
        $subtotal = $price * $count;
        
        // Calculate discounts
        $discountPercent = (float) ($service->discount_per ?? 0);
        $discountFixed = (float) ($service->discount ?? 0);
        $totalDiscount = ($subtotal * $discountPercent / 100) + $discountFixed;
        
        // Calculate endurance (company coverage)
        $endurance = $isCompanyPatient ? (float) ($service->endurance ?? 0) * $count : 0;
        
        $netPayable = $subtotal - $totalDiscount - $endurance;
        $amountPaid = (float) ($service->amount_paid ?? 0);

        return [
            'net_payable' => $netPayable,
            'amount_paid' => $amountPaid,
            'discount' => $totalDiscount,
        ];
    }

    /**
     * Calculate financial details for a lab request
     *
     * @param object $labRequest
     * @param bool $isCompanyPatient
     * @return array
     */
    private function calculateLabRequestFinancials($labRequest, bool $isCompanyPatient): array
    {
        $price = (float) ($labRequest->price ?? 0);
        $count = (int) ($labRequest->count ?? 1);
        $subtotal = $price * $count;
        
        // Calculate discount (only percentage for lab requests)
        $discountPercent = (float) ($labRequest->discount_per ?? 0);
        $totalDiscount = $subtotal * $discountPercent / 100;
        
        // Calculate endurance (company coverage)
        $endurance = $isCompanyPatient ? (float) ($labRequest->endurance ?? 0) * $count : 0;
        
        $netPayable = $subtotal - $totalDiscount - $endurance;
        $amountPaid = (float) ($labRequest->amount_paid ?? 0);

        return [
            'net_payable' => $netPayable,
            'amount_paid' => $amountPaid,
            'discount' => $totalDiscount,
        ];
    }

    /**
     * Format visit time for display
     *
     * @return string|null
     */
    private function formatVisitTime(): ?string
    {
        if (!$this->visit_time) {
            return null;
        }

        try {
            return Carbon::parse($this->visit_time)->format('h:i A');
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get requested services summary
     *
     * @return \Illuminate\Support\Collection|null
     */
    private function getRequestedServicesSummary()
    {
        return $this->whenLoaded('requestedServices', function() {
            return $this->requestedServices->map(function ($service) {
                return [
                    'id' => $service->id,
                    'service_name' => $service->service?->name,
                    'price' => (float) ($service->price ?? 0),
                    'count' => (int) ($service->count ?? 1),
                    'amount_paid' => (float) ($service->amount_paid ?? 0),
                    'is_paid' => (bool) ($service->is_paid ?? false),
                    'done' => (bool) ($service->done ?? false),
                ];
            });
        });
    }
}