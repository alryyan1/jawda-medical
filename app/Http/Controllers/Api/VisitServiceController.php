<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
// app/Http/Controllers/Api/VisitServiceController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorVisit;
use App\Models\Service;
use App\Models\RequestedService;
use App\Models\Company; // If checking company contracts
use App\Http\Resources\ServiceResource;
use App\Http\Resources\RequestedServiceResource; // Create this
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VisitServiceController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:request visit_services')->only('addRequestedServices');
        $this->middleware('can:remove visit_services')->only('removeRequestedService');
        // ... etc.
    }
    public function getAvailableServices(DoctorVisit $visit) // Route model binding
    {
        // Get IDs of services already requested for this visit
        $requestedServiceIds = $visit->requestedServices()->pluck('service_id')->toArray();

        // Get patient's company if any, to check for contracted prices later (more complex)
        // $patientCompanyId = $visit->patient->company_id;

        $availableServices = Service::where('activate', true)
            ->whereNotIn('id', $requestedServiceIds)
            ->with('serviceGroup')
            ->orderBy('name')
            ->get();

        // TODO: Future enhancement: Adjust prices based on patient's company contract if applicable
        // This would involve checking the company_service table.
        // For now, returning standard service prices.

        return ServiceResource::collection($availableServices);
    }


 
 
     public function getRequestedServices(DoctorVisit $visit)
    {
        $requested = $visit->requestedServices()
                           ->with([
                               'service.serviceGroup', 
                               'requestingUser:id,name', 
                               'depositUser:id,name', 
                               'performingDoctor:id,name'
                            ])
                           ->orderBy('created_at', 'desc') // Show newest first or by other criteria
                           ->get();
        return RequestedServiceResource::collection($requested);
    }

    public function addRequestedServices(Request $request, DoctorVisit $visit)
    {
        // Permission check
        // if (!Auth::user()->can('request visit_services')) {
        //    return response()->json(['message' => 'Unauthorized'], 403);
        // }
        
        $validated = $request->validate([
            'service_ids' => 'required|array',
            'service_ids.*' => 'required|integer|exists:services,id',
            // Add validation for quantities if you implement it:
            // 'quantities' => 'nullable|array',
            // 'quantities.*' => 'required_with:quantities|integer|min:1',
        ]);

        $patient = $visit->patient()->firstOrFail(); // Ensure patient is loaded
        $company = $patient->company_id ? Company::find($patient->company_id) : null;

        $createdItems = [];
        DB::beginTransaction();
        try {
            foreach ($validated['service_ids'] as $index => $serviceId) {
                $service = Service::find($serviceId);
                if (!$service) continue;

                // Check if this exact service (by ID) is already requested for this visit to avoid duplicates
                // Or, if service can be added multiple times, ensure frontend handles quantities correctly
                $alreadyExists = $visit->requestedServices()->where('service_id', $serviceId)->exists();
                if ($alreadyExists && !$request->has('allow_duplicates')) { // Add a flag if needed
                    // Consider how to handle: skip, error, or update quantity of existing
                    // For now, we skip if it exists (simple approach)
                    // You might want to return a message indicating which were skipped.
                    continue; 
                }

                $price = $service->price;
                $companyEnduranceAmount = 0;
                $companyContractDetails = null;

                if ($company) {
                    $contract = $company->contractedServices()
                                        ->where('services.id', $serviceId)
                                        ->first(); // This gets the Service model with pivot data
                                        // return $contract;
                    if ($contract && $contract->pivot->approval) {
                        $companyContractDetails = $contract->pivot;
                        $price = $companyContractDetails->price; // Use contract price
                        // return ['price'=>$price , 'details'=>$companyContractDetails];
                        if ($companyContractDetails->use_static) {
                            $companyEnduranceAmount = $companyContractDetails->static_endurance;
                        } else {
                            $companyEnduranceAmount = ($price * $companyContractDetails->percentage_endurance) / 100;
                        }
                        // TODO: Consider company's service_roof/lab_roof and patient's remaining endurance under this company
                    }
                }
                
                $count = $request->input("quantities.{$serviceId}", 1); // Default to 1 if no quantity map provided

                $requestedService = RequestedService::create([
                    'doctorvisits_id' => $visit->id,
                    'service_id' => $serviceId,
                    'user_id' => Auth::id(),
                    'doctor_id' => $visit->doctor_id, // Doctor of the visit, or allow overriding
                    'price' => $price,
                    'amount_paid' => 0,
                    'endurance' => $companyEnduranceAmount,
                    'is_paid' => false,
                    'discount' => 0, // Handle discount application logic if any
                    'discount_per' => 0,
                    'bank' => false,
                    'count' => $count,
                    'approval' => true, // Default or from contract (companyContractDetails->approval)
                    'done' => false,
                ]);
                $createdItems[] = $requestedService;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to add services to visit.', 'error' => $e->getMessage()], 500);
        }
        
        // Load relations for the response
        $loadedItems = collect($createdItems)->map(function ($item) {
            return $item->load(['service.serviceGroup', 'requestingUser:id,name', 'performingDoctor:id,name']);
        });

        return RequestedServiceResource::collection($loadedItems);
    }

    public function removeRequestedService(DoctorVisit $visit, RequestedService $requestedService)
    {
        // ... (same as before, ensure $requestedService belongs to $visit) ...
        if ($requestedService->doctorvisits_id !== $visit->id) {
            return response()->json(['message' => 'Service not found for this visit.'], 404);
        }
        if ($requestedService->is_paid || $requestedService->done) {
             return response()->json(['message' => 'لا يمكن حذف خدمة مدفوعة أو مكتملة.'], 403);
        }
        $requestedService->delete();
        return response()->json(null, 204);
    }
    
    // Optional: Endpoint to update details of a RequestedService (count, discount)
    public function updateRequestedService(Request $request, RequestedService $requestedService)
    {
     
        if ($requestedService->is_paid || $requestedService->done) {
             return response()->json(['message' => 'لا يمكن تعديل خدمة مدفوعة أو مكتملة.'], 403);
        }

        $validated = $request->validate([
            'count' => 'sometimes|integer|min:1',
            'discount_per' => 'sometimes|integer|min:0|max:100',
            'discount' => 'sometimes|numeric|min:0', // Fixed discount
            // Add other editable fields like 'doctor_note', 'nurse_note'
        ]);

        // Logic to ensure only one type of discount is primarily active or they are combined correctly
        // For example, if discount_per is set, maybe clear fixed discount or vice-versa
        // For now, we update whatever is provided.
        
        $requestedService->update($validated);
        return new RequestedServiceResource($requestedService->load(['service.serviceGroup', 'requestingUser']));
    }
}
