<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lean resource for the active-patients list view.
 * Only includes fields consumed by ActivePatientCard and its child dialogs.
 * Does NOT serialize requestedServices / labRequests arrays — those stay
 * in memory only for the balance_due calculation.
 */
class DoctorVisitListItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isCompanyPatient = !empty($this->patient?->company_id);
        $balanceDue = $this->computeBalanceDue($isCompanyPatient);

        return [
            'id'                       => $this->id,
            'number'                   => $this->number,
            'queue_number'             => $this->queue_number,
            'status'                   => $this->status,
            'is_online'                => (bool) $this->is_online,
            'is_new'                   => (bool) $this->is_new,
            'only_lab'                 => (bool) $this->only_lab,
            'balance_due'              => $balanceDue,
            'requested_services_count' => $this->requested_services_count,
            'doctor_id'                => $this->doctor_id,
            'doctor_shift_id'          => $this->doctor_shift_id,

            // Truthy company check for card styling — full object not needed
            'company' => $this->patient?->company
                ? ['id' => $this->patient->company->id, 'name' => $this->patient->company->name]
                : null,

            'patient' => $this->whenLoaded('patient', function () {
                $p = $this->patient;
                return [
                    'id'         => $p->id,
                    'name'       => $p->name,
                    'phone'      => $p->phone,
                    'gender'     => $p->gender,
                    'age_year'   => $p->age_year,
                    'age_month'  => $p->age_month,
                    'age_day'    => $p->age_day,
                    'full_age'   => $p->getFullAgeAttribute(),
                    'company_id' => $p->company_id,
                    'company'    => $p->company
                        ? ['id' => $p->company->id, 'name' => $p->company->name, 'status' => $p->company->status]
                        : null,
                ];
            }),
        ];
    }

    /**
     * Single-pass balance calculation over already-loaded relations.
     * requestedServices and patientLabRequests are loaded in the controller
     * query solely for this computation — they are never serialized.
     */
    private function computeBalanceDue(bool $isCompanyPatient): float
    {
        $total = 0.0;
        $paid  = 0.0;

        if ($this->relationLoaded('requestedServices')) {
            foreach ($this->requestedServices as $s) {
                $price     = (float) ($s->price ?? 0);
                $count     = (int)   ($s->count ?? 1);
                $discount  = ($price * $count * (float) ($s->discount_per ?? 0) / 100) + (float) ($s->discount ?? 0);
                $endurance = $isCompanyPatient ? (float) ($s->endurance ?? 0) * $count : 0.0;
                $total    += $price * $count - $discount - $endurance;
                $paid     += (float) ($s->amount_paid ?? 0);
            }
        }

        if ($this->relationLoaded('patientLabRequests')) {
            foreach ($this->patientLabRequests as $lr) {
                $price     = (float) ($lr->price ?? 0);
                $discount  = $price * (float) ($lr->discount_per ?? 0) / 100;
                $endurance = $isCompanyPatient ? (float) ($lr->endurance ?? 0) : 0.0;
                $total    += $isCompanyPatient ? $endurance : $price - $discount;
                $paid     += (float) ($lr->amount_paid ?? 0);
            }
        }

        return round($total - $paid, 2);
    }
}
