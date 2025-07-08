<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Http\Resources\ShiftDefinitionResource;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Models\LabRequest;
use App\Models\RequestedServiceDeposit;
use App\Models\Shift;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct()
    {
        // Apply middleware for permissions. Adjust permission names as needed.
        // $this->middleware('can:list users')->only('index');
        // $this->middleware('can:view users')->only('show');
        // $this->middleware('can:create users')->only('store');
        // $this->middleware('can:edit users')->only('update');
        // $this->middleware('can:delete users')->only('destroy');
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name,guard_name,web', // Ensure guard_name is web
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'is_nurse' => 'sometimes|boolean',
            'is_supervisor' => 'sometimes|boolean', // ADDED
            'is_active' => 'sometimes|boolean',     // ADDED
            'user_money_collector_type' => ['sometimes', 'required', Rule::in(['lab', 'company', 'clinic', 'all'])],
        ]);

        $createData = collect($validatedData)->except(['password_confirmation', 'roles'])->toArray();
        $createData['password'] = Hash::make($validatedData['password']);
        
        // Set defaults if not provided
        $createData['is_nurse'] = $validatedData['is_nurse'] ?? false;
        $createData['is_supervisor'] = $validatedData['is_supervisor'] ?? false;
        $createData['is_active'] = $validatedData['is_active'] ?? true; // Default to active
        $createData['user_money_collector_type'] = $validatedData['user_money_collector_type'] ?? 'all';


        $user = User::create($createData);

        if (!empty($validatedData['roles']) && Auth::user()->can('assign roles')) { // Assuming 'assign roles' permission
            $user->syncRoles($validatedData['roles']);
        } else {
            // Assign a default role if no roles are provided and you have a default role strategy
            // For example: $user->assignRole('Default User');
        }

        return new UserResource($user->load('roles', 'doctor'));
    }

    public function update(Request $request, User $user)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            // Password validation is removed here; use updatePassword for that.
            // If you want to allow password change here, add it back with 'nullable'
            // 'password' => ['nullable', 'confirmed', Password::defaults()],
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'is_nurse' => 'sometimes|boolean',
            'is_supervisor' => 'sometimes|boolean', // ADDED
            'is_active' => 'sometimes|boolean',     // ADDED
            'user_money_collector_type' => ['sometimes', 'required', Rule::in(['lab', 'company', 'clinic', 'all'])],
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name,guard_name,web', // Ensure guard_name is web
        ]);

        // Prepare data for update, excluding password fields (handled by dedicated endpoint)
        $updateData = collect($validatedData)->except(['roles'])->toArray();

        // Handle boolean flags explicitly if they are not present in the request but field exists
        if ($request->has('is_nurse')) $updateData['is_nurse'] = $request->boolean('is_nurse');
        if ($request->has('is_supervisor')) $updateData['is_supervisor'] = $request->boolean('is_supervisor');
        if ($request->has('is_active')) $updateData['is_active'] = $request->boolean('is_active');
        
        $user->update($updateData);

        if ($request->has('roles') && Auth::user()->can('assign roles')) { // Assuming 'assign roles' permission
            $user->syncRoles($validatedData['roles'] ?? []);
        }

        return new UserResource($user->load('roles', 'doctor'));
    }


    public function show(User $user)
    {
        return new UserResource($user->load('roles', 'permissions'));
    }
    public function index(Request $request)
    {
        // Validate per_page if you want to restrict its range
        $request->validate([
            'per_page' => 'nullable|integer|min:5|max:200', // Example range
            'search' => 'nullable|string|max:255', // Optional search term
            'role' => 'nullable|string|max:255', // Optional filter by role name
        ]);

        $perPage = $request->input('per_page', 15); // Default to 15 items per page

        $query = User::with('roles'); // Eager load roles

        // Optional: Search functionality
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('username', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('email', 'LIKE', "%{$searchTerm}%"); // If you have email
            });
        }

        // Optional: Filter by role
        if ($request->filled('role')) {
            $roleName = $request->role;
            $query->whereHas('roles', function ($q) use ($roleName) {
                $q->where('name', $roleName);
            });
        }

        $users = $query->orderBy('id', 'desc')->paginate($perPage);

        return UserResource::collection($users);
    }
  

    public function destroy(User $user)
    {
        // Add checks: e.g., cannot delete self, cannot delete last super admin
        if (Auth::id() === $user->id) {
            return response()->json(['message' => 'لا يمكنك حذف حسابك الخاص.'], 403);
        }
        // Add more sophisticated checks if needed
        $user->delete();
        return response()->json(null, 204);
    }

    // Endpoint to get all roles for dropdowns/checkboxes in user form
    public function getRolesList()
    {
        if (!Auth::user()->can('assign roles') && !Auth::user()->can('list roles')) {
            return response()->json(['message' => 'غير مصرح لك.'], 403);
        }
        return RoleResource::collection(Role::orderBy('name')->get());
    }

    /**
     * Update the user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePassword(Request $request)
    {
        $validatedData = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = Auth::user();

        // Check if current password matches
        if (!Hash::check($validatedData['current_password'], $user->password)) {
            return response()->json([
                'message' => 'كلمة المرور الحالية غير صحيحة',
                'errors' => [
                    'current_password' => ['كلمة المرور الحالية غير صحيحة']
                ]
            ], 422);
        }

        // Update password
        $user->update([
            'password' => Hash::make($validatedData['password'])
        ]);

        return response()->json([
            'message' => 'تم تحديث كلمة المرور بنجاح'
        ]);
    }

    public function getCurrentUserShiftIncomeSummary(Request $request)
    {
        $request->validate([
            'shift_id' => 'required|integer|exists:shifts,id',
        ]);

        $user = Auth::user();
        $shiftId = $request->input('shift_id');

        // Ensure the provided shift is actually open, or allow for closed shifts if that's the requirement
        // $shift = Shift::where('id', $shiftId)->open()->first();
        // if (!$shift) {
        //     return response()->json(['message' => 'الوردية المحددة ليست مفتوحة أو غير موجودة.'], 404);
        // }

        $shift = Shift::find($shiftId);
        $totalPaidService =  $shift->totalPaidService($user->id);
        $totalBankService =  $shift->totalPaidServiceBank($user->id);
        $totalLab =  $shift->paidLab($user->id);
        $totalLabBank =  $shift->bankakLab($user->id);
        $totallabCash = $totalLab - $totalLabBank;
        // Costs specific to this user within this shift (if applicable)
        $totalCostForUser = $shift->totalCost($user->id); // Ensure this method exists and is relevant
        $totalCostBankForUser = $shift->totalCostBank($user->id);
        $totalCost = $shift->totalCost($user->id);
        $totalCostBank = $shift->totalCostBank($user->id);
        $totalCashService = $totalPaidService - $totalBankService;
        $totalCostCash = $totalCost - $totalCostBank;
        $netCash = ($totalCashService + $totallabCash )- $totalCostCash;
        $netBank = ($totalBankService + $totalLabBank) - $totalCostBank;

        // You might also want to include other income sources or expenses handled by the user
        // For example, if users can record direct cash income/expenses not tied to services.
        // This would require querying other tables. For now, focusing on service deposits.
        $expenses = [
            'total_cash_expenses' => (float) $totalCostCash,
            'total_bank_expenses' => (float) $totalCostBank,
        ];
        return response()->json([

            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'shift_id' => (int) $shiftId,
                'service_income' => [
                    'total' => (float) $totalPaidService,
                    'bank' => (float) $totalBankService,
                    'cash' => (float) $totalCashService,
                ],
                'total' => (float) $totalPaidService + $totalLab,
                'total_cash' => (float) $totalCashService + $totallabCash,
                'total_bank' => (float) $totalBankService + $totalLabBank,
                'total_cash_expenses' => (float) $totalCostCash,
                'total_bank_expenses' => (float) $totalCostBank,
                'total_cost' => (float) $totalCost,
                'net_cash' => (float) $netCash,
                'net_bank' => (float) $netBank,
                'expenses' => $expenses,
                'lab_income' => [
                    'total' => (float) $totalLab,
                    'bank' => (float) $totalLabBank,
                    'cash' => (float) $totallabCash,
                ],
                // Add more details if needed, like number of transactions
            ]
        ]);
    }
    public function updateAttendanceSettings(Request $request, User $user)
    {
        // if (!Auth::user()->can('edit_user_attendance_settings', $user)) { /* ... */ }

        $validated = $request->validate([
            'is_supervisor' => 'sometimes|boolean',
            'default_shift_ids' => 'nullable|array', // User can be assigned to multiple default shifts (e.g., if working pattern varies)
            'default_shift_ids.*' => 'integer|exists:shifts_definitions,id',
        ]);

        DB::beginTransaction();
        try {
            if ($request->has('is_supervisor')) {
                $user->update(['is_supervisor' => $validated['is_supervisor']]);
            }

            if ($request->has('default_shift_ids')) {
                $user->defaultShifts()->sync($validated['default_shift_ids'] ?? []);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to update user attendance settings.', 'error' => $e->getMessage()], 500);
        }

        return new UserResource($user->load(['roles', 'defaultShifts']));
    }

    public function getUserDefaultShifts(User $user)
    {
        // if (!Auth::user()->can('view_user_attendance_settings', $user)) { /* ... */ }
        return ShiftDefinitionResource::collection($user->defaultShifts);
    }
    /**
     * Get a summary of lab payments processed by the current user in a specific shift.
     */
    public function getCurrentUserLabIncomeSummary(Request $request)
    {
        $request->validate([
            'shift_id' => 'required|integer|exists:shifts,id',
        ]);

        $user = Auth::user();
        $shiftId = $request->input('shift_id');

        // Sum up payments from LabRequest records where this user was the depositor in this shift
        $query = LabRequest::where('user_deposited', $user->id)
                           ->where('payment_shift_id', $shiftId);

        $totalIncome = (float) (clone $query)->sum('amount_paid');
        $totalBank = (float) (clone $query)->where('is_bankak', true)->sum('amount_paid');
        $totalCash = $totalIncome - $totalBank;

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'shift_id' => (int) $shiftId,
                'total_lab_income' => $totalIncome,
                'total_cash' => $totalCash,
                'total_bank' => $totalBank,
            ]
        ]);
    }
}
