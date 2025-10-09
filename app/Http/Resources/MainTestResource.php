<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MainTestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'main_test_name' => $this->main_test_name,
            // Unified field used by frontend (lr.main_test.name)
            'name' => $this->main_test_name,
            'pack_id' => $this->pack_id,
            // 'pack_name' => $this->whenLoaded('pack', optional($this->pack)->name), // If Pack model exists
            'pageBreak' => (bool) $this->pageBreak,
            'container_id' => $this->container_id,
            'container_name' => $this->whenLoaded('container', optional($this->container)->container_name),
            'container' => new ContainerResource($this->whenLoaded('container')),
            'price' => (float) $this->price,
            'divided' => (bool) $this->divided,
            'available' => (bool) $this->available,
            'is_special_test' => (bool) $this->is_special_test,
                        'child_tests' => ChildTestResource::collection($this->whenLoaded('childTests')),

            // Include timestamps if your model has them
            // 'created_at' => $this->created_at?->toIso8601String(),
            // 'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}