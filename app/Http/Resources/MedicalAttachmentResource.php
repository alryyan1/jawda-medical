<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MedicalAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'doctor_visit_id' => $this->doctor_visit_id,
            'category' => $this->category,
            'title' => $this->title,
            'original_filename' => $this->original_filename,
            'url' => asset('storage/'.$this->file_path),
            'mime_type' => $this->mime_type,
            'file_size' => $this->file_size,
            'note' => $this->note,
            'uploaded_by_user' => $this->whenLoaded('uploadedByUser', fn () => $this->uploadedByUser ? [
                'id' => $this->uploadedByUser->id,
                'name' => $this->uploadedByUser->name,
            ] : null),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
