<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChildTest;
use App\Models\MainTest;
use Illuminate\Http\Request;
use App\Http\Resources\ChildTestResource;
use Illuminate\Validation\Rule;

class ChildTestController extends Controller
{
    public function __construct() {
        // Permissions: e.g., 'manage lab_tests' or more specific 'manage child_tests'
    }

 public function index(Request $request, MainTest $mainTest)
{
    $childTests = $mainTest->childTests()
                           ->with(['unit', 'childGroup', 'options']) // Eager load options
                           ->orderBy('test_order')
                           ->orderBy('child_test_name')
                           ->get();
    return ChildTestResource::collection($childTests);
}

// Example in ChildTestController@show
public function show(MainTest $mainTest, ChildTest $childTest)
{
     if ($childTest->main_test_id !== $mainTest->id) abort(404);
    return new ChildTestResource($childTest->loadMissing(['unit', 'childGroup', 'options'])); // Load options if not already loaded
}

    /**
     * Store a new child test for a main test.
     */
    public function store(Request $request, MainTest $mainTest)
    {
        // $this->authorize('update', $mainTest); // User needs permission to modify the main test
        $validatedData = $request->validate([
            'child_test_name' => ['required', 'string', 'max:70',
                Rule::unique('child_tests')->where(function ($query) use ($mainTest) {
                    return $query->where('main_test_id', $mainTest->id);
                })
            ],
            'low' => 'nullable|numeric',
            'upper' => 'nullable|numeric|gte:low', // Greater than or equal to low if both present
            'defval' => 'nullable|string|max:1000',
            'unit_id' => 'nullable|exists:units,id',
            'normalRange' => 'required_without_all:low,upper|nullable|string|max:1000', // Required if low/upper not set
            'max' => 'nullable|numeric',
            'lowest' => 'nullable|numeric|lte:max', // Less than or equal to max if both present
            'test_order' => 'nullable|integer',
            'child_group_id' => 'nullable|exists:child_groups,id',
        ]);

        $childTest = $mainTest->childTests()->create($validatedData);
        return new ChildTestResource($childTest->load(['unit', 'childGroup']));
    }

    

    /**
     * Update the specified child test.
     */
    public function update(Request $request, MainTest $mainTest, ChildTest $childTest)
    {
        if ($childTest->main_test_id !== $mainTest->id) abort(404);
        // $this->authorize('update', $mainTest);

        $validatedData = $request->validate([
            'child_test_name' => ['sometimes','required', 'string', 'max:70',
                Rule::unique('child_tests')->ignore($childTest->id)->where(function ($query) use ($mainTest) {
                    return $query->where('main_test_id', $mainTest->id);
                })
            ],
            'low' => 'nullable|numeric',
            'upper' => 'nullable|numeric|gte:low',
            'defval' => 'nullable|string|max:1000',
            'unit_id' => 'nullable|exists:units,id',
            'normalRange' => 'required_without_all:low,upper|nullable|string|max:1000',
            'max' => 'nullable|numeric',
            'lowest' => 'nullable|numeric|lte:max',
            'test_order' => 'nullable|integer',
            'child_group_id' => 'nullable|exists:child_groups,id',
        ]);

        $childTest->update($validatedData);
        return new ChildTestResource($childTest->load(['unit', 'childGroup']));
    }

    /**
     * Remove the specified child test.
     */
    public function destroy(MainTest $mainTest, ChildTest $childTest)
    {
        if ($childTest->main_test_id !== $mainTest->id) abort(404);
        // $this->authorize('update', $mainTest); // Or specific delete child_test permission

        // Add checks: e.g., cannot delete if linked to requested_results
        // if ($childTest->requestedResults()->exists()) { ... }

        $childTest->delete();
        return response()->json(null, 204);
    }
}