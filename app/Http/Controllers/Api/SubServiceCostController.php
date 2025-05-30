<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubServiceCost;
use Illuminate\Http\Request;
use App\Http\Resources\SubServiceCostResource; // Create this resource
use Illuminate\Validation\Rule;

class SubServiceCostController extends Controller
{
    public function __construct()
    {
        // Add permissions: e.g., 'manage service_cost_types'
    }

    public function index(Request $request)
    {
        // $this->authorize('list', SubServiceCost::class);
        $query = SubServiceCost::query();
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }
        $subServiceCosts = $query->orderBy('name')->paginate($request->get('per_page', 15));
        return SubServiceCostResource::collection($subServiceCosts);
    }

    public function indexList() // For dropdowns
    {
        // $this->authorize('list', SubServiceCost::class);
        return SubServiceCostResource::collection(SubServiceCost::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        // $this->authorize('create', SubServiceCost::class);
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:sub_service_costs,name',
        ]);
        $subServiceCost = SubServiceCost::create($validated);
        return new SubServiceCostResource($subServiceCost);
    }

    public function show(SubServiceCost $subServiceCost)
    {
        // $this->authorize('view', $subServiceCost);
        return new SubServiceCostResource($subServiceCost);
    }

    public function update(Request $request, SubServiceCost $subServiceCost)
    {
        // $this->authorize('update', $subServiceCost);
        $validated = $request->validate([
            'name' => ['sometimes','required','string','max:255', Rule::unique('sub_service_costs')->ignore($subServiceCost->id)],
        ]);
        $subServiceCost->update($validated);
        return new SubServiceCostResource($subServiceCost);
    }

    public function destroy(SubServiceCost $subServiceCost)
    {
        // $this->authorize('delete', $subServiceCost);
        // Add checks: e.g., cannot delete if used in ServiceCost or RequestedServiceCost
        if ($subServiceCost->serviceCosts()->exists() || $subServiceCost->requestedServiceCostEntries()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف هذا النوع من التكلفة لارتباطه ببيانات أخرى.'], 403);
        }
        $subServiceCost->delete();
        return response()->json(null, 204);
    }
}