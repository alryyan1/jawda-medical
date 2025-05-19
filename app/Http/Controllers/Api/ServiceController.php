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
        $services = Service::with('serviceGroup')->orderBy('name')->paginate(15);
        // return new ServiceCollection($services);
        return ServiceResource::collection($services); // Default collection for pagination
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