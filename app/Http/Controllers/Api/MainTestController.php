<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\MainTest;
use Illuminate\Http\Request;
use App\Http\Resources\MainTestResource;
use Illuminate\Validation\Rule;

class MainTestController extends Controller
{
    public function __construct() {
        // $this->middleware('can:list lab_tests')->only('index');
        // $this->middleware('can:create lab_tests')->only('store');
        // $this->middleware('can:edit lab_tests')->only('update');
        // ... etc.
    }

    public function index(Request $request)
    {
        $query = MainTest::with('container'); // Eager load container

        if ($request->filled('search')) {
            $query->where('main_test_name', 'LIKE', '%' . $request->search . '%');
        }
        if ($request->has('available') && $request->available !== '') {
            $query->where('available', (bool)$request->available);
        }
        if ($request->filled('container_id')) {
            $query->where('container_id', $request->container_id);
        }

        $mainTests = $query->orderBy('main_test_name')->paginate($request->get('per_page', 15));
        return MainTestResource::collection($mainTests);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'main_test_name' => 'required|string|max:70|unique:main_tests,main_test_name',
            'pack_id' => 'nullable|integer', // Add exists rule if 'packs' table exists
            'pageBreak' => 'required|boolean',
            'container_id' => 'required|exists:containers,id',
            'price' => 'nullable|numeric|min:0',
            'divided' => 'required|boolean',
            'available' => 'required|boolean',
        ]);
        $mainTest = MainTest::create($validatedData);
        return new MainTestResource($mainTest->load('container'));
    }

    public function show(MainTest $mainTest)
    {
        return new MainTestResource($mainTest->load('container'));
    }

    public function update(Request $request, MainTest $mainTest)
    {
        $validatedData = $request->validate([
            'main_test_name' => ['sometimes','required','string','max:70', Rule::unique('main_tests')->ignore($mainTest->id)],
            'pack_id' => 'nullable|integer',
            'pageBreak' => 'sometimes|required|boolean',
            'container_id' => 'sometimes|required|exists:containers,id',
            'price' => 'nullable|numeric|min:0',
            'divided' => 'sometimes|required|boolean',
            'available' => 'sometimes|required|boolean',
        ]);
        $mainTest->update($validatedData);
        return new MainTestResource($mainTest->load('container'));
    }

    public function destroy(MainTest $mainTest)
    {
        // Add checks: e.g., cannot delete if linked to lab_requests or child_tests
        if ($mainTest->labRequests()->exists() || $mainTest->childTests()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف هذا الفحص لارتباطه بطلبات أو فحوصات فرعية.'], 403);
        }
        $mainTest->delete();
        return response()->json(null, 204);
    }
}