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

class VisitServiceController extends Controller
{
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

    public function addRequestedServices(Request $request, DoctorVisit $visit)
    {
        $validated = $request->validate([
            'service_ids' => 'required|array',
            'service_ids.*' => 'required|integer|exists:services,id',
            // 'quantities' => 'nullable|array', // If services can have quantities
            // 'quantities.*' => 'integer|min:1',
        ]);

        $patient = $visit->patient;
        $company = $patient->company; // Get patient's insurance company

        $createdRequestedServices = [];

        foreach ($validated['service_ids'] as $serviceId) {
            $service = Service::find($serviceId);
            if (!$service) continue;

            // Determine price: Contract price or default service price
            $price = $service->price; // Default
            $endurance = 0; // Default company endurance for this service for this visit

            if ($company) {
                $contract = $company->contractedServices()->where('services.id', $serviceId)->first();
                if ($contract && $contract->pivot->approval) { // Check if contract exists and is approved
                    $price = $contract->pivot->price;
                    // Calculate endurance based on use_static or percentage
                    if ($contract->pivot->use_static) {
                        $endurance = $contract->pivot->static_endurance;
                    } else {
                        $endurance = ($price * $contract->pivot->percentage_endurance) / 100;
                    }
                     // Check company's service roof/lab roof if applicable
                }
            }
            
            // TODO: Check patient's company endurance limits if applicable

            $requestedService = RequestedService::create([
                'doctorvisits_id' => $visit->id, // Check your FK column name
                'service_id' => $serviceId,
                'user_id' => Auth::id(), // User who added the service
                'doctor_id' => $visit->doctor_id, // Doctor of the visit
                'price' => $price,
                'amount_paid' => 0, // Initially unpaid
                'endurance' => $endurance,
                'is_paid' => false,
                'discount' => 0, // Default discount
                'discount_per' => 0,
                'bank' => false, // Default payment method
                'count' => 1, // Default quantity
                'approval' => true, // Default to approved, or implement an approval flow
                'done' => false, // Service not yet performed
            ]);
            $createdRequestedServices[] = $requestedService->load('service.serviceGroup');
        }

        return RequestedServiceResource::collection($createdRequestedServices);
    }

    public function getRequestedServices(DoctorVisit $visit)
    {
        $requested = $visit->requestedServices()->with('service.serviceGroup', 'userDeposited')->orderBy('created_at')->get();
        return RequestedServiceResource::collection($requested);
    }

     public function removeRequestedService(DoctorVisit $visit, RequestedService $requestedService)
    {
        // Ensure the requestedService belongs to the visit for security
        if ($requestedService->doctorvisits_id !== $visit->id) {
            return response()->json(['message' => 'Service not found for this visit.'], 404);
        }
        // Add checks: e.g., cannot delete if already paid or performed, unless specific permissions
        if ($requestedService->is_paid || $requestedService->done) {
             return response()->json(['message' => 'لا يمكن حذف خدمة مدفوعة أو مكتملة.'], 403);
        }

        $requestedService->delete();
        return response()->json(null, 204);
    }
}