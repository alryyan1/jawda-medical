<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitIntakeOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recorded_at' => ['sometimes', 'date'],
            'feeding' => ['sometimes', 'numeric', 'min:0'],
            'iv_fluid' => ['sometimes', 'numeric', 'min:0'],
            'maintenance' => ['sometimes', 'numeric', 'min:0'],
            'urine_output' => ['sometimes', 'numeric', 'min:0'],
            'insensible_loss' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
