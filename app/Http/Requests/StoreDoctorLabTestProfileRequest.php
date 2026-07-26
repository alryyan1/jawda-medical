<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDoctorLabTestProfileRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->doctor_id !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'main_test_ids' => ['required', 'array', 'min:1'],
            'main_test_ids.*' => ['integer', 'exists:main_tests,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'اسم المجموعة مطلوب.',
            'main_test_ids.required' => 'يجب اختيار فحص واحد على الأقل.',
            'main_test_ids.min' => 'يجب اختيار فحص واحد على الأقل.',
        ];
    }
}
