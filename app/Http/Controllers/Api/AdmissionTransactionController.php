<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionTransaction;
use App\Services\Pdf\AdmissionLedgerReport;
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
            'description' => 'nullable|string|max:255',
            'reference_type' => 'nullable|in:service,deposit,manual,lab_test,room_charges,charge,discount,short_stay',
            'reference_id' => 'nullable|integer',
            'is_bank' => 'boolean',
            'notes' => 'nullable|string',
        ]);

        // Set default description based on reference_type if not provided
        if (empty($validatedData['description'])) {
            $defaultDescriptions = [
                'charge' => 'رسوم إضافية',
                'discount' => 'خصم',
                'deposit' => 'دفعة',
            ];
            $validatedData['description'] = $defaultDescriptions[$validatedData['reference_type']] ?? 'معاملة';
        }

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
        // Separate discounts from other credits
        $totalDiscounts = (float) $transactions->where('type', 'credit')
            ->where('reference_type', 'discount')
            ->sum('amount');
        $totalCredits = (float) $transactions->where('type', 'credit')
            ->where('reference_type', '!=', 'discount')
            ->sum('amount');
        $totalDebits = (float) $transactions->where('type', 'debit')->sum('amount');
        // Balance = المستحقات - المدفوعات - التخفيضات
        $balance = $totalDebits - $totalCredits - $totalDiscounts;

        // Build ledger entries
        $entries = [];
        $runningBalance = 0;

        foreach ($transactions as $transaction) {
            if ($transaction->type === 'debit') {
                $runningBalance += (float) $transaction->amount;  // الرسوم تزيد الرصيد المطلوب
            } else {
                $runningBalance -= (float) $transaction->amount;  // الدفعات تقلل الرصيد المطلوب
            }

            $entries[] = [
                'id' => $transaction->id,
                'type' => $transaction->type,
                'description' => $transaction->description,
                'amount' => $transaction->type === 'debit' ? (float) $transaction->amount : -(float) $transaction->amount,
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
                'total_discounts' => $totalDiscounts,
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
        $balance = $totalDebits - $totalCredits;  // المستحقات - المدفوعات
        
        return response()->json([
            'balance' => $balance,
            'total_credits' => $totalCredits,
            'total_debits' => $totalDebits,
        ]);
    }

    /**
     * Remove the specified transaction.
     */
    public function destroy(Admission $admission, AdmissionTransaction $transaction)
    {
        // Verify transaction belongs to admission
        if ($transaction->admission_id !== $admission->id) {
            return response()->json(['message' => 'المعاملة لا تنتمي لهذا التنويم.'], 400);
        }

        // Check if admission is still active (can only delete transactions for active admissions)
        if ($admission->status !== 'admitted') {
            return response()->json(['message' => 'لا يمكن حذف معاملة للمريض غير المقيم.'], 400);
        }

        try {
            $transaction->delete();
            
            return response()->json(['message' => 'تم حذف المعاملة بنجاح.'], 200);
        } catch (\Exception $e) {
            return response()->json(['message' => 'فشل حذف المعاملة: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Export the ledger as PDF.
     */
    public function exportLedgerPdf(Admission $admission)
    {
        try {
            // Get ledger data using the same logic as ledger() method
            $transactions = $admission->transactions()
                ->with('user')
                ->orderBy('created_at', 'asc')
                ->get();

            // Calculate totals
            $totalDiscounts = (float) $transactions->where('type', 'credit')
                ->where('reference_type', 'discount')
                ->sum('amount');
            $totalCredits = (float) $transactions->where('type', 'credit')
                ->where('reference_type', '!=', 'discount')
                ->sum('amount');
            $totalDebits = (float) $transactions->where('type', 'debit')->sum('amount');
            $balance = $totalDebits - $totalCredits - $totalDiscounts;

            // Build ledger entries
            $entries = [];
            $runningBalance = 0;

            foreach ($transactions as $transaction) {
                if ($transaction->type === 'debit') {
                    $runningBalance += (float) $transaction->amount;
                } else {
                    $runningBalance -= (float) $transaction->amount;
                }

                $entries[] = [
                    'id' => $transaction->id,
                    'type' => $transaction->type,
                    'description' => $transaction->description,
                    'amount' => $transaction->type === 'debit' ? (float) $transaction->amount : -(float) $transaction->amount,
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

            $ledgerData = [
                'admission_id' => $admission->id,
                'patient' => [
                    'id' => $admission->patient->id,
                    'name' => $admission->patient->name,
                ],
                'summary' => [
                    'total_credits' => $totalCredits,
                    'total_debits' => $totalDebits,
                    'total_discounts' => $totalDiscounts,
                    'balance' => $balance,
                ],
                'entries' => $entries,
            ];

            // Generate PDF
            $pdfReport = new AdmissionLedgerReport($admission, $ledgerData);
            $pdfContent = $pdfReport->generate();

            // Return PDF response
            $patientName = $admission->patient->name ?? 'patient';
            $filename = 'ledger_' . $admission->id . '_' . str_replace(' ', '_', $patientName) . '.pdf';

            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="' . $filename . '"')
                ->header('Cache-Control', 'private, max-age=0, must-revalidate')
                ->header('Pragma', 'public');
        } catch (\Exception $e) {
            return response()->json(['message' => 'فشل تصدير التقرير: ' . $e->getMessage()], 500);
        }
    }
}


