<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequestedServiceCost;
use App\Models\RequestedService; // Parent model
use Illuminate\Http\Request;
use App\Http\Resources\RequestedServiceCostResource; // Create this resource

class RequestedServiceCostController extends Controller
{
    public function __construct()
    {
        // Permissions for viewing cost breakdowns or rare manual adjustments
    }

    // List cost breakdown for a specific requested service
    public function indexForRequestedService(RequestedService $requestedService)
    {
        // $this->authorize('view', $requestedService); // Or general 'view service_cost_breakdown'
        $costBreakdown = $requestedService->costBreakdown()
                                        ->with(['subServiceCost', 'serviceCost.subServiceCost'])
                                        ->get();
        return RequestedServiceCostResource::collection($costBreakdown);
    }

    // Direct CRUD for RequestedServiceCost is likely rare.
    // These records are typically generated programmatically when a RequestedService is
    // processed or its price/cost is calculated based on ServiceCost definitions.
    // If manual adjustments are needed, you could implement store/update/destroy here.

    public function storeSingle(Request $request)
    {
        $validated = $request->validate([
            'requested_service_id' => 'required|exists:requested_services,id',
            'sub_service_cost_id' => 'required|exists:sub_service_costs,id',
            'service_cost_id' => 'required|exists:service_cost,id',
            'amount' => 'required|numeric|min:0',
        ]);
        $rsc = RequestedServiceCost::create($validated);
        return new RequestedServiceCostResource($rsc->load(['subServiceCost', 'serviceCost.subServiceCost']));
    }

    public function storeOrUpdateBatch(Request $request, RequestedService $requested_service)
    {
        $data = $request->all();
        $costs = isset($data[0]) ? $data : [$data];
        $validatedList = [];
        foreach ($costs as $item) {
            $validatedList[] = validator($item, [
                'sub_service_cost_id' => 'required|exists:sub_service_costs,id',
                'service_cost_id' => 'required|exists:service_cost,id',
                'amount' => 'required|numeric|min:0',
            ])->validate();
        }
        $created = [];
        foreach ($validatedList as $v) {
            $created[] = RequestedServiceCost::create([
                'requested_service_id' => $requested_service->id,
                'sub_service_cost_id' => $v['sub_service_cost_id'],
                'service_cost_id' => $v['service_cost_id'],
                'amount' => (int) round($v['amount']),
            ]);
        }
        foreach ($created as $model) {
            $model->load(['subServiceCost', 'serviceCost.subServiceCost']);
        }
        return RequestedServiceCostResource::collection($created);
    }

    public function updateSingle(Request $request, RequestedServiceCost $requested_service_cost)
    {
        $validated = $request->validate([
            'sub_service_cost_id' => 'sometimes|exists:sub_service_costs,id',
            'service_cost_id' => 'sometimes|exists:service_cost,id',
            'amount' => 'sometimes|numeric|min:0',
        ]);
        if (isset($validated['amount'])) {
            $validated['amount'] = (int) round($validated['amount']);
        }
        $requested_service_cost->update($validated);
        return new RequestedServiceCostResource($requested_service_cost->load(['subServiceCost', 'serviceCost.subServiceCost']));
    }

    public function destroySingle(RequestedServiceCost $requested_service_cost)
    {
        $requested_service_cost->delete();
        return response()->json(null, 204);
    }
}