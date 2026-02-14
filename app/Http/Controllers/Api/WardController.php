<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ward;
use Illuminate\Http\Request;
use App\Http\Resources\WardResource;
use Illuminate\Validation\Rule;

class WardController extends Controller
{
    /**
     * Display a listing of the wards.
     */
    public function index(Request $request)
    {
        $query = Ward::withCount([
            'rooms',
            'beds',
            'admissions as current_admissions_count' => function ($q) {
                $q->where('status', 'admitted');
            },
        ]);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', (bool) $request->status);
        }

        $wards = $query->orderBy('id', 'desc')->paginate($request->get('per_page', 15));

        return WardResource::collection($wards);
    }

    /**
     * Store a newly created ward in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:wards,name',
            'description' => 'nullable|string',
            'status' => 'required|boolean',
        ]);

        $ward = Ward::create($validatedData);

        return new WardResource($ward);
    }

    /**
     * Display the specified ward.
     */
    public function show(Ward $ward)
    {
        return new WardResource($ward->load('rooms.beds'));
    }

    /**
     * Update the specified ward in storage.
     */
    public function update(Request $request, Ward $ward)
    {
        $validatedData = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('wards')->ignore($ward->id)],
            'description' => 'nullable|string',
            'status' => 'sometimes|required|boolean',
        ]);

        $ward->update($validatedData);

        return new WardResource($ward);
    }

    /**
     * Remove the specified ward from storage.
     */
    public function destroy(Ward $ward)
    {
        // Check if ward has rooms
        if ($ward->rooms()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف القسم لارتباطه بغرف.'], 403);
        }

        $ward->delete();

        return response()->json(null, 204);
    }

    /**
     * Get rooms for a specific ward.
     */
    public function getRooms(Ward $ward)
    {
        $rooms = $ward->rooms()
            ->with('beds')
            ->withCount(['admissions as current_admissions_count' => function ($q) {
                $q->where('status', 'admitted');
            }])
            ->get();
        return \App\Http\Resources\RoomResource::collection($rooms);
    }

    /**
     * Simple list for dropdowns.
     */
    public function indexList()
    {
        $wards = Ward::where('status', true)->orderBy('name')->get(['id', 'name']);
        return WardResource::collection($wards);
    }
}
