<?php // Similar to UnitController
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller; use App\Models\ChildGroup; use Illuminate\Http\Request; use App\Http\Resources\ChildGroupResource;
class ChildGroupController extends Controller {
    public function indexList() { return ChildGroupResource::collection(ChildGroup::orderBy('name')->get()); }
    public function store(Request $request) {
         // Add permission
        $validated = $request->validate(['name' => 'required|string|max:255|unique:child_groups,name']);
        return new ChildGroupResource(ChildGroup::create($validated));
    }
}