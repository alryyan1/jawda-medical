<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PdfSettingResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'font_family' => $this->font_family,
            'font_size' => $this->font_size,
            'logo_path' => $this->logo_path,
            'logo_url' => $this->logo_path ? asset('storage/' . $this->logo_path) : null,
            'logo_width' => $this->logo_width ? (float) $this->logo_width : null,
            'logo_height' => $this->logo_height ? (float) $this->logo_height : null,
            'logo_position' => $this->logo_position,
            'hospital_name' => $this->hospital_name,
            'header_image_path' => $this->header_image_path,
            'header_image_url' => $this->header_image_path ? asset('storage/' . $this->header_image_path) : null,
            'footer_phone' => $this->footer_phone,
            'footer_address' => $this->footer_address,
            'footer_email' => $this->footer_email,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
