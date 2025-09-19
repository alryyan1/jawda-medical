<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Carbon\Carbon;

class PatientResource extends JsonResource
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
            'gender' => $this->gender,
            'age_year' => $this->age_year,
            'age_month' => $this->age_month,
            'age_day' => $this->age_day,
            'full_age' => $this->getFullAgeAttribute(), // Accessor for display
            'doctor'=> new DoctorStrippedResource($this->whenLoaded('doctor')), 
            'result_is_locked' => (bool) $this->result_is_locked,
            'address' => $this->address,
            'gov_id' => $this->gov_id, // Governorate ID or name
            // 'country_id' => $this->country_id, // If you have country management

            // Insurance & Company Information
            'company_id' => $this->company_id,
            'company' => new CompanyStrippedResource($this->whenLoaded('company')), // Use a stripped resource for dropdowns/light display
            'subcompany_id' => $this->subcompany_id,
            'subcompany' => new SubcompanyStrippedResource($this->whenLoaded('subcompany')),
            'company_relation_id' => $this->company_relation_id,
            'company_relation' => new CompanyRelationStrippedResource($this->whenLoaded('companyRelation')),
            'insurance_no' => $this->insurance_no,
            'expire_date' => $this->expire_date ? Carbon::parse($this->expire_date)->toDateString() : null, // Format as YYYY-MM-DD
            'guarantor' => $this->guarantor,
            'paper_fees' => (float) $this->paper_fees,

            // Lab & Visit Statuses (these might be better on a VisitResource or specialized PatientVisitSummaryResource)
            'is_lab_paid' => (bool) $this->is_lab_paid,
            'lab_paid' => (float) $this->lab_paid,
            'result_is_locked' => (bool) $this->result_is_locked,
            'sample_collected' => (bool) $this->sample_collected,
            'sample_collect_time' => $this->sample_collect_time, // Consider formatting if time only
            'result_print_date' => $this->result_print_date?->toIso8601String(),
            'sample_print_date' => $this->sample_print_date?->toIso8601String(),
            'visit_number' => (int) $this->visit_number,
            'result_auth' => (bool) $this->result_auth,
            'auth_date' => $this->auth_date?->toIso8601String(),

            // Discount
            'discount' => (float) $this->discount,
            'discount_comment' => $this->discount_comment,

            // Visit-specific flags (Consider if these truly belong at the top-level Patient resource
            // or if they are more related to the LATEST visit. If latest visit, they should be on a Visit resource).
            // For now, including them as per your Patient model's fillable/casts.
            'doctor_finish' => (bool) $this->doctor_finish,
            'doctor_lab_request_confirm' => (bool) $this->doctor_lab_request_confirm,
            'doctor_lab_urgent_confirm' => (bool) $this->doctor_lab_urgent_confirm,
            
            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Relationships loaded for context
            'user' => new UserStrippedResource($this->whenLoaded('user')), // User who registered
            'primary_doctor' => new DoctorStrippedResource($this->whenLoaded('primaryDoctor')), // Primary doctor linked in patients table
            'shift' => new ShiftResource($this->whenLoaded('shift')), // Shift of registration

            // Example for latest visit summary if needed here:
            // 'latest_visit' => new DoctorVisitStrippedResource($this->whenLoaded('latestDoctorVisit')),
            
            // This is included because PatientController->store loads it for the response after registration.
            // This visit is the *initial* visit created during patient registration.
            'doctor_visit' => new DoctorVisitResource($this->whenLoaded('doctorVisit')),
            'has_cbc' => $this->doctorVisit?->hasCbc,
            'result_url' => $this->result_url,
        ];
    }
}