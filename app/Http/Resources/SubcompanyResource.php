<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class SubcompanyResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'company_id' => $this->company_id,
            'lab_endurance' => (float) $this->lab_endurance,
            'service_endurance' => (float) $this->service_endurance,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}