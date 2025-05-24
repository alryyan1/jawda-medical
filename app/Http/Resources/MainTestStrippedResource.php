<?php namespace App\Http\Resources; // ...

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Request;

class MainTestStrippedResource extends JsonResource {
    public function toArray(Request $request): array {
        return [ 'id' => $this->id, 'main_test_name' => $this->main_test_name ,'price' => $this->price];
    }
}