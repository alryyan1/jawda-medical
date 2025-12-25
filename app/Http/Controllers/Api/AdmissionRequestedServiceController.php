<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionRequestedService;
use App\Models\Service;
use App\Models\Company;
use App\Models\ServiceCost;
use App\Models\AdmissionRequestedServiceCost;
use App\Http\Resources\AdmissionRequestedServiceResource;
use App\Http\Resources\AdmissionRequestedServiceCostResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdmissionRequestedServiceController extends Controller
{
    /**
     * Get all requested services for an admission.
     */
    public function index(Admission $admission)
    {
        $requested = $admission->requestedServices()
            ->with([
                'service.serviceGroup',
                'service.serviceCosts.subServiceCost',
                'requestingUser:id,name',
                'depositUser:id,name',
                'performingDoctor:id,name',
                'requestedServiceCosts.subServiceCost',
            ])
            ->orderBy('created_at', 'desc')
            ->get();
        
        return AdmissionRequestedServiceResource::collection($requested);
    }

    /**
     * Add services to an admission.
     */
    public function store(Request $request, Admission $admission)
    {
        // Check if admission is active
        if ($admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن إضافة خدمات لإقامة غير نشطة.'], 403);
        }

        $validated = $request->validate([
            'service_ids' => 'required|array',
            'service_ids.*' => 'required|integer|exists:services,id',
            'quantities' => 'nullable|array',
            'quantities.*' => 'required_with:quantities|integer|min:1',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
        ]);

        $patient = $admission->patient;
        $company = $patient->company_id ? Company::find($patient->company_id) : null;

        $createdItems = [];
        DB::beginTransaction();
        try {
            foreach ($validated['service_ids'] as $index => $serviceId) {
                $service = Service::with('serviceCosts.subServiceCost')->find($serviceId);
                if (!$service) {
                    Log::warning("Service ID {$serviceId} not found during admission service request.");
                    continue;
                }
                
                if ($company == null) {
                    if ($service->price == 0) {
                        return response()->json(['message' => 'لا يمكنك إضافة خدمة بسعر 0.'], 403);
                    }
                }

                // Initialize default values
                $price = (float) $service->price;
                $companyEnduranceAmount = 0;
                $contractApproval = false;

                if ($company) {
                    $contract = $company->contractedServices()
                        ->where('services.id', $serviceId)
                        ->first();
                    
                    if ($contract && $contract->pivot) {
                        $contractPivot = $contract->pivot;
                        if ($contractPivot->price == 0) {
                            return response()->json(['message' => 'لا يمكنك إضافة خدمة غير مسعرة في العقد.'], 403);
                        }
                        
                        $price = (float) $contractPivot->price;
                        $contractApproval = (bool) $contractPivot->approval;
                        
                        if ($contractPivot->use_static) {
                            $companyEnduranceAmount = (float) $contractPivot->static_endurance;
                        } else {
                            if ($contractPivot->percentage_endurance > 0) {
                                $companyServiceEndurance = ($price * (float) ($contractPivot->percentage_endurance ?? 0)) / 100;
                                $companyEnduranceAmount = $price - $companyServiceEndurance;
                            } else {
                                if ($patient->companyRelation != null) {
                                    $companyRelation = $patient->companyRelation;
                                    $companyServiceEndurance = ($price * (float) ($companyRelation->service_endurance ?? 0)) / 100;
                                    $companyEnduranceAmount = $price - $companyServiceEndurance;
                                } else {
                                    $companyServiceEndurance = ($price * (float) ($company->service_endurance ?? 0)) / 100;
                                    $companyEnduranceAmount = $price - $companyServiceEndurance;
                                }
                            }
                        }
                    }
                }

                $count = $request->input("quantities.{$serviceId}", $request->input("quantities.{$index}", 1));
                $doctorId = $validated['doctor_id'] ?? $admission->doctor_id;

                $requestedService = AdmissionRequestedService::create([
                    'admission_id' => $admission->id,
                    'service_id' => $serviceId,
                    'user_id' => Auth::id(),
                    'doctor_id' => $doctorId,
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

                // Auto-create cost breakdown entries
                if ($service->serviceCosts->isNotEmpty()) {
                    $costEntriesData = [];
                    $baseAmountForCostCalc = $price * $count;

                    foreach ($service->serviceCosts as $serviceCostDefinition) {
                        $calculatedCostAmount = 0;
                        $currentBase = $baseAmountForCostCalc;

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
                                'admission_requested_service_id' => $requestedService->id,
                                'sub_service_cost_id' => $serviceCostDefinition->sub_service_cost_id,
                                'service_cost_id' => $serviceCostDefinition->id,
                                'amount' => round($calculatedCostAmount, 2),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }
                    }
                    
                    if (!empty($costEntriesData)) {
                        AdmissionRequestedServiceCost::insert($costEntriesData);
                    }
                }

                $createdItems[] = new AdmissionRequestedServiceResource(
                    $requestedService->load(['service.serviceGroup', 'requestingUser', 'performingDoctor'])
                );
            }

            DB::commit();
            return response()->json([
                'message' => 'تم إضافة الخدمات بنجاح.',
                'services' => $createdItems
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to add services to admission {$admission->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل إضافة الخدمات.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update a requested service.
     */
    public function update(Request $request, AdmissionRequestedService $requestedService)
    {
        // Check if admission is active
        if ($requestedService->admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن تعديل خدمات لإقامة غير نشطة.'], 403);
        }

        $validated = $request->validate([
            'price' => 'sometimes|required|numeric|min:0',
            'count' => 'sometimes|required|integer|min:1',
            'discount' => 'sometimes|numeric|min:0',
            'discount_per' => 'sometimes|integer|min:0|max:100',
            'doctor_id' => 'sometimes|nullable|integer|exists:doctors,id',
            'doctor_note' => 'sometimes|nullable|string',
            'nurse_note' => 'sometimes|nullable|string',
            'done' => 'sometimes|boolean',
            'approval' => 'sometimes|boolean',
        ]);

        DB::beginTransaction();
        try {
            $requestedService->update($validated);

            // If price or count changed, recalculate costs
            if (isset($validated['price']) || isset($validated['count'])) {
                // Delete existing costs and recalculate
                $requestedService->requestedServiceCosts()->delete();
                $requestedService->addRequestedServiceCosts();
            }

            DB::commit();
            return new AdmissionRequestedServiceResource(
                $requestedService->load(['service.serviceGroup', 'requestingUser', 'performingDoctor', 'requestedServiceCosts'])
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update admission requested service {$requestedService->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل تحديث الخدمة.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Remove a requested service.
     */
    public function destroy(Admission $admission, AdmissionRequestedService $requestedService)
    {
        // Check if admission is active
        if ($admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن حذف خدمات لإقامة غير نشطة.'], 403);
        }

        // Verify service belongs to admission
        if ($requestedService->admission_id !== $admission->id) {
            return response()->json(['message' => 'الخدمة لا تنتمي لهذه الإقامة.'], 404);
        }

        // Check if service is paid
        if ($requestedService->is_paid) {
            return response()->json(['message' => 'لا يمكن حذف خدمة مدفوعة.'], 403);
        }

        DB::beginTransaction();
        try {
            // Delete related costs and deposits
            $requestedService->requestedServiceCosts()->delete();
            $requestedService->deposits()->delete();
            $requestedService->delete();

            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete admission requested service {$requestedService->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل حذف الخدمة.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get cost breakdown for a requested service.
     */
    public function getServiceCosts(AdmissionRequestedService $requestedService)
    {
        $costs = $requestedService->requestedServiceCosts()
            ->with(['serviceCost', 'subServiceCost'])
            ->get();

        return AdmissionRequestedServiceCostResource::collection($costs);
    }

    /**
     * Add or update cost breakdown for a requested service.
     */
    public function addServiceCosts(Request $request, AdmissionRequestedService $requestedService)
    {
        $validated = $request->validate([
            'costs' => 'required|array',
            'costs.*.service_cost_id' => 'required|integer|exists:service_cost,id',
            'costs.*.sub_service_cost_id' => 'nullable|integer|exists:sub_service_costs,id',
            'costs.*.amount' => 'required|numeric|min:0',
        ]);

        DB::beginTransaction();
        try {
            // Delete existing costs
            $requestedService->requestedServiceCosts()->delete();

            // Create new costs
            foreach ($validated['costs'] as $costData) {
                AdmissionRequestedServiceCost::create([
                    'admission_requested_service_id' => $requestedService->id,
                    'service_cost_id' => $costData['service_cost_id'],
                    'sub_service_cost_id' => $costData['sub_service_cost_id'] ?? null,
                    'amount' => $costData['amount'],
                ]);
            }

            DB::commit();
            return AdmissionRequestedServiceCostResource::collection(
                $requestedService->requestedServiceCosts()->with(['serviceCost', 'subServiceCost'])->get()
            );
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update costs for admission requested service {$requestedService->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل تحديث المصروفات.', 'error' => $e->getMessage()], 500);
        }
    }
}
