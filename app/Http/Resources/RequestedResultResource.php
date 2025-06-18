<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RequestedResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lab_request_id' => $this->lab_request_id,
            'patient_id' => $this->patient_id,
            'main_test_id' => $this->main_test_id,
            'child_test_id' => $this->child_test_id,
            'result' => $this->result,
            'normal_range' => $this->normal_range, // This is the snapshot
            'unit_id' => $this->unit_id,
            'unit_name' => $this->whenLoaded('unit', $this->unit?->name), // Eager load 'unit' relation
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // If you add back user/auth tracking:
            // 'entered_by_user_id' => $this->entered_by_user_id,
            // 'entered_by_user_name' => $this->whenLoaded('enteredBy', $this->enteredBy?->name),
            // 'entered_at' => $this->entered_at?->toIso8601String(),
            // 'authorized_by_user_id' => $this->authorized_by_user_id,
            // 'authorized_by_user_name' => $this->whenLoaded('authorizedBy', $this->authorizedBy?->name),
            // 'authorized_at' => $this->authorized_at?->toIso8601String(),

            // Include child test details if needed for context
            'child_test_name' => $this->whenLoaded('childTest', $this->childTest?->child_test_name),
        ];
    }
}