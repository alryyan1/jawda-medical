<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmissionRequestedService;
use App\Models\AdmissionRequestedServiceDeposit;
use Illuminate\Http\Request;
use App\Http\Resources\AdmissionRequestedServiceDepositResource;
use App\Http\Resources\AdmissionRequestedServiceResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdmissionRequestedServiceDepositController extends Controller
{
    /**
     * Display a listing of the deposits for a specific requested service.
     */
    public function indexForRequestedService(AdmissionRequestedService $requestedService)
    {
        $deposits = $requestedService->deposits()
            ->with(['user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return AdmissionRequestedServiceDepositResource::collection($deposits);
    }

    /**
     * Store a newly created deposit.
     */
    public function store(Request $request, AdmissionRequestedService $requestedService)
    {
        // Check if admission is active
        if ($requestedService->admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن تسجيل دفعة لإقامة غير نشطة.'], 403);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'is_bank' => 'required|boolean',
            'notes' => 'nullable|string',
        ]);

        // Calculate current balance
        $pricePerItem = (float) $requestedService->price;
        $count = (int) ($requestedService->count ?? 1);
        $subTotal = $pricePerItem * $count;

        $discountFromPercentage = ($subTotal * (intval($requestedService->discount_per) || 0)) / 100;
        $fixedDiscount = intval($requestedService->discount) || 0;
        $totalDiscount = $discountFromPercentage + $fixedDiscount;

        $patient = $requestedService->admission->patient;
        if ($patient->company_id) {
            $netPayable = $requestedService->endurance * $count;
        } else {
            $netPayable = $subTotal - $totalDiscount;
        }

        $currentAmountPaid = (float) $requestedService->getRawOriginal('amount_paid') ?? 0;
        $currentBalance = $netPayable - $currentAmountPaid;
        $paymentAmount = (float) $validated['amount'];

        if ($paymentAmount > ($currentBalance + 0.009)) {
            return response()->json([
                'message' => 'مبلغ الدفعة يتجاوز الرصيد المستحق للخدمة.',
                'balance' => round($currentBalance, 2)
            ], 422);
        }

        DB::beginTransaction();
        try {
            $deposit = $requestedService->deposits()->create([
                'amount' => $paymentAmount,
                'is_bank' => $validated['is_bank'],
                'user_id' => Auth::id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            // Update amount_paid on AdmissionRequestedService
            $newAmountPaid = $currentAmountPaid + $paymentAmount;
            $requestedService->amount_paid = $newAmountPaid;

            // Recalculate balance
            $newBalance = $netPayable - $newAmountPaid;
            if ($newBalance <= 0.009) {
                $requestedService->is_paid = true;
                $requestedService->user_deposited = Auth::id();
                if (abs($newBalance) < 0.01) {
                    $requestedService->amount_paid = $netPayable;
                }
            } else {
                $requestedService->is_paid = false;
            }
            $requestedService->save();

            DB::commit();

            return response()->json([
                'message' => 'تم تسجيل الدفعة بنجاح.',
                'deposit' => new AdmissionRequestedServiceDepositResource($deposit->load('user')),
                'requested_service' => new AdmissionRequestedServiceResource(
                    $requestedService->load(['service.serviceGroup', 'requestingUser', 'performingDoctor', 'depositUser', 'deposits'])
                )
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to record deposit for AdmissionRequestedService ID {$requestedService->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل تسجيل الدفعة.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing deposit.
     */
    public function update(Request $request, AdmissionRequestedServiceDeposit $deposit)
    {
        $requestedService = $deposit->admissionRequestedService;
        
        // Check if admission is active
        if ($requestedService->admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن تعديل دفعة لإقامة غير نشطة.'], 403);
        }

        $validated = $request->validate([
            'amount' => 'sometimes|required|numeric|min:0.01',
            'is_bank' => 'sometimes|required|boolean',
            'notes' => 'sometimes|nullable|string',
        ]);

        $oldDepositAmount = (float) $deposit->amount;
        $newDepositAmount = isset($validated['amount']) ? (float) $validated['amount'] : $oldDepositAmount;
        $diff = $newDepositAmount - $oldDepositAmount;

        // Recalculate net payable
        $pricePerItem = (float) $requestedService->price;
        $count = (int) ($requestedService->count ?? 1);
        $subTotal = $pricePerItem * $count;
        $discountFromPercentage = ($subTotal * (intval($requestedService->discount_per) || 0)) / 100;
        $fixedDiscount = intval($requestedService->discount) || 0;
        $totalDiscount = $discountFromPercentage + $fixedDiscount;
        $netPayable = $subTotal - $totalDiscount;

        $patient = $requestedService->admission->patient;
        if ($patient->company_id) {
            $netPayable = $requestedService->endurance * $count;
        }

        $currentAmountPaid = (float) $requestedService->getRawOriginal('amount_paid') ?? 0;
        $newTotalPaidOnService = $currentAmountPaid + $diff;

        if ($newTotalPaidOnService > ($netPayable + 0.009)) {
            return response()->json(['message' => 'التعديل سيؤدي إلى تجاوز المبلغ المستحق للخدمة.'], 422);
        }

        DB::beginTransaction();
        try {
            $deposit->update($validated);

            // Update parent AdmissionRequestedService
            $requestedService->amount_paid = $newTotalPaidOnService;
            $newBalance = $netPayable - $newTotalPaidOnService;
            if ($newBalance <= 0.009) {
                $requestedService->is_paid = true;
                if (abs($newBalance) < 0.01) {
                    $requestedService->amount_paid = $netPayable;
                }
            } else {
                $requestedService->is_paid = false;
            }
            $requestedService->save();

            DB::commit();
            return new AdmissionRequestedServiceDepositResource($deposit->load('user'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update deposit ID {$deposit->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل تحديث الدفعة.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a deposit.
     */
    public function destroy(AdmissionRequestedServiceDeposit $deposit)
    {
        $requestedService = $deposit->admissionRequestedService;
        
        // Check if admission is active
        if ($requestedService->admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن حذف دفعة لإقامة غير نشطة.'], 403);
        }

        $amountBeingReversed = (float) $deposit->amount;

        DB::beginTransaction();
        try {
            $currentAmountPaid = (float) $requestedService->getRawOriginal('amount_paid') ?? 0;
            $requestedService->amount_paid = $currentAmountPaid - $amountBeingReversed;

            // Recalculate is_paid status
            $pricePerItem = (float) $requestedService->price;
            $count = (int) ($requestedService->count ?? 1);
            $subTotal = $pricePerItem * $count;
            $discountFromPercentage = ($subTotal * (intval($requestedService->discount_per) || 0)) / 100;
            $fixedDiscount = intval($requestedService->discount) || 0;
            $totalDiscount = $discountFromPercentage + $fixedDiscount;
            $netPayable = $subTotal - $totalDiscount;

            $patient = $requestedService->admission->patient;
            if ($patient->company_id) {
                $netPayable = $requestedService->endurance * $count;
            }

            if ((float) $requestedService->amount_paid < ($netPayable - 0.009)) {
                $requestedService->is_paid = false;
            }

            if ($requestedService->amount_paid < 0) {
                $requestedService->amount_paid = 0;
            }

            $requestedService->save();
            $deposit->delete();

            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete deposit ID {$deposit->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل حذف الدفعة.', 'error' => $e->getMessage()], 500);
        }
    }
}
