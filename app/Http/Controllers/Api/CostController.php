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
    // ... index, show, update, destroy for managing costs ...
}
