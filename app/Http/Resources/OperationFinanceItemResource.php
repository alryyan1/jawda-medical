<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OperationFinanceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'operation_id' => $this->operation_id,
            'operation_item_id' => $this->operation_item_id,
            'item_type' => $this->item_type, // Legacy/Fallback
            // Derive category from relation if available, else fallback to column
            'category' => $this->operationItem ? $this->operationItem->type : $this->category,
            'description' => $this->description,
            'amount' => (float) $this->amount,
            'is_auto_calculated' => (bool)$this->is_auto_calculated,
            'operation_item' => $this->whenLoaded('operationItem'),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
