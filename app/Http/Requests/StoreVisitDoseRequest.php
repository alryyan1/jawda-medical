<?php

namespace App\Http\Requests;

use App\Models\VisitDose;
use Illuminate\Foundation\Http\FormRequest;

class StoreVisitDoseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'medication_name' => ['required', 'string', 'max:255'],
            'dosage' => ['required', 'string', 'max:255'],
            'status' => ['required', 'in:'.implode(',', [
                VisitDose::STATUS_GIVEN,
                VisitDose::STATUS_NOT_GIVEN,
                VisitDose::STATUS_POSTPONED,
            ])],
            'administered_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
