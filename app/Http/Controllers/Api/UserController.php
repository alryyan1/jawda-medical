<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoleResource;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Resources\UserResource;
use App\Models\RequestedServiceDeposit;
use Illuminate\Support\Facades\Auth;
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
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            // 'email' => 'required|string|email|max:255|unique:users,email', // If using email
            'password' => ['required', 'confirmed', Password::defaults()],
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name', // Validate that roles exist by name
        ]);

        $user = User::create([
            'name' => $validatedData['name'],
            'username' => $validatedData['username'],
            // 'email' => $validatedData['email'],
            'password' => Hash::make($validatedData['password']),
            'doctor_id' => $validatedData['doctor_id'] ?? null,
            'is_nurse' => $validatedData['is_nurse'] ?? false,
            'user_money_collector_type' => $validatedData['user_money_collector_type'] ?? 'all',
        ]);

        if (!empty($validatedData['roles']) && $request->user()->can('assign roles')) {
            $user->syncRoles($validatedData['roles']);
        }

        return new UserResource($user->load('roles'));
    }

    public function show(User $user)
    {
        return new UserResource($user->load('roles', 'permissions'));
    }

    public function update(Request $request, User $user)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'username' => ['sometimes','required','string','max:255', Rule::unique('users')->ignore($user->id)],
            // 'email' => ['sometimes','required','string','email','max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::defaults()], // Password is optional on update
            'doctor_id' => 'nullable|exists:doctors,id',
            'is_nurse' => 'sometimes|required|boolean',
            'user_money_collector_type' => ['sometimes','required', Rule::in(['lab','company','clinic','all'])],
            'roles' => 'nullable|array',
            'roles.*' => 'exists:roles,name',
        ]);

        $updateData = $request->except(['password', 'password_confirmation', 'roles']);
        if (!empty($validatedData['password'])) {
            $updateData['password'] = Hash::make($validatedData['password']);
        }

        $user->update($updateData);

        if ($request->has('roles') && $request->user()->can('assign roles')) {
            $user->syncRoles($validatedData['roles'] ?? []);
        }

        return new UserResource($user->load('roles'));
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
         if(!Auth::user()->can('assign roles') && !Auth::user()->can('list roles')) {
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

        // Summing payments handled by this user in this shift
        $depositsQuery = RequestedServiceDeposit::where('user_id', $user->id)
                                                ->where('shift_id', $shiftId);

        $totalCash = (clone $depositsQuery)->where('is_bank', false)->sum('amount');
        $totalBank = (clone $depositsQuery)->where('is_bank', true)->sum('amount');
        $totalIncome = $totalCash + $totalBank;
        
        // You might also want to include other income sources or expenses handled by the user
        // For example, if users can record direct cash income/expenses not tied to services.
        // This would require querying other tables. For now, focusing on service deposits.

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'shift_id' => (int) $shiftId,
                'total_income' => (float) $totalIncome,
                'total_cash' => (float) $totalCash,
                'total_bank' => (float) $totalBank,
                // Add more details if needed, like number of transactions
            ]
        ]);
    }
}