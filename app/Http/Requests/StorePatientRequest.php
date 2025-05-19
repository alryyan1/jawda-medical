<?php

// app/Http/Requests/StorePatientRequest.php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Or add specific authorization logic
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'phone' => ['required', 'string', 'max:20', /* Rule::unique('patients','phone') -> consider if phone must be unique system-wide or just a warning */],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'age_year' => 'nullable|integer|min:0|max:150',
            'age_month' => 'nullable|integer|min:0|max:11',
            'age_day' => 'nullable|integer|min:0|max:30',
            'address' => 'nullable|string|max:1000',
            'company_id' => 'nullable|exists:companies,id',
            'doctor_id' => 'required|exists:doctors,id', // Doctor assigned for the initial visit
            'notes' => 'nullable|string', // Could map to patient.present_complains or visit.notes

            // Additional fields that might come from the form to create the DoctorVisit
            // 'visit_type' => 'required|string|max:50', // e.g., "New", "Follow-up"
            // 'shift_id' => 'required|exists:shifts,id', // You'll need to pass this or determine it
        ];
    }

    public function messages(): array
    {
        // Custom messages, especially useful for Arabic
        return [
            'name.required' => 'حقل اسم المريض مطلوب.',
            'phone.required' => 'حقل رقم الهاتف مطلوب.',
            'gender.required' => 'حقل الجنس مطلوب.',
            'doctor_id.required' => 'يجب اختيار طبيب.',
            'doctor_id.exists' => 'الطبيب المختار غير صحيح.',
            // ... other custom messages
        ];
    }
}