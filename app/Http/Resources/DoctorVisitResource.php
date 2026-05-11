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
        $isCompanyPatient = !empty($this->patient?->company_id);
        $financialSummary = $this->calculateFinancialSummary($isCompanyPatient);

        return [
            'id' => $this->id,
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
            'is_online' => (bool) $this->is_online,
            'requested_services_count' => $this->requested_services_count,

            // Patient information
            'patient_id' => $this->patient_id,
            'patient' => new PatientResource($this->whenLoaded('patient', function () {
                // Inject has_cbc from the visit-level withExists so PatientResource
                // never needs to lazy-load doctorVisit or call hasCbc() per row.
                $this->patient->has_cbc = (bool) ($this->has_cbc ?? false);
                return $this->patient;
            })),
            'patient_subcompany' => $this->whenLoaded('patient', function () {
                return $this->patient->subcompany;
            }),

            // Doctor information
            'doctor_id' => $this->doctor_id ?? $this->patient?->doctor_id,
            'doctor' => new DoctorStrippedResource($this->relationLoaded('doctor') ? $this->doctor : ($this->patient?->relationLoaded('doctor') ? $this->patient->doctor : null)),
            'doctor_name' => $this->getDoctorName(),

            // User information
            'user_id' => $this->user_id,
            'created_by_user' => new UserStrippedResource($this->whenLoaded('createdByUser')),

            // Shift information
            'shift_id' => $this->shift_id,
            'general_shift_details' => new ShiftResource($this->whenLoaded('generalShift')),
            'doctor_shift_id' => $this->doctor_shift_id,
            'doctor_shift_details' => new DoctorShiftResource($this->whenLoaded('doctorShift')),

            // Service totals (computed in single pass inside calculateFinancialSummary)
            'total_services_amount' => $financialSummary['total_services_amount'],
            'total_services_paid' => $financialSummary['total_paid'],
            'total_lab_value_will_pay' => $financialSummary['total_lab_value_will_pay'],
            'lab_paid' => $financialSummary['lab_paid'],

            // Financial summary
            'total_lab_amount' => $financialSummary['total_lab_amount'],
            'total_paid' => $financialSummary['total_paid'],
            'total_discount' => $financialSummary['total_discount'],
            'balance_due' => $financialSummary['balance_due'],
            'total_lab_paid' => $financialSummary['total_lab_paid'],
            'total_lab_discount' => $financialSummary['total_lab_discount'],
            'total_lab_endurance' => $financialSummary['total_lab_endurance'],
            'total_lab_balance' => $financialSummary['total_lab_balance'],

            // Related resources
            'requested_services' => RequestedServiceResource::collection($this->whenLoaded('requestedServices')),
            'lab_requests' => LabRequestResource::collection($this->whenLoaded('patientLabRequests')),
            'requested_services_summary' => $this->getRequestedServicesSummary(),

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'company_relation' => $this->whenLoaded('patient', fn () => $this->patient->companyRelation),
            'result_auth' => $this->patient?->result_auth,
            'auth_date' => $this->patient?->auth_date,
        ];
    }

    /**
     * Single-pass financial calculation over requestedServices and patientLabRequests.
     * Eliminates the 3 separate iterations that existed before.
     */
    public function calculateFinancialSummary(bool $isCompanyPatient = false): array
    {
        $totalAmount = 0;
        $totalPaid = 0;
        $totalDiscount = 0;
        $totalEndurance = 0;
        $totalServicesAmount = 0;
        $totalLabAmount = 0;
        $totalLabPaid = 0;
        $totalLabDiscount = 0;
        $totalLabEndurance = 0;
        $totalLabBalance = 0;
        $totalLabValueWillPay = 0;
        $labPaid = 0;

        // Single pass over requestedServices (replaces 3 separate loops)
        if ($this->relationLoaded('requestedServices')) {
            foreach ($this->requestedServices as $service) {
                $calc = $this->calculateServiceFinancials($service, $isCompanyPatient);
                $totalAmount += $calc['net_payable'];
                $totalPaid += $calc['amount_paid'];
                $totalDiscount += $calc['discount'];
                $totalServicesAmount += (float) ($service->price ?? 0) * (int) ($service->count ?? 1);
            }
        }

        // Single pass over patientLabRequests (replaces 3 separate patient->labrequests loops)
        if ($this->relationLoaded('patientLabRequests')) {
            foreach ($this->patientLabRequests as $labRequest) {
                $calc = $this->calculateLabRequestFinancials($labRequest, $isCompanyPatient);
                $totalLabAmount += $calc['price'];
                $totalLabPaid += $calc['amount_paid'];
                $totalLabDiscount += $calc['discount'];
                $totalLabEndurance += $calc['endurance'];
                $totalLabBalance += $calc['balance'];

                // Replaces patient->total_lab_value_will_pay() and patient->discountAmount()
                $totalLabValueWillPay += $isCompanyPatient
                    ? $calc['endurance'] - $calc['discount']
                    : $calc['price'] - $calc['discount'];

                // Replaces patient->paid_lab() — only count paid requests
                if (!empty($labRequest->is_paid)) {
                    $labPaid += $calc['amount_paid'];
                }
            }
        }

        return [
            'total_services_amount' => round($totalServicesAmount, 2),
            'total_lab_amount' => round($totalLabAmount, 2),
            'total_paid' => round($totalPaid, 2),
            'total_discount' => round($totalDiscount, 2),
            'balance_due' => round($isCompanyPatient ? $totalEndurance - $totalPaid : ($totalAmount - $totalDiscount) - $totalPaid, 2),
            'total_lab_paid' => round($totalLabPaid, 2),
            'total_lab_discount' => round($totalLabDiscount, 2),
            'total_lab_endurance' => round($totalLabEndurance, 2),
            'total_lab_balance' => round($totalLabBalance, 2),
            'total_lab_value_will_pay' => round($totalLabValueWillPay, 2),
            'lab_paid' => round($labPaid, 2),
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
            'endurance' => $endurance,
        ];
    }

    /**
     * Calculate financial details for a lab request
     *
     * @param object $labRequest
     * @param bool $isCompanyPatient
     * @return array
     */
    public function calculateLabRequestFinancials($labRequest, bool $isCompanyPatient): array
    {
        $price = (float) ($labRequest->price ?? 0);
        
        // Calculate discount (only percentage for lab requests)
        $discountPercent = (float) ($labRequest->discount_per ?? 0);
        $totalDiscount = $price * $discountPercent / 100;
        
        // Calculate endurance (company coverage)
        $endurance = $isCompanyPatient ? (float) ($labRequest->endurance ?? 0) : 0;
        
        if($isCompanyPatient){
            $netPayable =  $endurance;
        }else{
            $netPayable = $price - $totalDiscount;
        }
        $amountPaid = (float) ($labRequest->amount_paid ?? 0);  
        if($isCompanyPatient){
            $balance = $endurance - $amountPaid;
        }else{
            $balance = $price - $totalDiscount - $amountPaid;
        }
        return [
            'price' => $price,
            'net_payable' => $netPayable,
            'amount_paid' => $amountPaid,
            'discount' => $totalDiscount,
            'endurance' => $endurance,
            'balance' => $balance,
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
                    'done_by_user_name' => $service->doneByUser?->name,
                    'done_at' => $service->done_at?->toIso8601String(),
                ];
            });
        });
    }

    /**
     * Get doctor name with fallback priority:
     * 1. doctorShift.doctor.name (if doctorShift is loaded)
     * 2. doctor.name (direct relationship)
     * 3. patient.doctor.name (patient's assigned doctor)
     *
     * @return string|null
     */
    private function getDoctorName(): ?string
    {
        // Priority 1: Doctor from doctorShift
        if ($this->relationLoaded('doctorShift') && $this->doctorShift?->relationLoaded('doctor')) {
            return $this->doctorShift->doctor?->name;
        }
        
        // Priority 2: Direct doctor relationship
        if ($this->relationLoaded('doctor')) {
            return $this->doctor?->name;
        }
        
        // Priority 3: Patient's assigned doctor
        if ($this->relationLoaded('patient') && $this->patient?->relationLoaded('doctor')) {
            return $this->patient->doctor?->name;
        }
        
        return null;
    }
}