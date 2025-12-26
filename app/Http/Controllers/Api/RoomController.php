<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Ward;
use Illuminate\Http\Request;
use App\Http\Resources\RoomResource;
use Illuminate\Validation\Rule;

class RoomController extends Controller
{
    /**
     * Display a listing of the rooms.
     */
    public function index(Request $request)
    {
        $query = Room::with(['ward', 'beds'])->withCount('beds');

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('room_number', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('room_type', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Ward filter
        if ($request->has('ward_id') && $request->ward_id) {
            $query->where('ward_id', $request->ward_id);
        }

        // Status filter
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', (bool) $request->status);
        }

        $rooms = $query->orderBy('ward_id')->orderBy('room_number')->paginate($request->get('per_page', 15));

        return RoomResource::collection($rooms);
    }

    /**
     * Store a newly created room in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'ward_id' => 'required|exists:wards,id',
            'room_number' => 'required|string|max:255',
            'room_type' => ['nullable', Rule::in(['normal', 'vip'])],
            'capacity' => 'required|integer|min:1',
            'status' => 'required|boolean',
            'price_per_day' => 'nullable|numeric|min:0',
        ]);

        $room = Room::create($validatedData);

        return new RoomResource($room->load('ward'));
    }

    /**
     * Display the specified room.
     */
    public function show(Room $room)
    {
        return new RoomResource($room->load(['ward', 'beds']));
    }

    /**
     * Update the specified room in storage.
     */
    public function update(Request $request, Room $room)
    {
        $validatedData = $request->validate([
            'ward_id' => 'sometimes|required|exists:wards,id',
            'room_number' => ['sometimes', 'required', 'string', 'max:255'],
            'room_type' => ['nullable', Rule::in(['normal', 'vip'])],
            'capacity' => 'sometimes|required|integer|min:1',
            'status' => 'sometimes|required|boolean',
            'price_per_day' => 'nullable|numeric|min:0',
        ]);

        $room->update($validatedData);

        return new RoomResource($room->load('ward'));
    }

    /**
     * Remove the specified room from storage.
     */
    public function destroy(Room $room)
    {
        // Check if room has beds
        if ($room->beds()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف الغرفة لارتباطها بأسرّة.'], 403);
        }

        $room->delete();

        return response()->json(null, 204);
    }

    /**
     * Get beds for a specific room.
     */
    public function getBeds(Room $room)
    {
        $beds = $room->beds()->get();
        return \App\Http\Resources\BedResource::collection($beds);
    }
}
