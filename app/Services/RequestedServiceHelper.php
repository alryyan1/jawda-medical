<?php

namespace App\Services;

use App\Models\Service;
use App\Models\Company;
use App\Models\Patient;
use App\Models\DoctorVisit;
use App\Models\RequestedService;

class RequestedServiceHelper
{
    /**
     * Create a requested service from a favorite service with company contract handling.
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

        return $requestedService;
    }
}

