<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Company;
use App\Models\Patient;
use App\Models\DoctorVisit;
use App\Models\RequestedService;
use App\Models\RequestedServiceCost;

class RequestedServiceHelper
{
    /**
     * Create a requested service from a favorite service with company contract handling
     * and automatic cost breakdown creation.
     *
     * @param Service $service The service to create a request for
     * @param DoctorVisit $doctorVisit The doctor visit to attach the service to
     * @param Patient $patient The patient for company contract calculations
     * @param int $userId The user ID creating the request
     * @param int $count The count/quantity of the service (default: 1)
     * @return RequestedService|null The created RequestedService or null on failure
     */
    public static function createFromFavoriteService(
        Service $service,
        DoctorVisit $doctorVisit,
        Patient $patient,
        int $userId,
        int $count = 1
    ): ?RequestedService {
        // Load service with costs relationship
        $service->load('serviceCosts.subServiceCost');

        // Get company if patient has one
        $company = $patient->company_id ? Company::find($patient->company_id) : null;

        // Initialize pricing variables
        $price = (float) $service->price;
        $companyEnduranceAmount = 0;
        $contractApproval = true;

        // Handle company contract pricing and endurance
        if ($company) {
            $contract = $company->contractedServices()
                ->where('services.id', $service->id)
                ->first();

            if ($contract && $contract->pivot) {
                $pivot = $contract->pivot;
                $price = (float) $pivot->price;
                $contractApproval = (bool) $pivot->approval;

                // Calculate company endurance amount
                if ($pivot->use_static) {
                    $companyEnduranceAmount = (float) $pivot->static_endurance;
                } else {
                    if ($pivot->percentage_endurance > 0) {
                        $companyServiceEndurance = ($price * (float) ($pivot->percentage_endurance ?? 0)) / 100;
                        $companyEnduranceAmount = $price - $companyServiceEndurance;
                    } else {
                        $companyServiceEndurance = ($price * (float) ($company->service_endurance ?? 0)) / 100;
                        $companyEnduranceAmount = $price - $companyServiceEndurance;
                    }
                }
            }
        }

        // Create the RequestedService
        $requestedService = RequestedService::create([
            'doctorvisits_id' => $doctorVisit->id,
            'service_id' => $service->id,
            'user_id' => $userId,
            'doctor_id' => $patient->doctor_id,
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

        // Auto-create RequestedServiceCost breakdowns
        self::createServiceCostBreakdowns($requestedService, $service, $price, $count);

        return $requestedService;
    }

    /**
     * Create cost breakdown entries for a requested service.
     *
     * @param RequestedService $requestedService The requested service to attach costs to
     * @param Service $service The service with cost definitions
     * @param float $price The base price for calculations
     * @param int $count The count/quantity multiplier
     * @return void
     */
    public static function createServiceCostBreakdowns(
        RequestedService $requestedService,
        Service $service,
        float $price,
        int $count = 1
    ): void {
        if ($service->serviceCosts->isEmpty()) {
            return;
        }

        $costEntriesData = [];
        $baseAmountForCostCalc = $price * $count;

        foreach ($service->serviceCosts as $serviceCostDefinition) {
            $calculatedCostAmount = 0;
            $currentBase = $baseAmountForCostCalc;

            // Handle "after cost" type - subtract already calculated costs
            if ($serviceCostDefinition->cost_type === 'after cost') {
                $alreadyCalculatedCostsSum = collect($costEntriesData)->sum('amount');
                $currentBase = $baseAmountForCostCalc - $alreadyCalculatedCostsSum;
            }

            // Calculate cost amount based on fixed or percentage
            if ($serviceCostDefinition->fixed !== null && $serviceCostDefinition->fixed > 0) {
                $calculatedCostAmount = (float) $serviceCostDefinition->fixed;
            } elseif ($serviceCostDefinition->percentage !== null && $serviceCostDefinition->percentage > 0) {
                $calculatedCostAmount = ($currentBase * (float) $serviceCostDefinition->percentage) / 100;
            }

            // Add to cost entries if amount is greater than 0
            if ($calculatedCostAmount > 0) {
                $costEntriesData[] = [
                    'requested_service_id' => $requestedService->id,
                    'sub_service_cost_id' => $serviceCostDefinition->sub_service_cost_id,
                    'service_cost_id' => $serviceCostDefinition->id,
                    'amount' => round($calculatedCostAmount, 2),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        // Bulk insert cost entries if any were created
        if (!empty($costEntriesData)) {
            RequestedServiceCost::insert($costEntriesData);
        }
    }
}

