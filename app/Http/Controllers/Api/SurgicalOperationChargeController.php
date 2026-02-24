<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SurgicalOperation;
use App\Models\SurgicalOperationCharge;
use Illuminate\Http\Request;

class SurgicalOperationChargeController extends Controller
{
    public function index(SurgicalOperation $surgicalOperation)
    {
        return $surgicalOperation->charges()->get();
    }

    public function store(Request $request, SurgicalOperation $surgicalOperation)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'type' => 'required|in:fixed,percentage',
            'amount' => 'required|numeric|min:0',
            'reference_type' => 'nullable|in:total,charge',
            'reference_charge_id' => 'nullable|exists:surgical_operation_charges,id'
        ]);

        $charge = $surgicalOperation->charges()->create($validated);
        return response()->json($charge, 201);
    }

    public function update(Request $request, SurgicalOperation $surgicalOperation, SurgicalOperationCharge $charge)
    {
        if ($charge->surgical_operation_id !== $surgicalOperation->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'type' => 'sometimes|in:fixed,percentage',
            'amount' => 'sometimes|numeric|min:0',
            'reference_type' => 'nullable|in:total,charge',
            'reference_charge_id' => 'nullable|exists:surgical_operation_charges,id'
        ]);

        $charge->update($validated);
        return response()->json($charge);
    }

    public function destroy(SurgicalOperation $surgicalOperation, SurgicalOperationCharge $charge)
    {
        if ($charge->surgical_operation_id !== $surgicalOperation->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $charge->delete();
        return response()->json(null, 204);
    }
}
