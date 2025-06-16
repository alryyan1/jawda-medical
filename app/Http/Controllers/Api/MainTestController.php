<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MainTest;
use Illuminate\Http\Request;
use App\Http\Resources\MainTestResource;
use App\Http\Resources\MainTestStrippedResource;
use App\Models\DoctorVisit;
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
    // app/Http/Controllers/Api/MainTestController.php
    public function findByIdOrCode(Request $request, $identifier)
    {
        // $this->authorize('list lab_tests'); // Or specific permission
        $mainTest = MainTest::where('id', $identifier)
            // ->orWhere('short_code', $identifier) // If you have short codes
            ->where('available', true)
            ->first();

        if (!$mainTest) {
            return response()->json(['message' => 'لم يتم العثور على فحص بهذا المعرف أو الرمز.'], 404);
        }
        // Optionally, check if this test is already requested for the current visit if visit_id is passed
        if ($request->has('visit_id')) {
            $visit = DoctorVisit::find($request->visit_id);
            if ($visit && $visit->labRequests()->where('main_test_id', $mainTest->id)->exists()) {
                return response()->json(['message' => 'هذا الفحص مطلوب بالفعل لهذه الزيارة.'], 409); // Conflict
            }
        }
        return new MainTestStrippedResource($mainTest);
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

        // // return ['name' => 'waleed'];
        if ($request->filled('container_id')) {
            $query->where('container_id', $request->container_id);
        }
        // $query = MainTest::with('container', 'package'); // serviceGroup if tests are also services
        // $query->where('available', true); // Only available tests
    
    
        // if ($request->filled('pack_id') && $request->pack_id !== 'all') { // 'all' could mean no package filter
        //     $query->where('pack_id', $request->pack_id);
        // } elseif ($request->pack_id === 'none') { // Explicitly request tests with no package
        //      $query->whereNull('pack_id');
        // }
        // // Exclude tests already requested for a specific visit if visit_id is provided
        // if ($request->filled('visit_id_to_exclude_requests')) {
        //     $visit = DoctorVisit::find($request->visit_id_to_exclude_requests);
        //     if ($visit) {
        //         $requestedTestIds = $visit->labRequests()->pluck('main_test_id')->toArray();
        //         $query->whereNotIn('id', $requestedTestIds);
        //     }
        // }

        // If not paginating for a selection list (e.g., for a specific package tab)
        if ($request->boolean('no_pagination')) {
            
            // $query->when('pack_id',function($q)use($request){
            //     $q->where('pack_id','=',$request->get('pack_id'));
            // });
            $mainTests = $query->orderBy('id')->get();
            return MainTestStrippedResource::collection($mainTests);
        }

        $mainTests = $query->orderBy('id')->paginate($request->get('per_page', 50)); // Default 50 for selection
        return MainTestResource::collection($mainTests); // Or MainTestStrippedResource

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
                    if ($mainTest->labRequests()->exists()) {
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
