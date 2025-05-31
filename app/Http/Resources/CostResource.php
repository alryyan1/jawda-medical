<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class CostResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'shift_id' => $this->shift_id,
            'user_cost_id' => $this->user_cost,
            'user_cost_name' => $this->whenLoaded('userCost', optional($this->userCost)->name),
            'doctor_shift_id' => $this?->doctor_shift_id,
            'doctor_shift_doctor_name' => $this->whenLoaded('doctorShift', optional($this->doctorShift?->doctor)?->name),
            'description' => $this->description,
            'comment' => $this->comment,
            'amount' => (float) $this->amount,
            'amount_bankak' => (float) $this->amount_bankak,
            'payment_method' => $this->amount_bankak > 0 ? 'bank' : 'cash',
            'total_cost_amount' => $this->amount_bankak > 0 ? (float) $this->amount_bankak : (float) $this->amount,
            'cost_category_id' => $this->cost_category_id,
            'cost_category_name' => $this->whenLoaded('costCategory', optional($this->costCategory)->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'cost_category' => new CostCategoryResource($this->whenLoaded('costCategory')), // <-- Use it here
        ];
    }
}