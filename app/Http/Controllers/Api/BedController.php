<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bed;
use App\Models\Room;
use Illuminate\Http\Request;
use App\Http\Resources\BedResource;

class BedController extends Controller
{
    /**
     * Display a listing of the beds.
     */
    public function index(Request $request)
    {
        $query = Bed::with(['room.ward'])->with('currentAdmission');

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('bed_number', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Room filter
        if ($request->has('room_id') && $request->room_id) {
            $query->where('room_id', $request->room_id);
        }

        // Ward filter (through room)
        if ($request->has('ward_id') && $request->ward_id) {
            $query->whereHas('room', function($q) use ($request) {
                $q->where('ward_id', $request->ward_id);
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Availability filter
        if ($request->has('available') && $request->available === 'true') {
            $query->where('status', 'available')
                  ->whereDoesntHave('currentAdmission');
        }

        $beds = $query->orderBy('room_id')->orderBy('bed_number')->paginate($request->get('per_page', 15));

        return BedResource::collection($beds);
    }

    /**
     * Store a newly created bed in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'bed_number' => 'required|string|max:255',
            'status' => 'required|in:available,occupied,maintenance',
        ]);

        $bed = Bed::create($validatedData);

        return new BedResource($bed->load('room'));
    }

    /**
     * Display the specified bed.
     */
    public function show(Bed $bed)
    {
        return new BedResource($bed->load(['room.ward', 'currentAdmission.patient']));
    }

    /**
     * Update the specified bed in storage.
     */
    public function update(Request $request, Bed $bed)
    {
        $validatedData = $request->validate([
            'room_id' => 'sometimes|required|exists:rooms,id',
            'bed_number' => ['sometimes', 'required', 'string', 'max:255'],
            'status' => 'sometimes|required|in:available,occupied,maintenance',
        ]);

        $bed->update($validatedData);

        return new BedResource($bed->load('room'));
    }

    /**
     * Remove the specified bed from storage.
     */
    public function destroy(Bed $bed)
    {
        // Check if bed has active admission
        if ($bed->currentAdmission()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف السرير لوجود مريض مقيم حالياً.'], 403);
        }

        $bed->delete();

        return response()->json(null, 204);
    }

    /**
     * Get available beds.
     */
    public function getAvailable(Request $request)
    {
        $query = Bed::with(['room.ward'])
                    ->where('status', 'available')
                    ->whereDoesntHave('currentAdmission');

        // Ward filter
        if ($request->has('ward_id') && $request->ward_id) {
            $query->whereHas('room', function($q) use ($request) {
                $q->where('ward_id', $request->ward_id);
            });
        }

        // Room filter
        if ($request->has('room_id') && $request->room_id) {
            $query->where('room_id', $request->room_id);
        }

        $beds = $query->orderBy('room_id')->orderBy('bed_number')->get();

        return BedResource::collection($beds);
    }
}
