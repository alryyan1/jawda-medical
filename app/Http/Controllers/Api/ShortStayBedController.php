<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShortStayBed;
use Illuminate\Http\Request;
use App\Http\Resources\ShortStayBedResource;
use Illuminate\Validation\Rule;

class ShortStayBedController extends Controller
{
    /**
     * Display a listing of short stay beds.
     */
    public function index(Request $request)
    {
        $query = ShortStayBed::query();

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('bed_number', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('notes', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $beds = $query->orderBy('bed_number')->paginate($request->get('per_page', 15));

        return ShortStayBedResource::collection($beds);
    }

    /**
     * Store a newly created short stay bed in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'bed_number' => 'required|string|max:255|unique:short_stay_beds,bed_number',
            'price_12h' => 'required|numeric|min:0',
            'price_24h' => 'required|numeric|min:0',
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'notes' => 'nullable|string',
        ]);

        $bed = ShortStayBed::create($validatedData);

        return new ShortStayBedResource($bed);
    }

    /**
     * Display the specified short stay bed.
     */
    public function show(ShortStayBed $shortStayBed)
    {
        return new ShortStayBedResource($shortStayBed);
    }

    /**
     * Update the specified short stay bed in storage.
     */
    public function update(Request $request, ShortStayBed $shortStayBed)
    {
        $validatedData = $request->validate([
            'bed_number' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('short_stay_beds')->ignore($shortStayBed->id)],
            'price_12h' => 'sometimes|required|numeric|min:0',
            'price_24h' => 'sometimes|required|numeric|min:0',
            'status' => ['sometimes', 'required', Rule::in(['active', 'inactive'])],
            'notes' => 'nullable|string',
        ]);

        $shortStayBed->update($validatedData);

        return new ShortStayBedResource($shortStayBed);
    }

    /**
     * Remove the specified short stay bed from storage.
     */
    public function destroy(ShortStayBed $shortStayBed)
    {
        // Check if bed has admissions
        if ($shortStayBed->admissions()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف السرير لارتباطه بتنويمات.'], 403);
        }

        $shortStayBed->delete();

        return response()->json(null, 204);
    }
}
