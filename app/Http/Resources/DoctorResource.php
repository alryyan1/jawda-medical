<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
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
            'name' => $this->name,
            'phone' => $this->phone,
            'cash_percentage' => $this->cash_percentage,
            'company_percentage' => $this->company_percentage,  
            'static_wage' => $this->static_wage,
            'lab_percentage' => $this->lab_percentage,
            'specialist_id' => $this->specialist_id,
            'specialist_name' => $this->whenLoaded('specialist', $this?->specialist?->name), // Eager load 'specialist'
            'start' => $this->start,
            'image' => $this->image, // You might want to return a full URL if storing paths
            'finance_account_id' => $this->finance_account_id,
            'finance_account_name' => $this->whenLoaded('financeAccount', optional($this->financeAccount)->name),
            'finance_account_id_insurance' => $this->finance_account_id_insurance,
            'insurance_finance_account_name' => $this->whenLoaded('insuranceFinanceAccount', optional($this->insuranceFinanceAccount)->name),
            'calc_insurance' => $this->calc_insurance,
            'created_at' => optional($this->created_at)->toIso8601String(),
            'updated_at' => optional($this->updated_at)->toIso8601String(),
            // Add user details if a doctor is linked to a user
            'user_id' => $this->whenLoaded('user', optional($this->user)->id),
            'username' => $this->whenLoaded('user', optional($this->user)->username),
        ];
    }
}