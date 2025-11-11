<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChildTest;
use App\Models\ChildTestOption;
use Illuminate\Http\Request;
use App\Http\Resources\ChildTestOptionResource; // We created this
use Illuminate\Validation\Rule;

class ChildTestOptionController extends Controller
{
    // Scoped to a ChildTest
    public function index(ChildTest $childTest)
    {
        // $this->authorize('view', $childTest); // Or 'manage lab_test_child_options'
        return ChildTestOptionResource::collection($childTest->options()->orderBy('name')->get());
    }

    public function store(Request $request, ChildTest $childTest)
    {
        // $this->authorize('update', $childTest); // If adding option implies modifying parent
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255',
                Rule::unique('child_test_options')->where(function ($query) use ($childTest) {
                    return $query->where('child_test_id', $childTest->id);
                })
            ],
            // Add other fields for ChildTestOption if any (e.g., 'value_code')
        ]);
        $option = $childTest->options()->create($validated);
        return new ChildTestOptionResource($option);
    }

    public function update(Request $request, ChildTest $childTest, ChildTestOption $option)
    {
        if ($option->child_test_id !== $childTest->id) abort(404);
        // $this->authorize('update', $childTest);
        $validated = $request->validate([
            'name' => ['sometimes','required', 'string', 'max:255',
                Rule::unique('child_test_options')->ignore($option->id)->where(function ($query) use ($childTest) {
                    return $query->where('child_test_id', $childTest->id);
                })
            ],
        ]);
        $option->update($validated);
        return new ChildTestOptionResource($option);
    }

    public function destroy(ChildTest $childTest, ChildTestOption $option)
    {
        // if ($option->child_test_id !== $childTest->id) abort(404);
        // $this->authorize('update', $childTest);
        $option->delete();
        // return response()->json(null, 204);
        // return response()->json(['message' => 'تم حذف الخيار بنجاح'], 200);
    }
}