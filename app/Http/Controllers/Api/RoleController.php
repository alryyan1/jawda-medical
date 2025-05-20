<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Http\Resources\RoleResource;
use App\Http\Resources\PermissionResource;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class RoleController extends Controller
{
    public function __construct()
    {
        // Apply middleware for permissions. Adjust permission names as needed.
        $this->middleware('can:list roles')->only(['index', 'indexList']);
        $this->middleware('can:view roles')->only('show');
        $this->middleware('can:create roles')->only('store');
        $this->middleware('can:edit roles')->only('update'); // Covers assigning permissions too
        $this->middleware('can:delete roles')->only('destroy');
    }

    public function index(Request $request)
    {
        $roles = Role::with('permissions') // Eager load permissions for the list
                     ->orderBy('name')
                     ->paginate(15);
        return RoleResource::collection($roles);
    }

    // For dropdowns or simpler lists (if needed, though index might suffice)
    public function indexList()
    {
        return RoleResource::collection(Role::orderBy('name')->get(['id', 'name']));
    }


    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->where(function ($query) {
                    return $query->where('guard_name', 'sanctum'); // Or your default guard
                })
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'sometimes|string|exists:permissions,name,guard_name,sanctum', // Validate permission names exist for sanctum guard
        ]);

        DB::beginTransaction();
        try {
            $role = Role::create([
                'name' => $validatedData['name'],
                'guard_name' => 'sanctum' // Or your default API guard
            ]);

            if (!empty($validatedData['permissions']) && Auth::user()->can('assign permissions to role')) {
                $permissions = Permission::whereIn('name', $validatedData['permissions'])
                                         ->where('guard_name', 'sanctum')
                                         ->get();
                $role->syncPermissions($permissions);
            }
            DB::commit();
            return new RoleResource($role->load('permissions'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to create role.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Role $role)
    {
        // Ensure the role uses the 'sanctum' guard or your API guard
        if ($role->guard_name !== 'web') {
            // Or handle this more gracefully, perhaps filter in route model binding
            return response()->json(['message' => 'Role not found for this guard.'], 404);
        }
        return new RoleResource($role->load('permissions'));
    }

    public function update(Request $request, Role $role)
    {
        // Ensure the role uses the 'sanctum' guard
        if ($role->guard_name !== 'web') {
            return response()->json(['message' => 'Role not found for this guard.'], 404);
        }

        $validatedData = $request->validate([
            'name' => [
                'sometimes', 'required', 'string', 'max:255',
                Rule::unique('roles', 'name')->ignore($role->id)->where(function ($query) {
                    return $query->where('guard_name', 'web');
                })
            ],
            'permissions' => 'nullable|array',
            'permissions.*' => 'sometimes|string|exists:permissions,name,guard_name,web',
        ]);
         
        DB::beginTransaction();
        try {
             if ($request->has('name')) {
                 $role->name = $validatedData['name'];
                 $role->save();
             }

            if ($request->has('permissions') && Auth::user()->can('assign permissions to role')) {
                $permissions = Permission::whereIn('name', $validatedData['permissions'] ?? [])
                                         ->where('guard_name', 'web')
                                         ->get();
                $role->syncPermissions($permissions);
            } elseif ($request->has('permissions') && empty($validatedData['permissions']) && Auth::user()->can('assign permissions to role')) {
                // If an empty permissions array is sent, revoke all permissions
                $role->syncPermissions([]);
            }
            DB::commit();
            return new RoleResource($role->load('permissions'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update role.', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Role $role)
    {
        // Ensure the role uses the 'web' guard
        if ($role->guard_name !== 'web') {
            return response()->json(['message' => 'Role not found for this guard.'], 404);
        }
        // Add checks: e.g., cannot delete 'Super Admin' role or roles with assigned users.
        if (in_array($role->name, ['Super Admin'])) { // Add other critical roles
            return response()->json(['message' => 'لا يمكن حذف هذا الدور الأساسي.'], 403);
        }
        if ($role->users()->count() > 0) {
             return response()->json(['message' => 'لا يمكن حذف هذا الدور لأنه مخصص لمستخدمين. قم بإزالة المستخدمين أولاً.'], 403);
        }

        $role->delete();
        return response()->json(null, 204);
    }

    // Endpoint to get all permissions for dropdowns/checkboxes in role form
    public function getPermissionsList()
    {
        if(!Auth::user()->can('assign permissions to role') && !Auth::user()->can('list roles')) { // Or a specific 'list permissions' permission
             return response()->json(['message' => 'غير مصرح لك.'], 403);
        }
        // Only return permissions for the 'web' guard
        return PermissionResource::collection(Permission::where('guard_name', 'web')->orderBy('name')->get());
    }
}