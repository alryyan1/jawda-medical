<?php
// app/Http/Resources/LabRequestResource.php
namespace App\Http\Resources;
use Illuminate\Http\Request; 
use Illuminate\Http\Resources\Json\JsonResource;
// Import RequestedResultResource if you create one, or inline the structure
// use App\Http\Resources\RequestedResultResource; 

class LabRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'main_test_id' => $this->main_test_id,
            // Load full MainTest with its childTests for result entry context
            'main_test' => new MainTestResource($this->whenLoaded('mainTest')), 
            'pid' => $this->pid,
            'patient_name' => $this->whenLoaded('patient', optional($this->patient)->name),
            'doctor_visit_id' => $this->doctor_visit_id,
            'hidden' => (bool) $this->hidden,
            'is_lab2lab' => (bool) $this->is_lab2lab,
            'valid' => (bool) $this->valid,
            'no_sample' => (bool) $this->no_sample,
            'price' => (float) $this->price,
            'amount_paid' => (float) $this->amount_paid,
            'discount_per' => (int) $this->discount_per,
            'is_bankak' => (bool) $this->is_bankak,
            'comment' => $this->comment, // Comment for the LabRequest itself
            'user_requested' => $this->user_requested,
            'requesting_user_name' => $this->whenLoaded('requestingUser', optional($this->requestingUser)->name),
            'user_deposited' => $this->user_deposited,
            'deposit_user_name' => $this->whenLoaded('depositUser', optional($this->depositUser)->name),
            'approve' => (bool) $this->approve,
            'endurance' => (float) $this->endurance,
            'is_paid' => (bool) $this->is_paid,
            'sample_id' => $this->sample_id,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // --- ADD/ENSURE THIS ---
            // Eager load 'results.childTest.unit' if needed for display consistency
            'results' => RequestedResultResource::collection($this->whenLoaded('results')), 
            // If you don't have RequestedResultResource yet, you can inline:
            // 'results' => $this->whenLoaded('results', function() {
            //     return $this->results->map(function ($result) {
            //         return [
            //             'id' => $result->id,
            //             'child_test_id' => $result->child_test_id,
            //             'result' => $result->result,
            //             'flags' => $result->flags,
            //             'result_comment' => $result->result_comment,
            //             'normal_range' => $result->normal_range, // From RequestedResult table
            //             'unit_name' => $result->unit_name,       // From RequestedResult table
            //             'authorized_at' => $result->authorized_at?->toIso8601String(),
            //             // ... other RequestedResult fields
            //         ];
            //     });
            // }),
        ];
    }
}