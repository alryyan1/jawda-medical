<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePatientAppointmentRequest extends FormRequest
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
            'doctor_id' => ['nullable', 'exists:doctors,id'],
            'scheduled_at' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
            'send_whatsapp' => ['sometimes', 'boolean'],
        ];
    }
}
