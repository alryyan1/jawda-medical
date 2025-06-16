<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class DeviceChildTestNormalRangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, // The ID of the record in child_test_devices table
            'child_test_id' => $this->child_test_id,
            'device_id' => $this->device_id,
            'normal_range' => $this->normal_range,
            // Optionally include child_test_name and device_name if needed by eager loading
            // 'child_test_name' => $this->whenLoaded('childTest', $this->childTest->child_test_name),
            // 'device_name' => $this->whenLoaded('device', $this->device->name),
        ];
    }
}