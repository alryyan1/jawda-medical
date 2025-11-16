<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequestedService;
use App\Models\RequestedServiceDeposit;
use App\Models\Shift; // To get current shift
use Illuminate\Http\Request;
use App\Http\Resources\RequestedServiceDepositResource;
use App\Http\Resources\RequestedServiceResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // Added for logging

class RequestedServiceDepositController extends Controller
{
    public function __construct()
    {
        // Add appropriate permissions here
        // e.g., $this->middleware('can:view service_payments')->only('indexForRequestedService');
        // $this->middleware('can:create service_payments')->only('store');
        // $this->middleware('can:edit service_payments')->only('update');
        // $this->middleware('can:delete service_payments')->only('destroy');
    }

    /**
     * Display a listing of the deposits for a specific requested service.
     */
    public function indexForRequestedService(RequestedService $requestedService) // Route model binding
    {
        // Add authorization if needed: e.g., can user view deposits for this service/visit?
        // $this->authorize('view', $requestedService); 

        $deposits = $requestedService->deposits() // Assuming 'deposits' is the relationship name
            ->with(['user:id,name', 'requestedService']) // Eager load user who made the deposit
            // ->with('requestedService') // Eager load requestedService
            ->orderBy('created_at', 'desc') // Show newest first
            ->get();

        return RequestedServiceDepositResource::collection($deposits);
    }

