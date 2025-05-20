<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Service;
use App\Models\CompanyService; // The pivot model
use Illuminate\Http\Request;
use App\Http\Resources\CompanyServiceResource;
use App\Http\Resources\ServiceResource; // For listing available services
use Illuminate\Validation\Rule;

class CompanyServiceController extends Controller
{
    // List all contracted services for a specific company
   public function index(Company $company)
{
    $search = request('service_name'); // e.g., ?search=cleaning

    // Get the query builder for the contractedServices relationship
    $query = $company->contractedServices();

    // Filter by service name if a search term is present
    if ($search) {
        $query->where('services.name', 'like', '%' . $search . '%');
    }

    // Optionally eager load the serviceGroup
    $query->with('serviceGroup');

    // Paginate results
    $contractedServices = $query->paginate(20);

    return CompanyServiceResource::collection($contractedServices);
}
    // List services NOT yet contracted by this company (for adding new contracts)
    public function availableServices(Company $company)
    {
        $contractedServiceIds = $company->contractedServices()->pluck('services.id');
        $availableServices = Service::whereNotIn('id', $contractedServiceIds)
            ->where('activate', true) // Only active services
            ->with('serviceGroup')
            ->orderBy('name')
            ->get();
        return ServiceResource::collection($availableServices);
    }

    // Add a new service contract to a company
    public function store(Request $request, Company $company)
    {
        $validatedData = $request->validate([
            'service_id' => [
                'required',
                'exists:services,id',
                // Ensure this service isn't already contracted by this company
                Rule::unique('company_service')->where(function ($query) use ($company) {
                    return $query->where('company_id', $company->id);
                }),
            ],
            'price' => 'required|numeric|min:0',
            'static_endurance' => 'required|numeric|min:0',
            'percentage_endurance' => 'required|numeric|min:0|max:100',
            'static_wage' => 'required|numeric|min:0',
            'percentage_wage' => 'required|numeric|min:0|max:100',
            'use_static' => 'required|boolean',
            'approval' => 'required|boolean',
        ]);

        // Use attach method for belongsToMany
        $company->contractedServices()->attach($validatedData['service_id'], [
            'price' => $validatedData['price'],
            'static_endurance' => $validatedData['static_endurance'],
            'percentage_endurance' => $validatedData['percentage_endurance'],
            'static_wage' => $validatedData['static_wage'],
            'percentage_wage' => $validatedData['percentage_wage'],
            'use_static' => $validatedData['use_static'],
            'approval' => $validatedData['approval'],
            // 'created_at' => now(), 'updated_at' => now(), // If pivot has timestamps & not auto
        ]);

        // Fetch the newly added pivot record to return it
        $companyServiceEntry = CompanyService::where('company_id', $company->id)
            ->where('service_id', $validatedData['service_id'])
            ->firstOrFail();

        return new CompanyServiceResource($companyServiceEntry->load('service.serviceGroup'));
    }

    // Update an existing service contract for a company
    // Needs company_id and service_id to identify the pivot record
    public function update(Request $request, Company $company, Service $service) // Route model binding for pivot
    {
        // Check if the service is actually contracted by the company
        if (!$company->contractedServices()->where('services.id', $service->id)->exists()) {
            return response()->json(['message' => 'Service contract not found for this company.'], 404);
        }

        $validatedData = $request->validate([
            'price' => 'sometimes|required|numeric|min:0',
            'static_endurance' => 'sometimes|required|numeric|min:0',
            'percentage_endurance' => 'sometimes|required|numeric|min:0|max:100',
            'static_wage' => 'sometimes|required|numeric|min:0',
            'percentage_wage' => 'sometimes|required|numeric|min:0|max:100',
            'use_static' => 'sometimes|required|boolean',
            'approval' => 'sometimes|required|boolean',
        ]);

        $company->contractedServices()->updateExistingPivot($service->id, $validatedData);

        $updatedEntry = CompanyService::where('company_id', $company->id)
            ->where('service_id', $service->id)
            ->firstOrFail();

        return new CompanyServiceResource($updatedEntry->load('service.serviceGroup'));
    }

    // Remove a service contract from a company
    public function destroy(Company $company, Service $service)
    {
        if (!$company->contractedServices()->where('services.id', $service->id)->exists()) {
            return response()->json(['message' => 'Service contract not found for this company.'], 404);
        }
        $company->contractedServices()->detach($service->id);
        return response()->json(null, 204);
    }
}
