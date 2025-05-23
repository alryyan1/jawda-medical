<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\MainTest; // For assigning tests to package
use Illuminate\Http\Request;
use App\Http\Resources\PackageResource;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class PackageController extends Controller
{
    public function __construct()
    {
        // Define permissions like 'list packages', 'create packages', etc.
        // $this->middleware('can:list lab_test_packages')->only('index', 'indexList');
        // $this->middleware('can:create lab_test_packages')->only('store');
        // $this->middleware('can:edit lab_test_packages')->only('update', 'assignTests');
        // $this->middleware('can:delete lab_test_packages')->only('destroy');
    }

    public function index(Request $request)
    {
        $query = Package::withCount('mainTests'); // Get count of tests in each package

        if ($request->filled('search')) {
            $query->where('package_name', 'LIKE', '%' . $request->search . '%');
        }

        $packages = $query->orderBy('package_name')->paginate($request->get('per_page', 15));
        return PackageResource::collection($packages);
    }

    // For dropdowns
    public function indexList()
    {
        $packages = Package::orderBy('package_name')->get(['package_id', 'package_name']);
        // Using map to match expected structure for simple list, or adapt PackageResource
        return response()->json($packages->map(fn($p) => ['id' => $p->package_id, 'name' => $p->package_name]));
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'package_name' => 'required|string|max:50|unique:packages,package_name',
            'container' => 'required|string|max:50', // Validate against 'containers' table if it's a FK
            'exp_time' => 'required|integer|min:0',
            'main_test_ids' => 'nullable|array', // Array of MainTest IDs to assign
            'main_test_ids.*' => 'integer|exists:main_tests,id',
        ]);

        DB::beginTransaction();
        try {
            $package = Package::create([
                'package_name' => $validatedData['package_name'],
                'container' => $validatedData['container'],
                'exp_time' => $validatedData['exp_time'],
            ]);

            if (!empty($validatedData['main_test_ids'])) {
                // Update MainTest records to set their pack_id
                MainTest::whereIn('id', $validatedData['main_test_ids'])->update(['pack_id' => $package->package_id]);
            }
            DB::commit();
            return new PackageResource($package->loadCount('mainTests'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشل إنشاء الحزمة.', 'error' => $e->getMessage()], 500);
        }
    }

    public function show(Package $package) // Route model binding uses primary key 'package_id'
    {
        return new PackageResource($package->load(['mainTests.container', 'mainTests.serviceGroup', 'mainTests.unit'])); // Example of loading test details
    }

    public function update(Request $request, Package $package)
    {
        $validatedData = $request->validate([
            'package_name' => ['sometimes','required','string','max:50', Rule::unique('packages')->ignore($package->package_id, 'package_id')],
            'container' => 'sometimes|required|string|max:50',
            'exp_time' => 'sometimes|required|integer|min:0',
            'main_test_ids' => 'nullable|array',
            'main_test_ids.*' => 'integer|exists:main_tests,id',
        ]);

        DB::beginTransaction();
        try {
            $package->update([
                'package_name' => $validatedData['package_name'] ?? $package->package_name,
                'container' => $validatedData['container'] ?? $package->container,
                'exp_time' => $validatedData['exp_time'] ?? $package->exp_time,
            ]);

            if ($request->has('main_test_ids')) {
                // 1. Unset pack_id for tests previously in this package but not in the new list
                $testsToUnassign = $package->mainTests()->whereNotIn('id', $validatedData['main_test_ids'] ?? [])->pluck('id');
                if ($testsToUnassign->isNotEmpty()) {
                    MainTest::whereIn('id', $testsToUnassign)->update(['pack_id' => null]);
                }
                // 2. Set pack_id for tests in the new list
                if (!empty($validatedData['main_test_ids'])) {
                    MainTest::whereIn('id', $validatedData['main_test_ids'])->update(['pack_id' => $package->package_id]);
                }
            }
            DB::commit();
            return new PackageResource($package->load(['mainTests', 'mainTestsCount']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشل تحديث الحزمة.', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Package $package)
    {
        // Before deleting a package, you must decide what to do with main_tests linked to it.
        // Option 1: Set main_tests.pack_id to null (orphan them from package)
        // Option 2: Prevent deletion if package contains tests (safer)
        // Option 3: Delete associated tests (cascade - careful!)

        if ($package->mainTests()->exists()) {
            // Option 2 chosen here for safety
            return response()->json(['message' => 'لا يمكن حذف الحزمة لأنها تحتوي على فحوصات. قم بإزالة الفحوصات أولاً أو انقلها.'], 403);
        }
        
        // If choosing Option 1:
        // DB::transaction(function () use ($package) {
        //     $package->mainTests()->update(['pack_id' => null]);
        //     $package->delete();
        // });

        $package->delete();
        return response()->json(null, 204);
    }

    /**
     * Assign multiple tests to a package.
     * This could be a separate endpoint if preferred over handling in update.
     */
    // public function assignTests(Request $request, Package $package) { /* ... */ }
}