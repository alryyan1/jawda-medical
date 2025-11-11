<?php
namespace App\Http\Controllers\Api;
use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Subcompany;
use Illuminate\Http\Request;
use App\Http\Resources\SubcompanyResource; // Create this
// ...

class SubcompanyController extends Controller {
    public function index(Request $request, Company $company) {
        // $this->authorize('list subcompanies');
        $subcompanies = Subcompany::where('company_id', $company->id)
            ->orderBy('name')
            ->get();
        return SubcompanyResource::collection($subcompanies);
    }

    public function indexList(Request $request) {
        // For the /subcompanies-list route
        $query = Subcompany::query();
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        return SubcompanyResource::collection($query->orderBy('name')->get());
    }

    public function store(Request $request, Company $company) {
        // $this->authorize('create subcompanies');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            // Add other required fields from subcompanies table e.g. lab_endurance, service_endurance
            'lab_endurance' => 'required|numeric|min:0',
            'service_endurance' => 'required|numeric|min:0',
        ]);
        $validated['company_id'] = $company->id;
        $subcompany = Subcompany::create($validated);
        return new SubcompanyResource($subcompany);
    }

    public function update(Request $request, Company $company, Subcompany $subcompany) {
        // $this->authorize('update subcompanies');
        $validated = $request->validate([
            'lab_endurance' => 'sometimes|numeric|min:0',
            'service_endurance' => 'sometimes|numeric|min:0',
        ]);
        $subcompany->update($validated);
        return new SubcompanyResource($subcompany->fresh());
    }
    
    // ... other CRUD methods if needed for full management
}