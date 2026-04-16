<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DeviceChildTestNormalRangeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'child_test_id' => $this->child_test_id,
            'device_id'    => $this->device_id,
            'device_name'  => $this->whenLoaded('device', fn() => $this->device->name),
            'normal_range' => $this->normal_range,
            'is_default'   => (bool) $this->is_default,
            'user_id'      => $this->user_id,
            'created_at'   => $this->created_at,
            'updated_at'   => $this->updated_at,
        ];
    }
}
