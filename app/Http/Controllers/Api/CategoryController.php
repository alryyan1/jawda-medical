<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Doctor;
use App\Models\Service;
use App\Http\Resources\CategoryWithServicesResource;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class CategoryController extends Controller
{
    public function __construct()
    {
        // Add permissions if needed
    }

    /**
     * Display a paginated listing of categories.
     */
    public function index(Request $request)
    {
        $query = Category::withCount(['services', 'doctors']);

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $categories = $query->orderBy('name')->paginate($request->get('per_page', 15));
        
        return response()->json($categories);
    }

    /**
     * Store a newly created category.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:categories,name',
            'description' => 'nullable|string',
        ]);

        $category = Category::create($validatedData);
        
        return response()->json($category, 201);
    }

    /**
     * Display the specified category with services and doctors.
     */
    public function show(Category $category)
    {
        $category->load(['services' => function ($query) {
            $query->with('serviceGroup');
        }, 'doctors' => function ($query) {
            $query->with('specialist');
        }]);
        
        return new CategoryWithServicesResource($category);
    }

    /**
     * Update the specified category.
     */
    public function update(Request $request, Category $category)
    {
        $validatedData = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories')->ignore($category->id)],
            'description' => 'nullable|string',
        ]);

        $category->update($validatedData);
        
        return response()->json($category);
    }

    /**
     * Remove the specified category.
     */
    public function destroy(Category $category)
    {
        // Check if category has doctors assigned
        if ($category->doctors()->exists()) {
            return response()->json([
                'message' => 'لا يمكن حذف هذه الفئة لارتباطها بأطباء. قم بإزالة الأطباء أولاً.'
            ], 403);
        }

        $category->delete();
        
        return response()->json(null, 204);
    }

    /**
     * Assign services to category with percentage/fixed.
     */
    public function assignServices(Request $request, Category $category)
    {
        $validatedData = $request->validate([
            'services' => 'required|array',
            'services.*.service_id' => 'required|integer|exists:services,id',
            'services.*.percentage' => 'nullable|numeric|min:0|max:100|required_without:services.*.fixed',
            'services.*.fixed' => 'nullable|numeric|min:0|required_without:services.*.percentage',
        ]);

        DB::transaction(function () use ($category, $validatedData) {
            foreach ($validatedData['services'] as $serviceData) {
                if (empty($serviceData['percentage']) && empty($serviceData['fixed'])) {
                    continue; // Skip if both are empty
                }

                $category->services()->syncWithoutDetaching([
                    $serviceData['service_id'] => [
                        'percentage' => $serviceData['percentage'] ?? null,
                        'fixed' => $serviceData['fixed'] ?? null,
                    ]
                ], false);
            }
        });

        $category->load(['services' => function ($query) {
            $query->with('serviceGroup');
        }]);
        
        return new CategoryWithServicesResource($category);
    }

    /**
     * Update service percentage/fixed in category.
     */
    public function updateService(Request $request, Category $category, Service $service)
    {
        // Ensure the service is in the category
        if (!$category->services()->where('services.id', $service->id)->exists()) {
            return response()->json([
                'message' => 'هذه الخدمة غير مضافة لهذه الفئة.'
            ], 404);
        }

        $validatedData = $request->validate([
            'percentage' => 'nullable|numeric|min:0|max:100|required_without:fixed',
            'fixed' => 'nullable|numeric|min:0|required_without:percentage',
        ]);

        if (empty($validatedData['percentage']) && empty($validatedData['fixed'])) {
            return response()->json([
                'message' => 'يجب توفير نسبة مئوية أو مبلغ ثابت.'
            ], 422);
        }

        $category->services()->updateExistingPivot($service->id, [
            'percentage' => $validatedData['percentage'] ?? null,
            'fixed' => $validatedData['fixed'] ?? null,
        ]);

        // Reload the relationship to get updated pivot data
        $category->load(['services' => function ($query) use ($service) {
            $query->with('serviceGroup')->where('services.id', $service->id);
        }]);
        
        $updatedService = $category->services->first();
        
        return response()->json([
            'id' => $updatedService->id,
            'name' => $updatedService->name,
            'price' => $updatedService->price,
            'service_group_id' => $updatedService->service_group_id,
            'service_group' => $updatedService->serviceGroup ? [
                'id' => $updatedService->serviceGroup->id,
                'name' => $updatedService->serviceGroup->name,
            ] : null,
            'percentage' => $updatedService->pivot->percentage,
            'fixed' => $updatedService->pivot->fixed,
        ]);
    }

    /**
     * Remove service from category.
     */
    public function removeService(Category $category, Service $service)
    {
        if (!$category->services()->where('services.id', $service->id)->exists()) {
            return response()->json([
                'message' => 'Service not in category.'
            ], 404);
        }

        $category->services()->detach($service->id);
        
        return response()->json(null, 204);
    }

    /**
     * Assign doctor to category (one doctor = one category).
     */
    public function assignDoctor(Category $category, Doctor $doctor)
    {
        // If doctor already has a category, remove from old category first
        if ($doctor->category_id && $doctor->category_id != $category->id) {
            // Optionally, you might want to prevent this or ask for confirmation
            // For now, we'll just update it
        }

        $doctor->update(['category_id' => $category->id]);
        
        return response()->json([
            'message' => 'تم تعيين الطبيب للفئة بنجاح.',
            'doctor' => $doctor->load('specialist')
        ]);
    }

    /**
     * Remove doctor from category.
     */
    public function removeDoctor(Category $category, Doctor $doctor)
    {
        if ($doctor->category_id != $category->id) {
            return response()->json([
                'message' => 'هذا الطبيب غير مخصص لهذه الفئة.'
            ], 404);
        }

        $doctor->update(['category_id' => null]);
        
        return response()->json(null, 204);
    }
}
