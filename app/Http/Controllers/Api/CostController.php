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
            'shift_id' => [
                'required',
                'integer',
                'exists:shifts,id',
                // Ensure the shift is currently open
                Rule::exists('shifts', 'id')->where(function ($query) {
                    $query->where('is_closed', false);
                }),
            ],
            'cost_category_id' => 'nullable|integer|exists:cost_categories,id',
            'doctor_shift_id' => 'nullable|integer|exists:doctor_shifts,id', // Optional
            'description' => 'required|string|max:255',
            'comment' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0.01',
            'is_bank_payment' => 'required|boolean', // To determine if amount goes to 'amount' or 'amount_bankak'
        ]);

        $costData = [
            'shift_id' => $validated['shift_id'],
            'user_cost' => Auth::id(),
            'cost_category_id' => $validated['cost_category_id'] ?? null,
            'doctor_shift_id' => $validated['doctor_shift_id'] ?? null,
            'description' => $validated['description'],
            'comment' => $validated['comment'] ?? null,
        ];

        if ($validated['is_bank_payment']) {
            $costData['amount_bankak'] = $validated['amount'];
            $costData['amount'] = 0; // Or your DB default if different
        } else {
            $costData['amount'] = $validated['amount'];
            $costData['amount_bankak'] = 0; // Or your DB default
        }

        $cost = Cost::create($costData);
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

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }
        if ($request->filled('cost_category_id')) {
            $query->where('cost_category_id', $request->cost_category_id);
        }
        if ($request->filled('user_cost_id')) {
            $query->where('user_cost', $request->user_cost_id);
        }
        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }
        if ($request->filled('payment_method')) {
            if ($request->payment_method === 'cash') {
                $query->where('amount', '>', 0)->where('amount_bankak', '=', 0); // Or however you distinguish
            } elseif ($request->payment_method === 'bank') {
                $query->where('amount_bankak', '>', 0);
            }
        }
        if ($request->filled('search_description')) {
            $query->where('description', 'LIKE', '%' . $request->search_description . '%');
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        if ($sortBy === 'amount') { // Special handling for total amount
             $query->orderByRaw('(amount + amount_bankak) ' . $sortDirection);
        } else {
             $query->orderBy($sortBy, $sortDirection);
        }


        $perPage = $request->input('per_page', 15);
        $costs = $query->paginate($perPage);

        // Optionally add a summary of totals for the current filtered view
        $summaryQuery = clone $query;
        $summaryQuery->getQuery()->orders = []; // Remove any ORDER BY clauses
        $totals = $summaryQuery->selectRaw('SUM(amount) as total_cash_paid, SUM(amount_bankak) as total_bank_paid, SUM(amount + amount_bankak) as grand_total_paid')->first();
        
        return CostResource::collection($costs)->additional(['meta' => ['summary' => $totals]]);
    }
    // ... (show, update, destroy if full CRUD management page for costs exists) ...
      
    // ... index, show, update, destroy for managing costs ...

}
