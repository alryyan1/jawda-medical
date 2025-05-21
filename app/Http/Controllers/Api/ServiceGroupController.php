<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ServiceGroup;
use Illuminate\Http\Request;
use App\Http\Resources\ServiceGroupResource;
use App\Http\Resources\ServiceGroupWithServicesResource;
use App\Models\DoctorVisit;

class ServiceGroupController extends Controller
{
    public function index() // For full list if needed, or use indexList primarily
    {
        return ServiceGroupResource::collection(ServiceGroup::orderBy('name')->get());
    }

    public function indexList() // For dropdowns
    {
        return ServiceGroupResource::collection(ServiceGroup::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255|unique:service_groups,name',
        ]);
        $serviceGroup = ServiceGroup::create($validatedData);
        return new ServiceGroupResource($serviceGroup);
    }
 public function getGroupsWithServices(Request $request)
    {
        // Permission check (e.g., user must be able to request services)
        // if (!auth()->user()->can('request visit_services')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $visitId = $request->query('visit_id');
        $visit = $visitId ? DoctorVisit::find($visitId) : null;

        $query = ServiceGroup::with(['services' => function ($serviceQuery) use ($visit) {
            $serviceQuery->where('activate', true) // Only active services
                         ->orderBy('name');
            
            // Optionally filter out services already requested for the current visit
            if ($visit) {
                $requestedServiceIds = $visit->requestedServices()->pluck('service_id')->toArray();
                $serviceQuery->whereNotIn('id', $requestedServiceIds);
            }
        }])
        ->whereHas('services', function ($serviceQuery) use ($visit) { // Only return groups that have (available) services
            $serviceQuery->where('activate', true);
            if ($visit) {
                $requestedServiceIds = $visit->requestedServices()->pluck('service_id')->toArray();
                $serviceQuery->whereNotIn('id', $requestedServiceIds);
            }
        })
        ->orderBy('name')
        ->get();

        return ServiceGroupWithServicesResource::collection($query);
    }
    // Add show, update, destroy if full CRUD for service groups is needed via UI
}