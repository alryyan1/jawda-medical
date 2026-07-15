<?php
namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PartyServiceCostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            // $this is a Service model fetched via Party::services(), with ->pivot populated
            'service_id' => $this->id,
            'service_name' => $this->name,
            'service_group_name' => $this->whenLoaded('serviceGroup', optional($this->serviceGroup)->name),

            'contract_id' => $this->pivot->id ?? null,
            'party_id' => $this->pivot->party_id ?? null,
            'price' => (float) ($this->pivot->price ?? 0),
        ];
    }
}
