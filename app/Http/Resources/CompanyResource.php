<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => $this->status,
            // Add other fields if needed for list views or dropdowns
            'finance_account_id' => $this->finance_account_id,
            // 'finance_account' => new FinanceAccountResource($this->whenLoaded('financeAccount')),
        ];
    }
}