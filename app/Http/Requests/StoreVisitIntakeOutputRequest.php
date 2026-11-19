<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitIntakeOutputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'recorded_at' => ['nullable', 'date'],
            'feeding' => ['nullable', 'numeric', 'min:0'],
            'iv_fluid' => ['nullable', 'numeric', 'min:0'],
            'maintenance' => ['nullable', 'numeric', 'min:0'],
            'urine_output' => ['nullable', 'numeric', 'min:0'],
            'insensible_loss' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
