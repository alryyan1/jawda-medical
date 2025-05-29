<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RequestedService;
use App\Models\RequestedServiceDeposit;
use App\Models\Shift; // To get current shift
use Illuminate\Http\Request;
use App\Http\Resources\RequestedServiceDepositResource;
use App\Http\Resources\RequestedServiceResource; // To return updated RequestedService
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequestedServiceDepositController extends Controller
{
    public function store(Request $request, RequestedService $requestedService)
    {
        // Permission check: e.g., can('record service_payment')
        // if (!Auth::user()->can('record service_payment')) {
        //    return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01', // Must pay something
            'is_bank' => 'required|boolean',
        ]);

        // Calculate current balance for the requested service
        $pricePerItem = (float) $requestedService->price;
        $count = (int) $requestedService->count;
        $subTotal = $pricePerItem * $count;
        $discountAmount = (float) $requestedService->discount; // Assuming fixed discount
        if ($requestedService->discount_per > 0) {
            $discountAmount += ($subTotal * ((int) $requestedService->discount_per / 100));
        }
        $netPayable = $subTotal - $discountAmount;
        $currentBalance = $netPayable - (float) $requestedService->amount_paid;
        
        $paymentAmount = (float) $validated['amount'];

        if ($paymentAmount > $currentBalance) {
            return response()->json(['message' => 'مبلغ الدفعة يتجاوز الرصيد المستحق.', 'balance' => $currentBalance], 422);
        }

        DB::beginTransaction();
        try {
            $deposit = RequestedServiceDeposit::create([
                'requested_service_id' => $requestedService->id,
                'amount' => $paymentAmount,
                'is_bank' => $validated['is_bank'],
                'user_id' => Auth::id(),
                'shift_id' => Shift::latest()->first()->id,
                'is_claimed' => false, // Default for new deposits
            ]);

            // Update amount_paid on RequestedService
            $requestedService->amount_paid = (float) $requestedService->amount_paid + $paymentAmount;
            
            // Check if now fully paid
            // Recalculate balance with the new payment for accuracy
            $newBalance = $netPayable - (float) $requestedService->amount_paid;
            if ($newBalance <= 0.009) { // Allowing for small float inaccuracies
                $requestedService->is_paid = true;
                // Optionally, if balance is exactly 0, ensure amount_paid matches net_payable precisely
                if (abs($newBalance) < 0.01) {
                    $requestedService->amount_paid = $netPayable;
                }
            }
            $requestedService->save();

            DB::commit();

            // Return the deposit and the updated requested service
            return response()->json([
                'deposit' => new RequestedServiceDepositResource($deposit->load('user')),
                'requested_service' => new RequestedServiceResource($requestedService->load(['service.serviceGroup', 'requestingUser', 'performingDoctor', 'depositUser'])) // Load relations for consistency
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to record payment.', 'error' => $e->getMessage()], 500);
        }
    }
    // You might add an index method to list deposits for a requested_service_id
    // public function index(RequestedService $requestedService) { ... }
}