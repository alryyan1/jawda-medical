<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitVitalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'blood_pressure_systolic' => ['nullable', 'integer', 'min:0', 'max:300'],
            'blood_pressure_diastolic' => ['nullable', 'integer', 'min:0', 'max:200'],
            'temperature' => ['nullable', 'numeric', 'min:30', 'max:45'],
            'heart_rate' => ['nullable', 'integer', 'min:0', 'max:300'],
            'respiratory_rate' => ['nullable', 'integer', 'min:0', 'max:100'],
            'pain_scale' => ['nullable', 'integer', 'min:0', 'max:10'],
            'spo2' => ['nullable', 'integer', 'min:0', 'max:100'],
            'weight' => ['nullable', 'numeric', 'min:0', 'max:500'],
            'height' => ['nullable', 'numeric', 'min:0', 'max:300'],
            'rbs' => ['nullable', 'numeric', 'min:0', 'max:1000'],
        ];
    }
}
