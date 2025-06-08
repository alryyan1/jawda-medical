<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class UpdatePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        // $patient = $this->route('patient'); // Get the patient model instance
        // return Auth::user()->can('edit patients'); // Or specific policy: Auth::user()->can('update', $patient);
        return true;
    }

    public function rules(): array
    {
        $patientId = $this->route('patient') ? $this->route('patient')->id : null;

        return [
            'name' => 'sometimes|required|string|max:255',
            'phone' => ['sometimes','required','string','max:10'],
            'gender' => ['sometimes','required', Rule::in(['male', 'female'])],
            'age_day' => 'nullable|integer|min:0|max:30',
            'age_month' => 'nullable|integer|min:0|max:11',
            'age_year' => 'nullable|integer|min:0|max:150',
            'company_id' => 'nullable|integer|exists:companies,id',
            'subcompany_id' => 'nullable|integer|exists:subcompanies,id',
            'company_relation_id' => 'nullable|integer|exists:company_relations,id',
            'paper_fees' => 'nullable|integer|min:0',
            'guarantor' => 'nullable|string|max:255',
            'expire_date' => 'nullable|date_format:Y-m-d',
            'insurance_no' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:1000',
            // These fields are usually managed by specific actions, not general update
            // 'is_lab_paid' => 'sometimes|boolean',
            // 'lab_paid' => 'sometimes|numeric|min:0',
            // 'result_is_locked' => 'sometimes|boolean',
            // 'sample_collected' => 'sometimes|boolean',
            // ... other updatable patient fields from your full Patient model ...
            'present_complains' => 'nullable|string',
            'history_of_present_illness' => 'nullable|string',
            // ... (many clinical string fields) ...
        ];
    }
}