<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChildTestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'main_test_id' => $this->main_test_id,
            'child_test_name' => $this->child_test_name,
            'low' => $this->low,
            'upper' => $this->upper,
            'defval' => $this->defval,
            'unit_id' => $this->unit_id,
            'unit_name' => $this->whenLoaded('unit', optional($this->unit)->name),
            'unit' => new UnitResource($this->whenLoaded('unit')),
            'normalRange' => $this->normalRange,
            'max' => $this->max,
            'lowest' => $this->lowest,
            'test_order' => $this->test_order,
            'child_group_id' => $this->child_group_id,
            'child_group_name' => $this->whenLoaded('childGroup', optional($this->childGroup)->name),
            'child_group' => new ChildGroupResource($this->whenLoaded('childGroup')),
            'json_params' => $this->json_params,
            'json_parameter' => $this->json_params, // alias for clients expecting this name
            'options' => ChildTestOptionResource::collection($this->whenLoaded('options')), // For later
        ];
    }
}