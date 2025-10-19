<?php
// app/Http/Controllers/Api/CostController.php
// php artisan make:controller Api/CostController --api --model=Cost
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cost;
use App\Models\CostCategory; // For dropdown
use App\Models\Shift;      // To ensure shift is open
use Illuminate\Http\Request;
use App\Http\Resources\CostResource; // Create this
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CostController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:record costs')->only('store');
        // $this->middleware('can:list costs')->only('index');
    }

    // For dropdown in the dialog
    public function getCostCategories()
    {
        // $this->authorize('view cost_categories');
        return \App\Http\Resources\CostCategoryResource::collection(CostCategory::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        // $this->authorize('create', Cost::class);
        $validated = $request->validate([
            'shift_id' => ['required', 'integer', 'exists:shifts,id', Rule::exists('shifts', 'id')->where('is_closed', false)],
            'cost_category_id' => 'nullable|integer|exists:cost_categories,id',
            'doctor_shift_id' => 'nullable|integer|exists:doctor_shifts,id',
            'description' => 'required|string|max:255',
            'comment' => 'nullable|string|max:255',
            'amount_cash_input' => 'required_without:amount_bank|nullable|numeric|min:0', // Amount from cash
            'amount_bank_input' => 'required_without:amount_cash|nullable|numeric|min:0', // Amount from bank
            'doctor_shift_id_for_sub_cost' => 'nullable|integer|exists:doctor_shifts,id',
        ]);
    
        // Ensure at least one amount is provided and not both zero if one is required
        if (($validated['amount_cash_input'] ?? 0) <= 0 && ($validated['amount_bank_input'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['amount_cash_input' => 'At least one amount (cash or bank) must be greater than zero.']);
        }
    
        $cost = Cost::create([
            'shift_id' => $validated['shift_id'],
            'user_cost' => Auth::id(),
            'cost_category_id' => $validated['cost_category_id'] ?? null,
            'doctor_shift_id' => $validated['doctor_shift_id'] ?? null,
            'description' => $validated['description'],
            'comment' => $validated['comment'] ?? null,
            'amount' => $validated['amount_cash_input'] ?? 0,       // Store cash portion
            'amount_bankak' => $validated['amount_bank_input'] ?? 0, // Store bank portion
        ]);
        return new CostResource($cost->load(['costCategory', 'userCost:id,name']));
    }
    public function index(Request $request)
    {
        // Permission check: e.g., can('list costs') or can('view cost_report')
        // if (!Auth::user()->can('list costs')) { ... }

        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'cost_category_id' => 'nullable|integer|exists:cost_categories,id',
            'user_cost_id' => 'nullable|integer|exists:users,id', // User who recorded
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'payment_method' => 'nullable|string|in:cash,bank', // 'cash' or 'bank'
            'search_description' => 'nullable|string|max:255',
            'sort_by' => 'nullable|string|in:created_at,amount,description',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = Cost::with(['costCategory:id,name', 'userCost:id,name', 'shift:id', 'doctorShift.doctor:id,name']); // Eager load

        // Apply all filters to the main query
        if ($request->filled('date_from')) { $query->whereDate('created_at', '>=', Carbon::parse($request->date_from)->startOfDay()); }
        if ($request->filled('date_to')) { $query->whereDate('created_at', '<=', Carbon::parse($request->date_to)->endOfDay()); }
        if ($request->filled('cost_category_id')) { $query->where('cost_category_id', $request->cost_category_id); }
        if ($request->filled('user_cost_id')) { $query->where('user_cost', $request->user_cost_id); }
        if ($request->filled('shift_id')) { $query->where('shift_id', $request->shift_id); }
        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            if ($request->payment_method === 'cash') { $query->where('amount', '>', 0)->where('amount_bankak', '=', 0); }
            elseif ($request->payment_method === 'bank') { $query->where('amount_bankak', '>', 0)->where('amount', '=', 0); }
            elseif ($request->payment_method === 'mixed') { $query->where('amount', '>', 0)->where('amount_bankak', '>', 0); }
        }
        if ($request->filled('search_description')) { $query->where('description', 'LIKE', '%' . $request->search_description . '%'); }

        if ($request->input('sort_by') === 'total_cost') {
            $query->orderByRaw('(amount + amount_bankak) ' . ($request->input('sort_direction', 'desc')));
        } else {
            $query->orderBy($request->input('sort_by', 'created_at'), $request->input('sort_direction', 'desc'));
        }

        $perPage = $request->input('per_page', 15);
        $costs = $query->paginate($perPage);

        // Corrected Summary Calculation - Clone the main query for summary
        $summaryQuery = clone $query;
        $summaryQuery->getQuery()->orders = null; // Remove ordering for summary calculation


        $summaryTotals = $summaryQuery->selectRaw('SUM(amount) as total_cash_paid, SUM(amount_bankak) as total_bank_paid, SUM(amount + amount_bankak) as grand_total_paid')
                                 ->first();

        return CostResource::collection($costs)->additional(['meta' => [
            'summary' => [
                'total_cash_paid' => (float)($summaryTotals->total_cash_paid ?? 0),
                'total_bank_paid' => (float)($summaryTotals->total_bank_paid ?? 0),
                'grand_total_paid' => (float)($summaryTotals->grand_total_paid ?? 0),
            ]
        ]]);
    }
    
    public function destroy($id)
    {
        $cost = Cost::findOrFail($id);
        
        // Check if user can delete this cost (optional authorization)
        // $this->authorize('delete', $cost);
        
        $cost->delete();
        
        return response()->json(['message' => 'Cost deleted successfully'], 200);
    }

}
