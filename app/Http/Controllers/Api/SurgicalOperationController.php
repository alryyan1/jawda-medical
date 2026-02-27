<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SurgicalOperation;
use Illuminate\Http\Request;

class SurgicalOperationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return SurgicalOperation::all();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $operation = SurgicalOperation::create($validated);
        return response()->json($operation, 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SurgicalOperation $surgicalOperation)
    {
        return $surgicalOperation;
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SurgicalOperation $surgicalOperation)
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $surgicalOperation->update($validated);
        return response()->json($surgicalOperation);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SurgicalOperation $surgicalOperation)
    {
        $surgicalOperation->delete();
        return response()->json(null, 204);
    }
}
