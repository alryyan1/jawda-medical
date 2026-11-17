<?php

namespace App\Http\Requests;

use App\Models\VisitDose;
use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitDoseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medication_name' => ['sometimes', 'string', 'max:255'],
            'dosage' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'in:'.implode(',', [
                VisitDose::STATUS_GIVEN,
                VisitDose::STATUS_NOT_GIVEN,
                VisitDose::STATUS_POSTPONED,
            ])],
            'administered_at' => ['sometimes', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
