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
                                        ->with(['subServiceCost', 'serviceCostDefinition'])
                                        ->get();
        return RequestedServiceCostResource::collection($costBreakdown);
    }

    // Direct CRUD for RequestedServiceCost is likely rare.
    // These records are typically generated programmatically when a RequestedService is
    // processed or its price/cost is calculated based on ServiceCost definitions.
    // If manual adjustments are needed, you could implement store/update/destroy here.

    // Example:
    // public function store(Request $request) {
    //     // $this->authorize('create', RequestedServiceCost::class);
    //     $validated = $request->validate([
    //         'requested_service_id' => 'required|exists:requested_services,id',
    //         'sub_service_cost_id' => 'required|exists:sub_service_costs,id',
    //         'service_cost_id' => 'required|exists:service_cost,id',
    //         'amount' => 'required|numeric|min:0',
    //     ]);
    //     $rsc = RequestedServiceCost::create($validated);
    //     return new RequestedServiceCostResource($rsc);
    // }
}