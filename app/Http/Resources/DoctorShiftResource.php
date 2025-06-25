<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

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

        // Calculate entitlements here (using methods from DoctorShift model)
        $cashEntitlement = $this->doctor_credit_cash(); // Assumes this method exists on DoctorShift
        $insuranceEntitlement = $this->doctor_credit_company(); // Assumes this method exists
        $staticWageApplied = ($this->status == false && $this->doctor) ? (float)$this->doctor->static_wage : 0; // Apply static wage only if shift is closed and doctor exists
        $totalDoctorEntitlement = $cashEntitlement + $insuranceEntitlement + $staticWageApplied;

        return [
            'id' => $this->id,
            'doctor_id' => $this->doctor_id,
            'doctor_name' => $this->whenLoaded('doctor', $this->doctor?->name),
            'doctor_specialist_name' => $this->whenLoaded('doctor', $this->doctor?->specialist?->name),
            'user_id_opened' => $this->user_id,
            'user_name_opened' => $this->whenLoaded('user', $this->user?->name),
            'patients_count' => $this->patients_count,
            'shift_id' => $this->shift_id, // General clinic shift_id
            'general_shift_name' => $this->whenLoaded('generalShift', $this->generalShift?->name ?? ('Shift #'.$this->generalShift?->id)),
            
            'status' => (bool) $this->status,
            'status_text' => $this->status ? 'Open' : 'Closed',
            'start_time' => $this->start_time?->toIso8601String(),
            'end_time' => $this->end_time?->toIso8601String(),
            'formatted_start_time' => $startTime ? $startTime->format('Y-m-d h:i A') : 'N/A',
            'formatted_end_time' => $endTime ? $endTime->format('Y-m-d h:i A') : ($this->status ? 'Still Open' : 'N/A'),
            'duration' => $duration,

            // Financials
            'total_doctor_entitlement' => round($totalDoctorEntitlement, 2),
            'cash_entitlement' => round($cashEntitlement, 2),
            'insurance_entitlement' => round($insuranceEntitlement, 2),
            'static_wage_applied' => round($staticWageApplied, 2),

            // Proofing Flags
            'is_cash_revenue_prooved' => (bool) $this->is_cash_revenue_prooved,
            'is_cash_reclaim_prooved' => (bool) $this->is_cash_reclaim_prooved,
            'is_company_revenue_prooved' => (bool) $this->is_company_revenue_prooved,
            'is_company_reclaim_prooved' => (bool) $this->is_company_reclaim_prooved,

            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}