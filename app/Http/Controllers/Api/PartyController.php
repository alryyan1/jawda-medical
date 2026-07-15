<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Party;
use Illuminate\Http\Request;
use App\Http\Resources\PartyResource;

class PartyController extends Controller
{
    public function index(Request $request)
    {
        $query = Party::withCount('services');

        if ($request->has('search') && !empty($request->search)) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $parties = $query->orderBy('name')->paginate($request->get('per_page', 15));

        return PartyResource::collection($parties);
    }

    public function indexList()
    {
        $parties = Party::orderBy('name')->get();
        return PartyResource::collection($parties);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:parties,name',
        ]);

        $party = Party::create($validatedData);

        return new PartyResource($party);
    }

    public function show(Party $party)
    {
        return new PartyResource($party->loadCount('services'));
    }

    public function update(Request $request, Party $party)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:parties,name,' . $party->id,
        ]);

        $party->update($validatedData);

        return new PartyResource($party);
    }

    public function destroy(Party $party)
    {
        $party->delete();
        return response()->json(null, 204);
    }
}
