<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MainTest;
use Illuminate\Http\Request;
use App\Http\Resources\MainTestResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MainTestController extends Controller
{
    public function __construct()
    {
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
            'main_test_name' => ['sometimes', 'required', 'string', 'max:70', Rule::unique('main_tests')->ignore($mainTest->id)],
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
    // app/Http/Controllers/Api/MainTestController.php
    public function batchUpdatePrices(Request $request)
    {
        // $this->authorize('edit lab_tests'); // Or a specific 'update lab_test_prices'
        $validated = $request->validate([
            'updates' => 'required|array',
            'updates.*.id' => 'required|integer|exists:main_tests,id',
            'updates.*.price' => 'required|numeric|min:0',
        ]);

        // return $validated;

        DB::beginTransaction();
        try {
            foreach ($validated['updates'] as $update) {
                MainTest::where('id', $update['id'])->update(['price' => $update['price']]);
            }
            DB::commit();
            return response()->json(['message' => 'تم تحديث أسعار الفحوصات بنجاح.']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشل تحديث الأسعار.', 'error' => $e->getMessage()], 500);
        }
    }
    // app/Http/Controllers/Api/MainTestController.php
    public function batchDeleteTests(Request $request)
    {
        // $this->authorize('delete lab_tests');
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required|integer|exists:main_tests,id',
        ]);

        $deletedCount = 0;
        $errors = [];
        DB::beginTransaction();
        try {
            foreach ($validated['ids'] as $id) {
                $mainTest = MainTest::find($id);
                if ($mainTest) {
                    // Dependency check (example)
                    if ($mainTest->labRequests()->exists() ) {
                        $errors[] = "لا يمكن حذف الفحص '{$mainTest->main_test_name}' لارتباطه ببيانات أخرى.";
                        continue;
                    }
                    if ($mainTest->delete()) {
                        // Delete child tests if delete was successful
                        $mainTest->childTests()->delete();
                    }
                    $deletedCount++;
                }
            }
            DB::commit();
            $message = "تم حذف {$deletedCount} فحص بنجاح.";
            if (!empty($errors)) {
                $message .= " الأخطاء: " . implode(', ', $errors);
            }
            return response()->json(['message' => $message, 'deleted_count' => $deletedCount, 'errors' => $errors]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشل حذف الفحوصات.', 'error' => $e->getMessage()], 500);
        }
    }
}
