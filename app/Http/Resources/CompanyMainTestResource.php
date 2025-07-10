<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyMainTestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // $this->resource here is an instance of MainTest model
        // when fetched via $company->contractedMainTests()
        return [
            'main_test_id' => $this->id,
            'main_test_name' => $this->main_test_name,
            'default_price' => (float) $this->price, // Original price from main_tests table
            'container_name' => $this->whenLoaded('container', optional($this->container)->container_name),

            // Pivot data from company_main_test table
            'contract_details' => $this->whenPivotLoaded('company_main_test', function () {
                return [
                    'contract_id' => $this->pivot->id, // ID of the company_main_test record
                    'company_id' => $this->pivot->company_id,
                    'status' => (bool) $this->pivot->status,
                    'price' => (float) $this->pivot->price, // Contracted price
                    'approve' => (bool) $this->pivot->approve,
                    'endurance_static' => (int) $this->pivot->endurance_static,
                    'endurance_percentage' => (float) $this->pivot->endurance_percentage,
                    'use_static' => (bool) $this->pivot->use_static,
                ];
            }),
        ];
    }
}