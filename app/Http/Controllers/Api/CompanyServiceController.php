<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Service;
use App\Models\CompanyService; // The pivot model
use Illuminate\Http\Request;
use App\Http\Resources\CompanyServiceResource;
use App\Http\Resources\ServiceResource; // For listing available services
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class CompanyServiceController extends Controller
{


    // List all contracted services for a specific company
    public function index(Company $company, Request $request)
    {
        $search = request('search'); // e.g., ?search=cleaning

        // Get the query builder for the contractedServices relationship
        $query = $company->contractedServices();

        // Filter by service name if a search term is present
        if ($search) {
            $query->where('services.name', 'like', '%' . $search . '%');
        }

        // Optionally eager load the serviceGroup
        $query->with('serviceGroup');

        // Paginate results
        if ($request->has('page') && $request->page == 0) {
            $contractedServices = $query->get();
        } else {
            $contractedServices = $query->paginate(20);
        }

        return CompanyServiceResource::collection($contractedServices);
    }
    public function copyContractsFrom(Request $request, Company $targetCompany, Company $sourceCompany)
    {
        // Authorization: User should be able to manage contracts for the targetCompany
        // if (!Auth::user()->can('manage company_contracts', $targetCompany)) { // Example policy check
        //     return response()->json(['message' => 'Unauthorized to modify target company contracts.'], 403);
        // }
        // if (!Auth::user()->can('view company_contracts', $sourceCompany)) { // Example policy check
        //     return response()->json(['message' => 'Unauthorized to view source company contracts.'], 403);
        // }

        if ($targetCompany->id === $sourceCompany->id) {
            return response()->json(['message' => 'لا يمكن نسخ العقود من نفس الشركة إلى نفسها.'], 422);
        }

        // Crucial Check: Target company must not have existing service contracts
        // if ($targetCompany->contractedServices()->exists()) {
        //     return response()->json(['message' => 'لا يمكن نسخ العقود. الشركة المستهدفة لديها عقود خدمات موجودة بالفعل.'], 409); // 409 Conflict
        // }

        $sourceContracts = $sourceCompany->contractedServices()->get();

        if ($sourceContracts->isEmpty()) {
            return response()->json(['message' => 'الشركة المصدر لا تحتوي على عقود خدمات لنسخها.', 'copied_count' => 0], 404);
        }

        // Get existing contracted service IDs for the target company
        $existingServiceIds = $targetCompany->contractedServices()->pluck('services.id')->toArray();

        $attachData = [];
        $updateData = [];
        
        foreach ($sourceContracts as $sourceContractPivotedService) {
            // $sourceContractPivotedService is a Service model with ->pivot populated
            $pivotData = $sourceContractPivotedService->pivot;
            $serviceId = $sourceContractPivotedService->id;
            
            $contractData = [
                'price' => $pivotData->price,
                'static_endurance' => $pivotData->static_endurance,
                'percentage_endurance' => $pivotData->percentage_endurance,
                'static_wage' => $pivotData->static_wage,
                'percentage_wage' => $pivotData->percentage_wage,
                'use_static' => $pivotData->use_static,
                'approval' => $pivotData->approval, // Copy approval status as well
                'updated_at' => now(),
            ];

            // Check if service already exists for target company
            if (in_array($serviceId, $existingServiceIds)) {
                // Service exists, prepare for update
                $updateData[$serviceId] = $contractData;
            } else {
                // Service doesn't exist, prepare for attach
                $contractData['created_at'] = now(); // New timestamps for the new contract
                $attachData[$serviceId] = $contractData;
            }
        }

        DB::beginTransaction();
        try {
            $attachedCount = 0;
            $updatedCount = 0;

            // Attach new contracts
            if (!empty($attachData)) {
                $targetCompany->contractedServices()->attach($attachData);
                $attachedCount = count($attachData);
            }

            // Update existing contracts
            if (!empty($updateData)) {
                foreach ($updateData as $serviceId => $data) {
                    $targetCompany->contractedServices()->updateExistingPivot($serviceId, $data);
                }
                $updatedCount = count($updateData);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to copy service contracts from company {$sourceCompany->id} to {$targetCompany->id}: " . $e->getMessage());
            return response()->json(['message' => 'فشل نسخ عقود الخدمات.', 'error' => $e->getMessage()], 500);
        }

        $totalCount = $attachedCount + $updatedCount;
        $message = "تم ";
        if ($attachedCount > 0 && $updatedCount > 0) {
            $message .= "إضافة {$attachedCount} عقد جديد وتحديث {$updatedCount} عقد موجود";
        } elseif ($attachedCount > 0) {
            $message .= "إضافة {$attachedCount} عقد خدمة جديد";
        } elseif ($updatedCount > 0) {
            $message .= "تحديث {$updatedCount} عقد خدمة موجود";
        }
        $message .= " بنجاح من " . $sourceCompany->name . " إلى " . $targetCompany->name . ".";

        return response()->json([
            'message' => $message,
            'copied_count' => $totalCount,
            'attached_count' => $attachedCount,
            'updated_count' => $updatedCount,
        ]);
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
    /**
     * Import all active services from the main services list into this company's contract,
     * if they don't already exist. Uses default/initial contract terms.
     */
    public function importAllServices(Request $request, Company $company)
    {
        // $this->authorize('manage company_contracts', $company); // Example permission

        $validated = $request->validate([
            'default_static_endurance' => 'nullable|numeric|min:0',
            'default_percentage_endurance' => 'nullable|numeric|min:0|max:100',
            'default_static_wage' => 'nullable|numeric|min:0',
            'default_percentage_wage' => 'nullable|numeric|min:0|max:100',
            'default_use_static' => 'nullable|boolean',
            'default_approval' => 'nullable|boolean',
            'price_preference' => 'required|string|in:standard_price,zero_price', // NEW
        ]);

        $allActiveServices = Service::where('activate', true)->get();
        $existingContractedServiceIds = $company->contractedServices()->pluck('services.id')->toArray();

        $servicesToImport = $allActiveServices->reject(function ($service) use ($existingContractedServiceIds) {
            return in_array($service->id, $existingContractedServiceIds);
        });

        if ($servicesToImport->isEmpty()) {
            return response()->json(['message' => 'All active services are already contracted for this company.', 'imported_count' => 0]);
        }

        // Use company's default service endurance if specific default_percentage_endurance is not provided
        $companyServiceEndurancePercent = $company->service_endurance ?? 0;

        $defaultContractTerms = [
            'static_endurance' => (float)($validated['default_static_endurance'] ?? 0.00),
            'percentage_endurance' => (float)($validated['default_percentage_endurance'] ?? $companyServiceEndurancePercent),
            'static_wage' => (float)($validated['default_static_wage'] ?? 0.00),
            'percentage_wage' => (float)($validated['default_percentage_wage'] ?? 0.00),
            'use_static' => $validated['default_use_static'] ?? false,
            'approval' => $validated['default_approval'] ?? true,
            // Timestamps are handled by Eloquent if using attach with array
        ];
        
        $attachData = [];
        foreach ($servicesToImport as $service) {
            $contractPrice = 0.00; // Default to zero
            if ($validated['price_preference'] === 'standard_price') {
                $contractPrice = (float)($service->price ?? 0.00);
            }

            $attachData[$service->id] = array_merge(
                $defaultContractTerms,
                ['price' => $contractPrice] 
            );
        }

        DB::beginTransaction();
        try {
            if (!empty($attachData)) {
                $company->contractedServices()->attach($attachData);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to import services to company contract {$company->id}: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while importing services.', 'error_details' => $e->getMessage()], 500);
        }

        return response()->json([
            'message' => count($attachData) . " services imported successfully to the company's contract.",
            'imported_count' => count($attachData),
        ]);
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
