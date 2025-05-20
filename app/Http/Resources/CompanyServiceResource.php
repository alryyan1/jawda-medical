<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // This resource is for when you are fetching CompanyService pivot records
        // or iterating through $company->contractedServices
        return [
            // If $this refers to a Service model fetched via belongsToMany:
            'service_id' => $this->id, // Service ID
            'service_name' => $this->name, // Service Name
            'service_group_name' => $this->whenLoaded('serviceGroup', optional($this->serviceGroup)->name),

            // Pivot data
            // When using ->using(CompanyService::class), $this->pivot will be an instance of CompanyService
            'contract_id' => $this->pivot->id ?? null, // ID from the company_service table itself if $incrementing=true on pivot
            'company_id' => $this->pivot->company_id ?? null, // Or directly $this->company_id if this resource is for CompanyService model
            'price' => (float) ($this->pivot->price ?? 0),
            'static_endurance' => (float) ($this->pivot->static_endurance ?? 0),
            'percentage_endurance' => (float) ($this->pivot->percentage_endurance ?? 0),
            'static_wage' => (float) ($this->pivot->static_wage ?? 0),
            'percentage_wage' => (float) ($this->pivot->percentage_wage ?? 0),
            'use_static' => (bool) ($this->pivot->use_static ?? false),
            'approval' => (bool) ($this->pivot->approval ?? false),

            // If $this refers to a CompanyService model instance directly:
            // 'id' => $this->id,
            // 'company_id' => $this->company_id,
            // 'service_id' => $this->service_id,
            // 'service' => new ServiceResource($this->whenLoaded('service')), // To show service details
            // 'price' => (float) $this->price,
            // ... other pivot fields ...
        ];
    }
}