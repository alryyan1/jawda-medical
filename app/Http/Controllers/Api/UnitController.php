<?php // Similar to ContainerController, for dropdowns and quick-add
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller; use App\Models\Unit; use Illuminate\Http\Request; use App\Http\Resources\UnitResource;
class UnitController extends Controller {
    public function indexList() { return UnitResource::collection(Unit::orderBy('name')->get()); }
    public function store(Request $request) {
        // Add permission
        $validated = $request->validate(['name' => 'required|string|max:20|unique:units,name']);
        return new UnitResource(Unit::create($validated));
    }
}