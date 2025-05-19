<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SpecialistResource;
use App\Models\Specialist;
use Illuminate\Http\Request;

class SpecialistController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function indexList()
    {
        // Return a simple list of specialists, suitable for dropdowns
        return SpecialistResource::collection(Specialist::orderBy('name')->get());
        // Or, if you only need id and name and don't want a resource:
        // return Specialist::orderBy('name')->get(['id', 'name']);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Add other fields and validation rules as needed
        ]);

        $specialist = Specialist::create($validated);

        return new SpecialistResource($specialist);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
