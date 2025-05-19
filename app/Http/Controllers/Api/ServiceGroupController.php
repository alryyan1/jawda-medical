<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\ServiceGroup;
use Illuminate\Http\Request;
use App\Http\Resources\ServiceGroupResource;

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
    // Add show, update, destroy if full CRUD for service groups is needed via UI
}