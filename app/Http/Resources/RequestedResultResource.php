<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class RequestedResultResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'lab_request_id' => $this->lab_request_id,
            'patient_id' => $this->patient_id,
            'main_test_id' => $this->main_test_id,
            'child_test_id' => $this->child_test_id,
            'child_test_name' => $this->whenLoaded('childTest', optional($this->childTest)->child_test_name),
            'result' => $this->result,
            'normal_range' => $this->normal_range,
            'unit_name' => $this->unit_name,
            'flags' => $this->flags,
            'result_comment' => $this->result_comment,
            'entered_by_user_id' => $this->entered_by_user_id,
            'entered_by_user_name' => $this->whenLoaded('enteredBy', optional($this->enteredBy)->name),
            'entered_at' => $this->entered_at?->toIso8601String(),
            'authorized_by_user_id' => $this->authorized_by_user_id,
            'authorized_by_user_name' => $this->whenLoaded('authorizedBy', optional($this->authorizedBy)->name),
            'authorized_at' => $this->authorized_at?->toIso8601String(),
        ];
    }
}