<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'shift_name' => $this->whenLoaded('shift', $this->shift?->name ?? $this->shift_id), // Example
            'user_cost_id' => $this->user_cost, // Assuming user_cost is the user_id
            'user_cost_name' => $this->whenLoaded('userCost', $this->userCost?->name),
            'doctor_shift_id' => $this->doctor_shift_id,
            'doctor_shift_id_for_sub_cost' => $this->doctor_shift_id_for_sub_cost,
            'sub_service_cost_id' => $this->sub_service_cost_id,
            'doctor_shift_doctor_name' => $this->whenLoaded('doctorShift', $this->doctorShift?->doctor?->name),
            'description' => $this->description,
            'comment' => $this->comment,
            'amount' => (float) $this->amount, // Cash portion
            'amount_bankak' => (float) $this->amount_bankak, // Bank portion
            'cost_category_id' => $this->cost_category_id,
            'cost_category_name' => $this->whenLoaded('costCategory', $this->costCategory?->name),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}