    public function store(Request $request, RequestedService $requestedService)
    {
        // if(!Auth::user()->can('record visit_service_payment')) {
        //     return response()->json(['message' => 'لا يمكنك تسجيل دفعة للخدمة لأنك ليس لديك صلاحية للقيام بذلك.'], 403);
        // }
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'is_bank' => 'required|boolean',
            // 'shift_id' might be taken from AuthContext on frontend and passed, or determined here
            // For now, assuming frontend might pass it or we take current open shift
        ]);
        if($requestedService->doctorVisit->shift_id != Shift::max('id')) {
            Log::error("Requested service {$requestedService->id} is in a previous shift.",);
            return response()->json(['message' => 'لا يمكن تسجيل دفعة لخدمة في ورديه سابقه  .'], 403);
        }

        $currentClinicShift = Shift::open()->latest('created_at')->first();
        if (!$currentClinicShift) {
            return response()->json(['message' => 'لا توجد وردية عيادة مفتوحة حالياً لتسجيل الدفعة.'], 400);
        }

        // Calculate current balance for the requested service
        $pricePerItem = (float) $requestedService->price;
        $count = (int) ($requestedService->count ?? 1);
        $subTotal = $pricePerItem * $count;

        $discountFromPercentage = ($subTotal * (intval($requestedService->discount_per) || 0)) / 100;
        $fixedDiscount = intval($requestedService->discount) || 0;
        $totalDiscount = $discountFromPercentage + $fixedDiscount;

        if($requestedService->doctorVisit->patient->company_id){
            $netPayable = $requestedService->endurance * $count;
        }
        else{
            $netPayable = $subTotal - $totalDiscount;
        }

        log::info('netPayable   ',['netPayable' => $netPayable]);
        // If company patient, subtract endurance that company covers
        $patient = $requestedService->doctorVisit->patient; // Need to load this relationship if not always available
        // if ($patient->company_id) {
        //     $netPayable -= (float) $requestedService->endurance;
        // }

        $currentBalance = $netPayable - (float) $requestedService->amount_paid;
        $paymentAmount = (float) $validated['amount'];
        log::info('currentBalance   ',['currentBalance' => $currentBalance,'paymentAmount' => $paymentAmount]);
        if ($paymentAmount > ($currentBalance + 0.009)) { // Allow for small float inaccuracies
            return response()->json(['message' => 'مبلغ الدفعة يتجاوز الرصيد المستحق للخدمة.', 'balance' => round($currentBalance, 2)], 422);
        }

        DB::beginTransaction();
        try {
            $deposit = $requestedService->deposits()->create([ // Use the relationship to create
                'amount' => $paymentAmount,
                'is_bank' => $validated['is_bank'],
                'user_id' => Auth::id(),
                'shift_id' => $currentClinicShift->id,
                'is_claimed' => false,
            ]);

            // Update amount_paid on RequestedService
            // Use getRawOriginal to get the actual DB value, not the accessor that sums deposits
            $currentAmountPaid = (float) $requestedService->getRawOriginal('amount_paid') ?? 0;
            $newAmountPaid = $currentAmountPaid + $paymentAmount;
            $requestedService->amount_paid = $newAmountPaid;

            // Recalculate balance with the new payment for accuracy
            $newBalance = $netPayable - $newAmountPaid;
            if ($newBalance <= 0.009) {
                $requestedService->is_paid = true;
                $requestedService->user_deposited = Auth::id();
                if (abs($newBalance) < 0.01) { // If very close to zero, set amount_paid to netPayable
                    $requestedService->amount_paid = $netPayable;
                }
            } else {
                $requestedService->is_paid = false; // If payment doesn't cover full balance
            }
            $requestedService->save();

            DB::commit();

            return response()->json([
                'message' => 'تم تسجيل الدفعة بنجاح.', // Added success message
                'deposit' => new RequestedServiceDepositResource($deposit->load('user')),
                'requested_service' => new RequestedServiceResource($requestedService->load(['service.serviceGroup', 'requestingUser', 'performingDoctor', 'depositUser', 'deposits']))
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to record service deposit for RequestedService ID {$requestedService->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل تسجيل الدفعة.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing deposit.
     * (Use with caution - consider business rules for editing financial records)
     */
    public function update(Request $request, RequestedServiceDeposit $requestedServiceDeposit)
    {
        // $this->authorize('update', $requestedServiceDeposit);
        // Typically, you might only allow updating notes or is_claimed, not amount or payment method after creation.
        // For this example, allowing amount and is_bank update.
        // if(!Auth::user()->can('manage service_payments_deposits')) {
        //     return response()->json(['message' => 'لا يمكنك تحديث دفعة للخدمة لأنك ليس لديك صلاحية للقيام بذلك.'], 403);
        // }

        $validated = $request->validate([
            'amount' => 'sometimes|required|numeric|min:0.01',
            'is_bank' => 'sometimes|required|boolean',
            // 'is_claimed' => 'sometimes|required|boolean', // If you want to update this
        ]);

        $requestedService = $requestedServiceDeposit->requestedService;
        $oldDepositAmount = (float) $requestedServiceDeposit->amount;
        $newDepositAmount = isset($validated['amount']) ? (float) $validated['amount'] : $oldDepositAmount;

        // Calculate how this change affects the RequestedService's amount_paid
        // This logic can get complex if there are multiple deposits.
        // Simplification: Assume we are adjusting this one deposit.
        $diff = $newDepositAmount - $oldDepositAmount;

        // Recalculate net payable for the service to check against new total paid
        $pricePerItem = (float) $requestedService->price;
        $count = (int) ($requestedService->count ?? 1);
        $subTotal = $pricePerItem * $count;
        $discountFromPercentage = ($subTotal * (intval($requestedService->discount_per) || 0)) / 100;
        $fixedDiscount = intval($requestedService->discount) || 0;
        $totalDiscount = $discountFromPercentage + $fixedDiscount;
        $netPayable = $subTotal - $totalDiscount;
        if ($requestedService->doctorVisit->patient->company_id) { // Assuming patient relationship is loaded or accessible
            $netPayable -= (float) $requestedService->endurance;
        }

        // Use getRawOriginal to get the actual DB value, not the accessor that sums deposits
        $currentAmountPaid = (float) $requestedService->getRawOriginal('amount_paid') ?? 0;
        $newTotalPaidOnService = $currentAmountPaid + $diff;

        if ($newTotalPaidOnService > ($netPayable + 0.009)) {
            return response()->json(['message' => 'التعديل سيؤدي إلى تجاوز المبلغ المستحق للخدمة.'], 422);
        }

        DB::beginTransaction();
        try {
            $requestedServiceDeposit->update($validated);

            // Update parent RequestedService
            $requestedService->amount_paid = $newTotalPaidOnService;
            $newBalance = $netPayable - $newTotalPaidOnService;
            if ($newBalance <= 0.009) {
                $requestedService->is_paid = true;
                if (abs($newBalance) < 0.01) $requestedService->amount_paid = $netPayable;
            } else {
                $requestedService->is_paid = false;
            }
            $requestedService->save();

            DB::commit();
            return new RequestedServiceDepositResource($requestedServiceDeposit->load('user'));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to update service deposit ID {$requestedServiceDeposit->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل تحديث الدفعة.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Delete a deposit.
     * (Use with extreme caution - financial records should usually be voided/reversed, not deleted)
     */
    public function destroy(RequestedServiceDeposit $requestedServiceDeposit)
    {
        // $this->authorize('delete', $requestedServiceDeposit);
        

        // IMPORTANT: Adjusting the parent RequestedService's amount_paid is critical.
        $requestedService = $requestedServiceDeposit->requestedService;
        $amountBeingReversed = (float) $requestedServiceDeposit->amount;
        

        DB::beginTransaction();
        try {
            // Use getRawOriginal to get the actual DB value, not the accessor that sums deposits
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
            if ($requestedService->doctorVisit->patient->company_id) {
                $netPayable -= (float) $requestedService->endurance;
            }

            if ((float) $requestedService->amount_paid < ($netPayable - 0.009)) {
                $requestedService->is_paid = false;
            }
            // If amount_paid becomes negative due to reversal, it implies an issue, but we'll allow it for now.
            // Ensure it doesn't go below zero if that's a hard rule.
            if ($requestedService->amount_paid < 0) $requestedService->amount_paid = 0;

            $requestedService->save();
            $requestedServiceDeposit->delete();
            $requestedService->update(['is_paid'=>0]);

            DB::commit();
            return response()->json(null, 204);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete service deposit ID {$requestedServiceDeposit->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل حذف الدفعة.', 'error' => $e->getMessage()], 500);
        }
    }
}
