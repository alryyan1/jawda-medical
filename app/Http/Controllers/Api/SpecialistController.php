<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Specialist;
use Illuminate\Http\Request;
use App\Http\Resources\SpecialistResource;
use Illuminate\Validation\Rule;

class SpecialistController extends Controller
{
    public function __construct()
    {
        // Add permissions for each action
        // $this->middleware('can:list specialists')->only(['index', 'indexList']);
        // $this->middleware('can:create specialists')->only('store');
        // $this->middleware('can:edit specialists')->only('update');
        // $this->middleware('can:delete specialists')->only('destroy');
    }
    
    /**
     * Display a paginated listing of the resource.
     * THIS IS THE MISSING METHOD.
     */
    public function index(Request $request)
    {
        // Permission check
        // if (!auth()->user()->can('list specialists')) {
        //     abort(403);
        // }

        $query = Specialist::withCount('doctors')->latest('id'); // Get count of doctors for each specialty

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $specialists = $query->paginate($request->get('per_page', 15));
        
        return SpecialistResource::collection($specialists);
    }

    /**
     * Display a simple listing of the resource for dropdowns.
     */
    public function indexList()
    {
        return SpecialistResource::collection(Specialist::orderBy('name')->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:specialists,name',
        ]);
        $specialist = Specialist::create($validated);
        return new SpecialistResource($specialist);
    }

    /**
     * Display the specified resource.
     */
    public function show(Specialist $specialist)
    {
        return new SpecialistResource($specialist->loadCount('doctors'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Specialist $specialist)
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('specialists')->ignore($specialist->id),],
            'firestore_id' => ['sometimes', 'required', 'string', 'max:255'],
        ]);
        $specialist->update($validated);
        return new SpecialistResource($specialist);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Specialist $specialist)
    {
        if ($specialist->doctors()->exists()) {
            return response()->json(['message' => 'Cannot delete this specialization as it is assigned to one or more doctors.'], 403);
        }
        $specialist->delete();
        return response()->json(null, 204);
    }
}