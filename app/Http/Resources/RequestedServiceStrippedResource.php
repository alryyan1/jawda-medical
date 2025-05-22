<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class RequestedServiceStrippedResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'service_name' => $this->service->name ?? 'N/A', // Assuming service relationship is loaded
            'price' => (float) $this->price,
            'count' => (int) $this->count,
            'amount_paid' => (float) $this->amount_paid,
            'is_paid' => (bool) $this->is_paid,
            'done' => (bool) $this->done,
        ];
    }
}