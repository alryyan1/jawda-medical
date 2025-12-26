<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionDeposit;
use App\Models\AdmissionTransaction;
use Illuminate\Http\Request;
use App\Http\Resources\AdmissionDepositResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdmissionDepositController extends Controller
{
    /**
     * Display a listing of deposits for an admission.
     * This is kept for backward compatibility but now returns credit transactions.
     */
    public function index(Admission $admission)
    {
        $credits = $admission->transactions()
            ->where('type', 'credit')
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
        
        // Convert to deposit-like format for backward compatibility
        $deposits = $credits->map(function ($transaction) {
            return (object) [
                'id' => $transaction->id,
                'amount' => $transaction->amount,
                'is_bank' => $transaction->is_bank,
                'notes' => $transaction->notes,
                'user' => $transaction->user,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
            ];
        });
        
        return AdmissionDepositResource::collection($deposits);
    }

    /**
     * Store a newly created deposit.
     */
    public function store(Request $request, Admission $admission)
    {
        if ($admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن إضافة أمانة للمريض غير المقيم.'], 400);
        }

        $validatedData = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'is_bank' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            // Create credit transaction instead of deposit
            $transaction = $admission->transactions()->create([
                'type' => 'credit',
                'amount' => $validatedData['amount'],
                'description' => 'دفعة',
                'reference_type' => 'deposit',
                'is_bank' => $validatedData['is_bank'] ?? false,
                'notes' => $validatedData['notes'] ?? null,
                'user_id' => Auth::id(),
            ]);

            DB::commit();

            // Return in deposit format for backward compatibility
            $deposit = (object) [
                'id' => $transaction->id,
                'amount' => $transaction->amount,
                'is_bank' => $transaction->is_bank,
                'notes' => $transaction->notes,
                'user' => $transaction->user,
                'created_at' => $transaction->created_at,
                'updated_at' => $transaction->updated_at,
            ];

            return new AdmissionDepositResource($deposit);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشل إضافة الدفعة: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get the ledger (account statement) for an admission.
     * Redirects to AdmissionTransactionController::ledger
     */
    public function ledger(Admission $admission)
    {
        // Redirect to transaction controller for consistency
        $transactionController = new AdmissionTransactionController();
        return $transactionController->ledger($admission);
    }
}

