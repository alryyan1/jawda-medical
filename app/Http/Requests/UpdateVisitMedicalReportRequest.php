<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateVisitMedicalReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'nullable', 'string'],
            'complete' => ['sometimes', 'boolean'],
            'is_printed' => ['sometimes', 'boolean'],
            'printed_by_user_id' => ['sometimes', 'nullable', 'exists:users,id'],
        ];
    }
}
