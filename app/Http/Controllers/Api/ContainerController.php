<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Container;
use Illuminate\Http\Request;
use App\Http\Resources\ContainerResource;

class ContainerController extends Controller
{
    public function indexList() // For dropdowns
    {
        // Permission check could go here
        return ContainerResource::collection(Container::orderBy('container_name')->get());
    }

    public function store(Request $request)
    {
        // Add permission: can('create lab_test_containers')
        $validatedData = $request->validate([
            'container_name' => 'required|string|max:50|unique:containers,container_name',
        ]);
        $container = Container::create($validatedData);
        return new ContainerResource($container);
    }
    // Add index, show, update, destroy if full CRUD for Containers is needed via UI
}