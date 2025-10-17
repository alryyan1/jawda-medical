<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class CompanyResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'email' => $this->email,
            'status' => (bool) $this->status,
            'lab_endurance' => (float) $this->lab_endurance,
            'service_endurance' => (float) $this->service_endurance,
            'lab_roof' => (int) $this->lab_roof,
            'service_roof' => (int) $this->service_roof,
            'finance_account_id' => $this->finance_account_id,
            'finance_account' => new FinanceAccountResource($this->whenLoaded('financeAccount')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            // Optionally, a count or brief summary of contracted services
            'contracted_services_count' => $this->whenCounted('contractedServices'),
            'lab2lab_firestore_id' => $this->lab2lab_firestore_id,
            // 'contracted_services' => CompanyServiceEntryResource::collection($this->whenLoaded('companyServiceEntries')), // If you fetch them directly
                    'contracted_main_tests_count' => $this->whenCounted('contractedMainTests'), // NEW

        ];
    }
}