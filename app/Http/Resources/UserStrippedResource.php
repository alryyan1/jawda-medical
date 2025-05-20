<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserStrippedResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return [];
        }
        
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            // 'avatar_url' => $this->avatar_url, // If users have avatars
        ];
    }
}