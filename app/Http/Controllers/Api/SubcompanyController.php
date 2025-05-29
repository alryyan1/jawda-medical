<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Subcompany;
use Illuminate\Http\Request;
use App\Http\Resources\SubcompanyResource; // Create this
// ...

class SubcompanyController extends Controller {
    public function index(Request $request) {
        // $this->authorize('list subcompanies');
        $query = Subcompany::query();
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        // Only active subcompanies? Add status filter if Subcompany has it
        return SubcompanyResource::collection($query->orderBy('name')->get());
    }
    public function store(Request $request) {
        // $this->authorize('create subcompanies');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'company_id' => 'required|integer|exists:companies,id',
            // Add other required fields from subcompanies table e.g. lab_endurance, service_endurance
            'lab_endurance' => 'required|numeric|min:0',
            'service_endurance' => 'required|numeric|min:0',
        ]);
        $subcompany = Subcompany::create($validated);
        return new SubcompanyResource($subcompany);
    }
    
    // ... other CRUD methods if needed for full management
}