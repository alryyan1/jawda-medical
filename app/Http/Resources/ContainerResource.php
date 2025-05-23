<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class ContainerResource extends JsonResource {
    public function toArray(Request $request): array {
        return [ 'id' => $this->id, 'container_name' => $this->container_name ];
    }
}