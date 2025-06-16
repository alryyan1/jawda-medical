<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ShiftDefinition;
use Illuminate\Http\Request;
use App\Http\Resources\ShiftDefinitionResource;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class ShiftDefinitionController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:list shift_definitions')->only('index', 'indexList');
        // $this->middleware('can:create shift_definitions')->only('store');
        // $this->middleware('can:edit shift_definitions')->only('update');
        // $this->middleware('can:delete shift_definitions')->only('destroy');
    }

    public function index(Request $request)
    {
        // if (!Auth::user()->can('list shift_definitions')) { /* ... */ }
        $query = ShiftDefinition::query();
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }
        $shifts = $query->orderBy('shift_label')->paginate($request->get('per_page', 15));
        return ShiftDefinitionResource::collection($shifts);
    }
    
    public function indexList(Request $request) // For dropdowns
    {
        // if (!Auth::user()->can('list shift_definitions')) { /* ... */ }
        $query = ShiftDefinition::query();
        if ($request->boolean('active_only')) {
            $query->where('is_active', true);
        }
        return ShiftDefinitionResource::collection($query->orderBy('shift_label')->get());
    }


    public function store(Request $request)
    {
        // if (!Auth::user()->can('create shift_definitions')) { /* ... */ }
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'shift_label' => 'required|string|max:50|unique:shifts_definitions,shift_label',
            'start_time' => 'required|date_format:H:i', // Example: 08:00
            'end_time' => 'required|date_format:H:i',   // Example: 16:00
            'is_active' => 'sometimes|boolean',
        ]);
        $shiftDefinition = ShiftDefinition::create($validated);
        return new ShiftDefinitionResource($shiftDefinition);
    }

    public function show(ShiftDefinition $shiftDefinition)
    {
        // if (!Auth::user()->can('view shift_definitions')) { /* ... */ }
        return new ShiftDefinitionResource($shiftDefinition);
    }

    public function update(Request $request, ShiftDefinition $shiftDefinition)
    {
        // if (!Auth::user()->can('edit shift_definitions')) { /* ... */ }
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'shift_label' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('shifts_definitions')->ignore($shiftDefinition->id)],
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i',
            'is_active' => 'sometimes|boolean',
        ]);
        $shiftDefinition->update($validated);
        return new ShiftDefinitionResource($shiftDefinition);
    }

    public function destroy(ShiftDefinition $shiftDefinition)
    {
        // if (!Auth::user()->can('delete shift_definitions')) { /* ... */ }
        // Add checks: cannot delete if in use by attendances or user_default_shifts
        if ($shiftDefinition->attendances()->exists() || $shiftDefinition->users()->exists()) {
             return response()->json(['message' => 'لا يمكن حذف تعريف الوردية هذا لارتباطه ببيانات حضور أو مستخدمين.'], 403);
        }
        $shiftDefinition->delete();
        return response()->json(null, 204);
    }
}