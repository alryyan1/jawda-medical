<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitDiagnosisRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'diagnosis' => ['sometimes', 'nullable', 'string'],
            'complete' => ['sometimes', 'boolean'],
            'is_printed' => ['sometimes', 'boolean'],
            'printed_by_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }
}
