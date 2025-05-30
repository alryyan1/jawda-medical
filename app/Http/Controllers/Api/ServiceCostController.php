<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceCost;
use App\Models\Service; // Parent model
use Illuminate\Http\Request;
use App\Http\Resources\ServiceCostResource; // Create this resource
use Illuminate\Validation\Rule;

class ServiceCostController extends Controller
{
    public function __construct()
    {
        // Add permissions: e.g., 'manage service_definitions' or 'manage service_costs'
    }

    // List costs for a specific service
    public function index(Request $request, Service $service)
    {
        // $this->authorize('view', $service); // Or specific permission
        $serviceCosts = $service->serviceCosts()->with('subServiceCost')->paginate($request->get('per_page', 15));
        return ServiceCostResource::collection($serviceCosts);
    }

    // Store a new cost definition for a service
    public function store(Request $request, Service $service)
    {
        // $this->authorize('update', $service); // Need to be able to update the parent service definition
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'percentage' => 'required_without:fixed|nullable|numeric|min:0|max:100',
            'fixed' => 'required_without:percentage|nullable|numeric|min:0',
            'cost_type' => ['required', Rule::in(['total', 'after cost'])],
            'sub_service_cost_id' => 'required|exists:sub_service_costs,id',
        ]);

        if (empty($validated['percentage']) && empty($validated['fixed'])) {
            return response()->json(['message' => 'يجب توفير قيمة نسبة مئوية أو مبلغ ثابت للتكلفة.'], 422);
        }

        $serviceCost = $service->serviceCosts()->create($validated);
        return new ServiceCostResource($serviceCost->load('subServiceCost'));
    }

    public function show(ServiceCost $serviceCost) // Shallow binding
    {
        // $this->authorize('view', $serviceCost->service); // Authorize based on parent service
        return new ServiceCostResource($serviceCost->load(['service', 'subServiceCost']));
    }

    public function update(Request $request, ServiceCost $serviceCost) // Shallow binding
    {
        // $this->authorize('update', $serviceCost->service);
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'percentage' => 'sometimes|nullable|numeric|min:0|max:100',
            'fixed' => 'sometimes|nullable|numeric|min:0',
            'cost_type' => ['sometimes','required', Rule::in(['total', 'after cost'])],
            'sub_service_cost_id' => 'sometimes|required|exists:sub_service_costs,id',
        ]);

        if (array_key_exists('percentage', $validated) && array_key_exists('fixed', $validated) &&
            empty($validated['percentage']) && empty($validated['fixed'])) {
            return response()->json(['message' => 'يجب توفير قيمة نسبة مئوية أو مبلغ ثابت للتكلفة.'], 422);
        }

        $serviceCost->update($validated);
        return new ServiceCostResource($serviceCost->load('subServiceCost'));
    }

    public function destroy(ServiceCost $serviceCost) // Shallow binding
    {
        // $this->authorize('update', $serviceCost->service);
        // Add checks: e.g., cannot delete if used in RequestedServiceCost
        if ($serviceCost->requestedServiceCostEntries()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف تعريف التكلفة هذا لارتباطه بطلبات خدمات فعلية.'], 403);
        }
        $serviceCost->delete();
        return response()->json(null, 204);
    }
}