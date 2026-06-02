<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lean resource for the patients list page (TodaysPatientsPage).
 * Only serializes fields the list actually renders.
 * requestedServices and patientLabRequests are loaded with financial columns
 * only (no joins to services/main_tests tables) — they are iterated here
 * for totals and then discarded, never serialized.
 */
class PatientVisitSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isCompany = !empty($this->patient?->company_id);
        $totals    = $this->computeTotals($isCompany);

        return [
            'id'         => $this->id,
            'number'     => $this->number,
            'created_at' => $this->created_at?->toIso8601String(),
            'status'     => $this->status,

            'doctor_id'   => $this->doctor_id,
            'doctor_name' => $this->doctor?->name ?? '—',

            'patient' => $this->whenLoaded('patient', fn () => [
                'id'      => $this->patient->id,
                'name'    => $this->patient->name,
                'phone'   => $this->patient->phone,
                'company' => $this->patient->company
                    ? ['id' => $this->patient->company->id, 'name' => $this->patient->company->name]
                    : null,
                'company_id' => $this->patient->company_id,
            ]),

            'company' => $this->patient?->company
                ? ['id' => $this->patient->company->id, 'name' => $this->patient->company->name]
                : null,

            // Financial totals (computed from lean-loaded relations)
            'total_services_amount'   => $totals['total_services_amount'],
            'total_services_paid'     => $totals['total_services_paid'],
            'total_lab_value_will_pay'=> $totals['total_lab_value_will_pay'],
            'lab_paid'                => $totals['lab_paid'],
            'total_paid'              => $totals['total_paid'],
            'balance_due'             => $totals['balance_due'],
        ];
    }

    private function computeTotals(bool $isCompany): array
    {
        $svcAmount = 0.0; $svcPaid = 0.0; $svcNet = 0.0;
        $labWillPay = 0.0; $labPaid = 0.0;

        if ($this->relationLoaded('requestedServices')) {
            foreach ($this->requestedServices as $s) {
                $price      = (float) ($s->price ?? 0);
                $count      = (int)   ($s->count ?? 1);
                $sub        = $price * $count;
                $discount   = ($sub * (float) ($s->discount_per ?? 0) / 100) + (float) ($s->discount ?? 0);
                $endurance  = $isCompany ? (float) ($s->endurance ?? 0) * $count : 0.0;
                $svcAmount += $sub;
                $svcNet    += $sub - $discount - $endurance;
                $svcPaid   += (float) ($s->amount_paid ?? 0);
            }
        }

        if ($this->relationLoaded('patientLabRequests')) {
            foreach ($this->patientLabRequests as $lr) {
                $price     = (float) ($lr->price ?? 0);
                $discount  = $price * (float) ($lr->discount_per ?? 0) / 100;
                $endurance = $isCompany ? (float) ($lr->endurance ?? 0) : 0.0;
                $labWillPay += $isCompany ? $endurance - $discount : $price - $discount;
                if (!empty($lr->is_paid)) {
                    $labPaid += (float) ($lr->amount_paid ?? 0);
                }
            }
        }

        $totalPaid  = $svcPaid + $labPaid;
        $balanceDue = ($svcNet + $labWillPay) - $totalPaid;

        return [
            'total_services_amount'    => round($svcAmount, 2),
            'total_services_paid'      => round($svcPaid, 2),
            'total_lab_value_will_pay' => round($labWillPay, 2),
            'lab_paid'                 => round($labPaid, 2),
            'total_paid'               => round($totalPaid, 2),
            'balance_due'              => round($balanceDue, 2),
        ];
    }
}
