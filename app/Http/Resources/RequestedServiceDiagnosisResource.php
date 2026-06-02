<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestedServiceDiagnosisResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                   => $this->id,
            'requested_service_id' => $this->requested_service_id,
            'user_id'              => $this->user_id,
            'user'                 => $this->whenLoaded('user', fn () => [
                'id'   => $this->user->id,
                'name' => $this->user->name,
            ]),
            'diagnosis'            => $this->diagnosis,
            'complete'             => (bool) $this->complete,
            'completed_at'         => $this->completed_at?->toIso8601String(),
            'is_printed'           => (bool) $this->is_printed,
            'printed_by_user_id'   => $this->printed_by_user_id,
            'printed_by_user'      => $this->whenLoaded('printedByUser', fn () => [
                'id'   => $this->printedByUser->id,
                'name' => $this->printedByUser->name,
            ]),
            'requested_service'    => $this->whenLoaded('requestedService', function () {
                $rs = $this->requestedService;
                return [
                    'id'           => $rs->id,
                    'service_name' => $rs->service?->name ?? "خدمة #{$rs->service_id}",
                    'patient_name' => $rs->doctorVisit?->patient?->name ?? '—',
                    'patient_phone'=> $rs->doctorVisit?->patient?->phone ?? null,
                    'visit_id'     => $rs->doctorvisits_id,
                    'doctor_name'  => $rs->doctorVisit?->doctor?->name ?? '—',
                    'done'         => (bool) $rs->done,
                    'created_at'   => $rs->created_at?->toIso8601String(),
                ];
            }),
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
