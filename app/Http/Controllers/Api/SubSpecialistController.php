<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SubSpecialist;
use App\Models\Specialist;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubSpecialistController extends Controller
{
    /**
     * Get all sub specialists for a specific specialist
     */
    public function index(Request $request, Specialist $specialist)
    {
        $subSpecialists = $specialist->subSpecialists()->orderBy('name')->get();
        return response()->json(['data' => $subSpecialists]);
    }

    /**
     * Store a newly created sub specialist
     */
    public function store(Request $request, Specialist $specialist)
    {
        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sub_specialists')->where('specialists_id', $specialist->id)
            ],
        ]);

        $subSpecialist = $specialist->subSpecialists()->create($validated);
        return response()->json(['data' => $subSpecialist], 201);
    }

    /**
     * Update the specified sub specialist
     */
    public function update(Request $request, Specialist $specialist, SubSpecialist $subSpecialist)
    {
        // Ensure the sub specialist belongs to the specialist
        if ($subSpecialist->specialists_id !== $specialist->id) {
            return response()->json(['message' => 'Sub specialist does not belong to this specialist'], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('sub_specialists')
                    ->where('specialists_id', $specialist->id)
                    ->ignore($subSpecialist->id)
            ],
        ]);

        $subSpecialist->update($validated);
        return response()->json(['data' => $subSpecialist]);
    }

    /**
     * Remove the specified sub specialist
     */
    public function destroy(Specialist $specialist, SubSpecialist $subSpecialist)
    {
        // Ensure the sub specialist belongs to the specialist
        if ($subSpecialist->specialists_id !== $specialist->id) {
            return response()->json(['message' => 'Sub specialist does not belong to this specialist'], 404);
        }

        $subSpecialist->delete();
        return response()->json(null, 204);
    }
}
