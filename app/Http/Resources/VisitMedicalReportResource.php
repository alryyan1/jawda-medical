<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VisitMedicalReportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'doctor_visit_id' => $this->doctor_visit_id,
            'user_id' => $this->user_id,
            'user' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ] : null),
            'content' => $this->content,
            'complete' => (bool) $this->complete,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'is_printed' => (bool) $this->is_printed,
            'printed_by_user_id' => $this->printed_by_user_id,
            'printed_by_user' => $this->whenLoaded('printedByUser', fn () => $this->printedByUser ? [
                'id' => $this->printedByUser->id,
                'name' => $this->printedByUser->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
