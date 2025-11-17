<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorVisit;
use App\Models\Service;
use App\Models\RequestedService;
use App\Models\Company;
use App\Models\ServiceCost; // For accessing predefined costs
use App\Models\RequestedServiceCost; // For creating cost breakdown entries
use App\Http\Resources\ServiceResource;
use App\Http\Resources\RequestedServiceResource;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VisitServiceController extends Controller
{
    public function __construct()
    {
        // Example permissions
        // $this->middleware('can:view requested_services')->only(['getAvailableServices', 'getRequestedServices']);
        // $this->middleware('can:manage requested_services')->only(['addRequestedServices', 'updateRequestedService', 'removeRequestedService']);
    }

    /**
     * Create a standardized error response with file and line information
     */
    private function createErrorResponse(\Exception $e, string $message, int $statusCode = 500)
    {
        $errorContext = [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ];

        Log::error("VisitServiceController Error: " . $e->getMessage(), [
            'exception' => $e,
            'context' => $errorContext
        ]);

        return response()->json([
            'message' => $message,
            'error' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
            'context' => $errorContext
        ], $statusCode);
    }

    /**
     * Central guard to prevent add/delete/update on requested services when conditions are met.
     * Returns a JSON response on failure, or null if operation is allowed.
     */
    private function guardRequestedServiceMutation(DoctorVisit $visit, ?RequestedService $requestedService = null)
    {
        // Block any mutation on visits that are not in the latest shift
        if ($visit->shift_id != Shift::max('id')) {
            return response()->json(['message' => 'لا يمكن إجراء تعديلات على خدمات زيارة في وردية سابقة.'], 403);
        }

        // If a specific requested service is provided, ensure it belongs to the visit
        if ($requestedService && $requestedService->doctorvisits_id !== $visit->id) {
            return response()->json(['message' => 'Service not found for this visit.'], 404);
        }

        // If a specific requested service is provided, ensure it's not paid or done
        // if ($requestedService && ($requestedService->is_paid || $requestedService->done)) {
        //     return response()->json(['message' => 'لا يمكن تعديل/حذف خدمة مدفوعة أو مكتملة.'], 403);
        // }

        return null;
    }

    public function getAvailableServices(DoctorVisit $visit)
    {
        $requestedServiceIds = $visit->requestedServices()->pluck('service_id')->toArray();
        $availableServices = Service::where('activate', true)
            ->whereNotIn('id', $requestedServiceIds)
            ->with('serviceGroup')
            ->orderBy('name')
            ->get();
        return ServiceResource::collection($availableServices);
    }

    public function getRequestedServices(DoctorVisit $visit)
    {
        $requested = $visit->requestedServices()
            ->with([
                'service.serviceGroup',
                'service.serviceCosts.subServiceCost', // Eager load service costs for potential display
                'requestingUser:id,name',
                'depositUser:id,name',
                'performingDoctor:id,name',
                'costBreakdown.subServiceCost', // Eager load actual cost breakdown
            ])
            ->orderBy('created_at', 'desc')
            ->get();
        return RequestedServiceResource::collection($requested);
    }

    public function addRequestedServices(Request $request, DoctorVisit $visit)
    {
        if ($resp = $this->guardRequestedServiceMutation($visit)) {
            return $resp;
        }
        // if(!Auth::user()->can('request visit_services')) {
        //     return response()->json(['message' => 'لا يمكنك إضافة خدمات للزيارة لأنك ليس لديك صلاحية للقيام بذلك.'], 403);
        // }
        $validated = $request->validate([
            'service_ids' => 'required|array',
            'service_ids.*' => 'required|integer|exists:services,id',
            'quantities' => 'nullable|array', // Optional: if frontend sends quantities for each service
            'quantities.*' => 'required_with:quantities|integer|min:1',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
        ]);

        $patient = $visit->patient()->firstOrFail();
        $company = $patient->company_id ? Company::find($patient->company_id) : null;

        $createdItems = [];
        DB::beginTransaction();
        try {
            foreach ($validated['service_ids'] as $index => $serviceId) {
                $service = Service::with('serviceCosts.subServiceCost')->find($serviceId); // Eager load predefined costs
                if (!$service) {
                    Log::warning("Service ID {$serviceId} not found during visit service request.");
                    continue;
                }
                if ($company == null) {
                    if ($service->price == 0) {
                        return response()->json(['message' => 'لا يمكنك إضافة خدمة بسعر 0.'], 403);
                    }
                }

                // Initialize default values
                $price = (float) $service->price; // Default to service price
                $companyEnduranceAmount = 0;
                $contractApproval = false;

                if ($company) {
                    $contract = $company->contractedServices()
                        ->where('services.id', $serviceId)
                        ->first();
                    // return $contract;
                    if ($contract && $contract->pivot) { // Ensure pivot exists
                        $contractPivot = $contract->pivot;
                        if ($contractPivot->price == 0) {
                            return response()->json(['message' => 'لا يمكنك إضافة خدمة  غير مسعره في العقد  .'], 403);
                        }
                        // return $contractPivot;
                        // if ($contractPivot->status) { // Assuming company_service pivot has 'status' for active contract item
                        // return $contractPivot;
                        $price = (float) $contractPivot->price;
                        if ($contractPivot->price == 0) {
                            return response()->json(['message' => 'لا يمكنك إضافة خدمة  غير مسعره في العقد  .'], 403);
                        }
                        $contractApproval = (bool) $contractPivot->approval;
                        if ($contractPivot->use_static) {
                            $companyEnduranceAmount = (float) $contractPivot->static_endurance;
                        } else {
                            if ($contractPivot->percentage_endurance > 0) {
                                $companyServiceEndurance = ($price * (float) ($contractPivot->percentage_endurance ?? 0)) / 100;
                                $companyEnduranceAmount = $price - $companyServiceEndurance;
                            } else {
                                // return $company;
                                //compnay relation
                                if($patient->companyRelation != null){
                                    $companyRelation = $patient->companyRelation;
                                    $companyServiceEndurance = ($price * (float) ($companyRelation->service_endurance ?? 0)) / 100;
                                    $companyEnduranceAmount = $price - $companyServiceEndurance;
                                }else{

                                    $companyServiceEndurance = ($price * (float) ($company->service_endurance ?? 0)) / 100;
                                    $companyEnduranceAmount = $price - $companyServiceEndurance;
                                }
                            }
                            //then use $company->service_endurance which is percentage



                        }
                        // }
                    }
                }

                $count = $request->input("quantities.{$serviceId}", $request->input("quantities.{$index}", 1)); // Try by service_id key or by index

                $requestedService = RequestedService::create([
                    'doctorvisits_id' => $visit->id,
                    'service_id' => $serviceId,
                    'user_id' => Auth::id(),
                    'doctor_id' => $visit->patient->doctor_id,
                    'price' => $price,
                    'amount_paid' => 0,
                    'endurance' => $companyEnduranceAmount,
                    'is_paid' => false,
                    'discount' => 0,
                    'discount_per' => 0,
                    'bank' => false,
                    'count' => $count,
                    'approval' => $contractApproval,
                    'done' => false,
                ]);

                // --- START: Auto-create RequestedServiceCost entries ---
                if ($service->serviceCosts->isNotEmpty()) {
                    $costEntriesData = [];
                    $baseAmountForCostCalc = $price * $count; // Initial base is total price before patient discount/endurance

                    foreach ($service->serviceCosts as $serviceCostDefinition) {
                        $calculatedCostAmount = 0;
                        $currentBase = $baseAmountForCostCalc;

                        // If cost_type is 'after cost', we need to subtract previously calculated costs from the base
                        if ($serviceCostDefinition->cost_type === 'after cost') {
                            $alreadyCalculatedCostsSum = collect($costEntriesData)->sum('amount');
                            $currentBase = $baseAmountForCostCalc - $alreadyCalculatedCostsSum;
                        }

                        if ($serviceCostDefinition->fixed !== null && $serviceCostDefinition->fixed > 0) {
                            $calculatedCostAmount = (float) $serviceCostDefinition->fixed;
                        } elseif ($serviceCostDefinition->percentage !== null && $serviceCostDefinition->percentage > 0) {
                            $calculatedCostAmount = ($currentBase * (float) $serviceCostDefinition->percentage) / 100;
                        }

                        if ($calculatedCostAmount > 0) {
                            $costEntriesData[] = [
                                'requested_service_id' => $requestedService->id,
                                'sub_service_cost_id' => $serviceCostDefinition->sub_service_cost_id,
                                'service_cost_id' => $serviceCostDefinition->id,
                                'amount' => round($calculatedCostAmount, 2), // Round to 2 decimal places
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                    if (!empty($costEntriesData)) {
                        RequestedServiceCost::insert($costEntriesData);
                    }
                }
                // --- END: Auto-create RequestedServiceCost entries ---

                $createdItems[] = $requestedService;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->createErrorResponse($e, 'فشل إضافة الخدمات للزيارة.', 500);
        }

        $loadedItems = collect($createdItems)->map(function ($item) {
            return $item->load(['service.serviceGroup', 'service.serviceCosts', 'requestingUser:id,name', 'performingDoctor:id,name', 'costBreakdown']);
        });

        return RequestedServiceResource::collection($loadedItems);
    }

    public function removeRequestedService(DoctorVisit $visit, RequestedService $requestedService)
    {
        // if(!Auth::user()->can('remove visit_services')) {
        //     return response()->json(['message' => 'لا يمكنك حذف خدمة للزيارة لأنك ليس لديك صلاحية للقيام بذلك.'], 403);
        // }
        if ($resp = $this->guardRequestedServiceMutation($visit, $requestedService)) {
            return $resp;
        }

        DB::beginTransaction();
        try {
            if($requestedService->deposits()->count() > 0){
                return response()->json(['message' => 'لا يمكنك حذف الخدمة لأنها مدفوعة.'], 403);
            }
            $requestedService->costBreakdown()->delete(); // Delete associated cost breakdown entries
            $requestedService->deposits()->delete(); // Delete associated deposits entries
            $requestedService->delete();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->createErrorResponse($e, 'فشل حذف الخدمة.', 500);
        }

        return response()->json(null, 204);
    }

    public function updateRequestedService(Request $request, RequestedService $requestedService) // Changed $visit to $requestedService directly
    {
        // Find the visit this requested service belongs to for authorization or context if needed
        $visit = $requestedService->doctorVisit;
        if (!$visit) {
            return response()->json(['message' => 'Visit not found for this service request.'], 404);
        }

        // $this->authorize('update', $requestedService); // Policy for RequestedService

        if ($resp = $this->guardRequestedServiceMutation($visit, $requestedService)) {
            return $resp;
        }

        $validated = $request->validate([
            'count' => 'sometimes|integer|min:1',
            'discount_per' => 'sometimes|integer|min:0|max:100',
            'discount' => 'sometimes|numeric|min:0',
            'endurance' => 'sometimes|numeric|min:0', // Allow updating endurance if rules permit
            // Add other editable fields like 'doctor_note', 'nurse_note', 'approval'
            'approval' => 'sometimes|boolean',
            'doctor_note' => 'nullable|string|max:1000',
            'nurse_note' => 'nullable|string|max:1000',
        ]);

        // Recalculate endurance if count or price changes and it's a company patient
        // This logic can get complex if price is also updatable here.
        // For now, assuming price is fixed from initial add.
        if (array_key_exists('count', $validated) && $visit->patient->company_id) {
            $company = Company::find($visit->patient->company_id);
            $service = $requestedService->service; // Get the base service
            if ($company && $service) {
                $contract = $company->contractedServices()->where('services.id', $service->id)->first();
                if ($contract && $contract->pivot) {
                    $contractPivot = $contract->pivot;
                    $currentPrice = $contractPivot->price; // Price from contract
                    $newCount = $validated['count'];
                    // Log::info('contractPivot', ['contractPivot' => $contractPivot, 'currentPrice' => $currentPrice, 'newCount' => $newCount]);
                    if ($contractPivot->use_static) {
                        // Static endurance usually isn't per count, but per service type per visit.
                        // If it should scale with count, adjust here. For now, assume it doesn't.
                        // $validated['endurance'] = (float) $contractPivot->static_endurance;
                    } else {
                        // Percentage endurance IS per item, so it scales with count * price
                        $validated['endurance'] = $validated['endurance'] * $newCount;
                        // Log::info('contractPivot', ['contractPivot' => $contractPivot, 'currentPrice' => $currentPrice, 'newCount' => $newCount]);
                    }
                }
            }
        }


        $requestedService->update($validated);
        return new RequestedServiceResource($requestedService->load(['service.serviceGroup', 'requestingUser']));
    }
}
