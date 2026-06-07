<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DoctorShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $startTime = $this->start_time ? Carbon::parse($this->start_time) : null;
        $endTime = $this->end_time ? Carbon::parse($this->end_time) : null;
        $duration = null;
        if ($startTime && $endTime) {
            $duration = $startTime->diff($endTime)->format('%Hh %Im');
        } elseif ($startTime && $this->status) {
            $duration = $startTime->diffForHumans(now(), true) . ' (open)';
        }

        // Only calculate expensive financials when explicitly requested via query param
        // or when visits relation is already loaded
        $includeFinancials = $request->boolean('include_financials') || $this->relationLoaded('visits');
        
        $cashEntitlement = 0;
        $insuranceEntitlement = 0;
        $totalIncome = 0;
        $clinicInsurance = 0;
        
        if ($includeFinancials) {
            /** @var \App\Models\DoctorShift $model */
            $model = $this->resource;
            $fin = $model->precomputedFinancials ?? $model->financialSummaryFast();
            $cashEntitlement      = $fin['doctor_credit_cash'];
            $insuranceEntitlement = $fin['doctor_credit_insurance'];
            $totalIncome          = $fin['total_paid_services'];
            $clinicInsurance      = $fin['clinic_endurance'];
        }
        
        $staticWageApplied = ($this->status == false && $this->doctor) ? (float)$this->doctor->static_wage : 0;
        $totalDoctorEntitlement = $cashEntitlement + $insuranceEntitlement + $staticWageApplied;

        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'total_income' => $totalIncome,
            'clinic_enurance' => $clinicInsurance,
            'doctor_name' => $this->whenLoaded('doctor', $this->doctor?->name),
            'doctor_specialist_name' => $this->whenLoaded('doctor', $this->doctor?->specialist?->name),
            'doctor_cash_percentage' => $this->whenLoaded('doctor', $this->doctor ? (float) $this->doctor->cash_percentage : null),
            'doctor_company_percentage' => $this->whenLoaded('doctor', $this->doctor ? (float) $this->doctor->company_percentage : null),
            'doctor_static_wage' => $this->whenLoaded('doctor', $this->doctor ? (float) $this->doctor->static_wage : null),
            'user_id_opened' => $this->user_id,
            'user_name_opened' => $this->whenLoaded('user', $this->user?->name),
            'patients_count' => $this->patients_count,
            'shift_id' => $this->shift_id,
            'general_shift_name' => $this->whenLoaded('generalShift', $this->generalShift?->name ?? ('Shift #'.$this->generalShift?->id)),
            'firebase_id' => $this->doctor?->firebase_id,
            'specialist_firestore_id' => $this->doctor?->specialist?->firestore_id,
            'status' => (bool) $this->status,
            'status_text' => $this->status ? 'Open' : 'Closed',
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'formatted_start_time' => $startTime ? $startTime->format('Y-m-d h:i A') : 'N/A',
            'formatted_end_time' => $endTime ? $endTime->format('Y-m-d h:i A') : ($this->status ? 'Still Open' : 'N/A'),
            'duration' => $duration,

            // Financials (only populated when requested)
            'total_doctor_entitlement' => round($totalDoctorEntitlement, 2),
            'cash_entitlement' => round($cashEntitlement, 2),
            'insurance_entitlement' => round($insuranceEntitlement, 2),
            'static_wage_applied' => round($staticWageApplied, 2),

            // Snapshot columns (null for open/uncomputed shifts)
            'snap_patients_count'              => $this->snap_patients_count,
            'snap_total_paid'                  => $this->snap_total_paid,
            'snap_total_cash_revenue'          => $this->snap_total_cash_revenue,
            'snap_total_insurance_revenue'     => $this->snap_total_insurance_revenue,
            'snap_total_insurance_services'    => $this->snap_total_insurance_services,
            'snap_total_bank'                  => $this->snap_total_bank,
            'snap_doctor_cash_percentage'      => $this->snap_doctor_cash_percentage,
            'snap_doctor_insurance_percentage' => $this->snap_doctor_insurance_percentage,
            'snap_doctor_cash_entitlement'     => $this->snap_doctor_cash_entitlement,
            'snap_doctor_insurance_entitlement'=> $this->snap_doctor_insurance_entitlement,
            'snap_doctor_fixed_entitlement'    => $this->snap_doctor_fixed_entitlement,
            'snap_total_doctor_entitlement'    => $this->snap_total_doctor_entitlement,

            'has_journal' => (bool) $this->has_journal,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'doctor_visits_count' => $this->doctor_visits_count ?? $this->patients_count,
        ];
    }
}