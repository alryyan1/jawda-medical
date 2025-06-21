<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ServiceGroup;
use Illuminate\Http\Request;
use App\Http\Resources\ServiceGroupResource;
use App\Http\Resources\ServiceGroupCollection; // For paginated list
use Illuminate\Validation\Rule;

class ServiceGroupController extends Controller
{
    public function __construct()
    {
        // Add permissions if needed, e.g.,
        // $this->middleware('can:list service_groups')->only(['index', 'indexList']);
        // $this->middleware('can:manage service_groups')->except(['index', 'indexList']);
    }

    /**
     * Display a paginated listing of the resource.
     */
    public function index(Request $request)
    {
        $query = ServiceGroup::withCount('services'); // Optionally count services

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }
        
        $serviceGroups = $query->orderBy('name')->paginate($request->get('per_page', 15));
        return new ServiceGroupCollection($serviceGroups);
    }

    /**
     * For dropdowns - returns all (non-paginated)
     */
    public function indexList()
    {
        return ServiceGroupResource::collection(ServiceGroup::orderBy('name')->get());
    }
    
    /**
     * Get service groups along with their active services.
     * (Existing method, can remain as is or be adapted if needed for other contexts)
     */
    public function getGroupsWithServices(Request $request)
    {
        // ... (existing logic from your previous ServiceGroupController) ...
        // This method seems more for service selection UI than CRUD management of groups.
        // For now, keep it as is. If not used by new page, it's fine.
        $visitId = $request->query('visit_id');
        $visit = $visitId ? \App\Models\DoctorVisit::find($visitId) : null;

        $query = ServiceGroup::with(['services' => function ($serviceQuery) use ($visit) {
            $serviceQuery->where('activate', true)->orderBy('name');
            if ($visit) {
                $requestedServiceIds = $visit->requestedServices()->pluck('service_id')->toArray();
                $serviceQuery->whereNotIn('id', $requestedServiceIds);
            }
        }])
        ->whereHas('services', function ($serviceQuery) use ($visit) {
            $serviceQuery->where('activate', true);
            if ($visit) {
                $requestedServiceIds = $visit->requestedServices()->pluck('service_id')->toArray();
                $serviceQuery->whereNotIn('id', $requestedServiceIds);
            }
        })
        ->orderBy('name')
        ->get();
        return \App\Http\Resources\ServiceGroupWithServicesResource::collection($query);
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:service_groups,name',
            // Add other fields if ServiceGroup model has them
        ]);
        $serviceGroup = ServiceGroup::create($validatedData);
        return new ServiceGroupResource($serviceGroup);
    }

    /**
     * Display the specified resource.
     */
    public function show(ServiceGroup $serviceGroup)
    {
        return new ServiceGroupResource($serviceGroup->loadCount('services'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ServiceGroup $serviceGroup)
    {
        $validatedData = $request->validate([
            'name' => ['sometimes','required','string','max:255', Rule::unique('service_groups')->ignore($serviceGroup->id)],
        ]);
        $serviceGroup->update($validatedData);
        return new ServiceGroupResource($serviceGroup);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ServiceGroup $serviceGroup)
    {
        // Add check: cannot delete if it has services linked
        if ($serviceGroup->services()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف هذه المجموعة لارتباطها بخدمات. قم بنقل الخدمات أولاً.'], 403);
        }
        $serviceGroup->delete();
        return response()->json(null, 204);
    }
}