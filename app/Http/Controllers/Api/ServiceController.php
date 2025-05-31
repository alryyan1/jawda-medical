<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use App\Http\Resources\ServiceResource;
use App\Http\Resources\ServiceCollection; // If you create one for pagination
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index(Request $request)
    {
        $query = Service::with('serviceGroup'); // Eager load by default
    
        // Filter by search term (service name)
        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }
    
        // Filter by service group ID
        if ($request->filled('service_group_id')) {
            $query->where('service_group_id', $request->service_group_id);
        }
    
        $services = $query->orderBy('id','desc')->paginate($request->get('per_page', 15));
        return ServiceResource::collection($services);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'service_group_id' => 'required|exists:service_groups,id',
            'price' => 'required|numeric|min:0',
            'activate' => 'required|boolean',
            'variable' => 'required|boolean', // As per schema, no default, so required
        ]);
        $service = Service::create($validatedData);
        return new ServiceResource($service->load('serviceGroup'));
    }

    public function show(Service $service)
    {
        return new ServiceResource($service->load('serviceGroup'));
    }

    public function update(Request $request, Service $service)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'service_group_id' => 'sometimes|required|exists:service_groups,id',
            'price' => 'sometimes|required|numeric|min:0',
            'activate' => 'sometimes|required|boolean',
            'variable' => 'sometimes|required|boolean',
        ]);
        $service->update($validatedData);
        return new ServiceResource($service->load('serviceGroup'));
    }

    public function destroy(Service $service)
    {
        $service->delete();
        return response()->json(null, 204);
    }
}