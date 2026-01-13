<?php namespace App\Http\Resources;
use Illuminate\Http\Request; use Illuminate\Http\Resources\Json\JsonResource;
class UserResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'username' => $this->username,
            // 'email' => $this->email, // If you have it
            'doctor_id' => $this->doctor_id,
            'is_nurse' => (bool) $this->is_nurse,
            'user_money_collector_type' => $this->user_money_collector_type,
            'is_supervisor' => (bool) $this->is_supervisor,
            'is_active' => (bool) $this->is_active,
            'user_type' => $this->user_type,
            'nav_items' => $this->nav_items ? json_decode($this->nav_items, true) : null,
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
            'roles' => RoleResource::collection($this->whenLoaded('roles')),
            'permissions' => PermissionResource::collection($this->whenLoaded('permissions')), // Direct permissions
            // 'all_permissions' => PermissionResource::collection($this->getAllPermissions()), // All permissions via roles & direct
        ];
    }
}