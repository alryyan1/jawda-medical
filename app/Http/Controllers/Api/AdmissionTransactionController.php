<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionTransaction;
use Illuminate\Http\Request;
use App\Http\Resources\AdmissionTransactionResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdmissionTransactionController extends Controller
{
    /**
     * Display a listing of transactions for an admission.
     */
    public function index(Admission $admission)
    {
        $transactions = $admission->transactions()
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();
        
        return AdmissionTransactionResource::collection($transactions);
    }

    /**
     * Store a newly created transaction.
     */
    public function store(Request $request, Admission $admission)
    {
        if ($admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن إضافة معاملة للمريض غير المقيم.'], 400);
        }

        $validatedData = $request->validate([
            'type' => 'required|in:debit,credit',
            'amount' => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'reference_type' => 'nullable|in:service,deposit,manual',
            'reference_id' => 'nullable|integer',
            'is_bank' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        try {
            DB::beginTransaction();

            $transaction = $admission->transactions()->create([
                'type' => $validatedData['type'],
                'amount' => $validatedData['amount'],
                'description' => $validatedData['description'],
                'reference_type' => $validatedData['reference_type'] ?? null,
                'reference_id' => $validatedData['reference_id'] ?? null,
                'is_bank' => $validatedData['is_bank'] ?? false,
                'notes' => $validatedData['notes'] ?? null,
                'user_id' => Auth::id(),
            ]);

            DB::commit();

            return new AdmissionTransactionResource($transaction->load('user'));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'فشل إضافة المعاملة: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get the ledger (account statement) for an admission.
     */
    public function ledger(Admission $admission)
    {
        // Get all transactions
        $transactions = $admission->transactions()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();

        // Calculate totals
        $totalCredits = (float) $transactions->where('type', 'credit')->sum('amount');
        $totalDebits = (float) $transactions->where('type', 'debit')->sum('amount');
        $balance = $totalCredits - $totalDebits;

        // Build ledger entries
        $entries = [];
        $runningBalance = 0;

        foreach ($transactions as $transaction) {
            if ($transaction->type === 'credit') {
                $runningBalance += (float) $transaction->amount;
            } else {
                $runningBalance -= (float) $transaction->amount;
            }

            $entries[] = [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'description' => $transaction->description,
                'amount' => $transaction->type === 'credit' ? (float) $transaction->amount : -(float) $transaction->amount,
                'is_bank' => $transaction->is_bank,
                'date' => $transaction->created_at->toDateString(),
                'time' => $transaction->created_at->format('H:i:s'),
                'user' => $transaction->user?->name,
                'notes' => $transaction->notes,
                'reference_type' => $transaction->reference_type,
                'reference_id' => $transaction->reference_id,
                'balance_after' => $runningBalance,
            ];
        }

        return response()->json([
            'admission_id' => $admission->id,
            'patient' => [
                'id' => $admission->patient->id,
                'name' => $admission->patient->name,
            ],
            'summary' => [
                'total_credits' => $totalCredits,
                'total_debits' => $totalDebits,
                'balance' => $balance,
            ],
            'entries' => $entries,
        ]);
    }

    /**
     * Get the balance for an admission.
     */
    public function balance(Admission $admission)
    {
        $totalCredits = (float) $admission->transactions()->where('type', 'credit')->sum('amount');
        $totalDebits = (float) $admission->transactions()->where('type', 'debit')->sum('amount');
        $balance = $totalCredits - $totalDebits;
        
        return response()->json([
            'balance' => $balance,
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
        ]);
    }
}


