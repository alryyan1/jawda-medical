<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LabTestStatisticResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'main_test_id' => $this->main_test_id,
            'main_test_name' => $this->main_test_name,
            'container_name' => $this->container_name,
            // 'package_name' => $this->whenLoaded('mainTest.package', $this->mainTest?->package?->package_name), // If you track packages
            'request_count' => (int) $this->request_count,
            'total_price_generated' => (float) ($this->total_price_generated ?? 0), // Sum of LabRequest.price
            'total_amount_paid' => (float) ($this->total_amount_paid ?? 0),     // Sum of LabRequest.amount_paid
            // Add other relevant aggregated data if needed
        ];
    }
}