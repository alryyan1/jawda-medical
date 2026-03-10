<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\RequestedSurgery;
use App\Models\RequestedSurgeryFinance;
use App\Models\SurgicalOperation;
use App\Models\RequestedSurgeryTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequestedSurgeryController extends Controller
{
    /**
     * List requested surgeries by date (created_at). Query: ?date=Y-m-d (default: today).
     */
    public function indexByDate(Request $request)
    {
        $date = $request->get('date', now()->toDateString());
        $surgeries = RequestedSurgery::whereDate('created_at', $date)
            ->with([
                'surgery',
                'admission.patient',
            ])
            ->orderBy('created_at')
            ->get();

        return response()->json($surgeries);
    }

    public function index(Admission $admission)
    {
        $surgeries = $admission->requestedSurgeries()
            ->with([
                'surgery',
                'doctor',
                'user',
                'approvedBy',
                'finances.financeCharge',
                'admission.patient',
            ])
            ->get();

        return response()->json($surgeries);
    }

    public function store(Request $request, Admission $admission)
    {
        $validated = $request->validate([
            'surgery_id'     => 'required|exists:surgical_operations,id',
            'doctor_id'      => 'nullable|exists:doctors,id',
            'initial_price'  => 'nullable|numeric|min:0',
        ]);

        /** @var SurgicalOperation $surgicalOperation */
        $surgicalOperation = SurgicalOperation::with('charges')->findOrFail($validated['surgery_id']);

        DB::beginTransaction();
        try {
            // 1. Create the requested surgery record
            $requestedSurgery = RequestedSurgery::create([
                'admission_id'  => $admission->id,
                'surgery_id'    => $surgicalOperation->id,
                'initial_price' => isset($validated['initial_price']) ? (float) $validated['initial_price'] : null,
                'doctor_id'     => $validated['doctor_id'] ?? null,
                'user_id'       => Auth::id(),
            ]);

            // 2. Resolve and insert finance charges
            $charges = $surgicalOperation->charges;

            // Build a map of charge_id -> calculated amount so percentage-of-charge works
            $calculatedAmounts = [];

            // First pass: Process fixed charges
            // Note: Since we dropped base price, we don't have 'percentage of total' here 
            // unless we define what 'total' means in this context (usually 0 or ignored for base)
            foreach ($charges as $charge) {
                if ($charge->type === 'fixed') {
                    $calculatedAmounts[$charge->id] = $charge->amount;
                } else {
                    $calculatedAmounts[$charge->id] = 0; // Default for percentage based until pass 2
                }
            }

            // Second pass: percentage-of-charge (depends on another charge amount)
            foreach ($charges as $charge) {
                if ($charge->type === 'percentage' && $charge->reference_type === 'charge') {
                    $refId = $charge->reference_charge_id;
                    $refAmount = $calculatedAmounts[$refId] ?? 0;
                    $calculatedAmounts[$charge->id] = ($charge->amount / 100) * $refAmount;
                }
            }

            // Insert all finance records
            foreach ($charges as $charge) {
                RequestedSurgeryFinance::create([
                    'requested_surgery_id' => $requestedSurgery->id,
                    'admission_id'         => $admission->id,
                    'surgery_id'           => $surgicalOperation->id,
                    'finance_charge_id'    => $charge->id,
                    'amount'               => $calculatedAmounts[$charge->id] ?? 0,
                ]);
            }

            DB::commit();

            return response()->json(
                $requestedSurgery->load(['surgery', 'doctor', 'user', 'approvedBy', 'finances.financeCharge']),
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ أثناء طلب العملية', 'error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, Admission $admission, RequestedSurgery $requestedSurgery)
    {
        if ($requestedSurgery->admission_id !== $admission->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'initial_price' => 'nullable|numeric|min:0',
        ]);

        $requestedSurgery->update($validated);

        return response()->json(
            $requestedSurgery->load(['surgery', 'doctor', 'user', 'approvedBy', 'finances.financeCharge'])
        );
    }

    public function destroy(Admission $admission, RequestedSurgery $requestedSurgery)
    {
        if ($requestedSurgery->admission_id !== $admission->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $requestedSurgery->delete();
        return response()->json(null, 204);
    }

    public function approve(Admission $admission, RequestedSurgery $requestedSurgery)
    {
        if ($requestedSurgery->admission_id !== $admission->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();
        try {
            $requestedSurgery->update([
                'status'      => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
            ]);

            // Automatically post all finances as debits to the surgery ledger
            $finances = $requestedSurgery->finances()->with('financeCharge')->get();
            foreach ($finances as $finance) {
                RequestedSurgeryTransaction::create([
                    'requested_surgery_id' => $requestedSurgery->id,
                    'type'                 => 'debit',
                    'amount'               => $finance->amount,
                    'description'          => $finance->financeCharge->name,
                    'user_id'              => Auth::id(),
                ]);
            }

            DB::commit();
            return response()->json($requestedSurgery->load(['surgery', 'doctor', 'user', 'approvedBy', 'finances.financeCharge']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ أثناء اعتماد العملية', 'error' => $e->getMessage()], 500);
        }
    }

    public function unapprove(Admission $admission, RequestedSurgery $requestedSurgery)
    {
        if ($requestedSurgery->admission_id !== $admission->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent unapproving if there are any payments (credit transactions)
        $hasPayments = $requestedSurgery->transactions()->where('type', 'credit')->exists();
        if ($hasPayments) {
            return response()->json(['message' => 'لا يمكن التراجع عن الاعتماد لوجود دفعات مسجلة'], 422);
        }

        DB::beginTransaction();
        try {
            $requestedSurgery->update([
                'status'      => 'pending',
                'approved_by' => null,
                'approved_at' => null,
            ]);

            // Delete the default debit transactions (charges) that were created on approval
            $requestedSurgery->transactions()->where('type', 'debit')->delete();

            DB::commit();
            return response()->json($requestedSurgery->load(['surgery', 'doctor', 'user', 'approvedBy', 'finances.financeCharge']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ أثناء التراجع عن الاعتماد', 'error' => $e->getMessage()], 500);
        }
    }

    public function reject(Admission $admission, RequestedSurgery $requestedSurgery)
    {
        if ($requestedSurgery->admission_id !== $admission->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $requestedSurgery->update([
            'status'      => 'rejected',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        return response()->json($requestedSurgery->load(['surgery', 'doctor', 'user', 'approvedBy', 'finances.financeCharge']));
    }

    public function updateFinance(Request $request, RequestedSurgeryFinance $requestedSurgeryFinance)
    {
        $validated = $request->validate([
            'amount' => 'sometimes|numeric|min:0',
            'doctor_id' => 'sometimes|nullable|exists:doctors,id',
            'payment_method' => 'sometimes|in:cash,bankak',
        ]);

        DB::beginTransaction();
        try {
            $requestedSurgeryFinance->update($validated);

            // If amount was updated, recalculate dependent charges
            if (isset($validated['amount'])) {
                // Find all finance records for the same surgery that depend on THIS specific finance_charge_id
                $dependents = RequestedSurgeryFinance::where('requested_surgery_id', $requestedSurgeryFinance->requested_surgery_id)
                    ->whereHas('financeCharge', function ($query) use ($requestedSurgeryFinance) {
                        $query->where('reference_type', 'charge')
                            ->where('reference_charge_id', $requestedSurgeryFinance->finance_charge_id);
                    })
                    ->with('financeCharge')
                    ->get();

                foreach ($dependents as $dependent) {
                    $newAmount = ($dependent->financeCharge->amount / 100) * $requestedSurgeryFinance->amount;
                    $dependent->update(['amount' => $newAmount]);
                }
            }

            DB::commit();
            return response()->json($requestedSurgeryFinance->load(['financeCharge', 'doctor']));
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ أثناء تحديث التكاليف', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroyFinance(RequestedSurgeryFinance $requestedSurgeryFinance)
    {
        // Prevent deleting a finance row that other charges depend on
        $hasDependents = RequestedSurgeryFinance::where('requested_surgery_id', $requestedSurgeryFinance->requested_surgery_id)
            ->whereHas('financeCharge', function ($query) use ($requestedSurgeryFinance) {
                $query->where('reference_type', 'charge')
                    ->where('reference_charge_id', $requestedSurgeryFinance->finance_charge_id);
            })
            ->exists();

        if ($hasDependents) {
            return response()->json([
                'message' => 'لا يمكن حذف هذا البند لأنه مستخدم لحساب بنود أخرى',
            ], 422);
        }

        $requestedSurgeryFinance->delete();

        return response()->json(null, 204);
    }

    public function print(Admission $admission, RequestedSurgery $requestedSurgery)
    {
        if ($requestedSurgery->admission_id !== $admission->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $report = new \App\Services\Pdf\SurgeryFinanceReport($requestedSurgery);
        $pdfContent = $report->generate();

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="surgery_finance_report.pdf"');
    }

    public function invoice(Admission $admission, RequestedSurgery $requestedSurgery)
    {
        if ($requestedSurgery->admission_id !== $admission->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $report = new \App\Services\Pdf\SurgeryInvoiceA5($requestedSurgery);
        $pdfContent = $report->generate();

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="surgery_invoice_' . $requestedSurgery->id . '.pdf"');
    }

    public function getLedger(RequestedSurgery $requestedSurgery)
    {
        $transactions = $requestedSurgery->transactions()->with('user:id,name')->orderBy('created_at', 'asc')->get();

        $totalDebits = $transactions->where('type', 'debit')->sum('amount');
        $totalCredits = $transactions->where('type', 'credit')->sum('amount');
        $balance = $totalDebits - $totalCredits;

        return response()->json([
            'transactions' => $transactions,
            'summary' => [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'balance' => $balance,
            ]
        ]);
    }

    /**
     * Summary for all requested surgeries of a given admission:
     * - total_initial: sum of initial_price
     * - paid: sum of credit transactions
     * - balance: total_initial - paid
     */
    public function admissionSummary(Admission $admission)
    {
        $totalInitial = (float) RequestedSurgery::where('admission_id', $admission->id)
            ->sum('initial_price');

        $paid = (float) RequestedSurgeryTransaction::whereHas('requestedSurgery', function ($q) use ($admission) {
                $q->where('admission_id', $admission->id);
            })
            ->where('type', 'credit')
            ->sum('amount');

        $balance = $totalInitial - $paid;

        return response()->json([
            'total_initial' => $totalInitial,
            'paid'          => $paid,
            'balance'       => $balance,
        ]);
    }

    public function addTransaction(Request $request, RequestedSurgery $requestedSurgery)
    {
        $validated = $request->validate([
            'payment_method' => 'required|in:cash,bankak',
            'amount'         => 'required|numeric|min:0.01',
            'description'    => 'required|string|max:255',
            'notes'          => 'nullable|string',
        ]);

        $transactions = $requestedSurgery->transactions()->get();
        $totalDebits = $transactions->where('type', 'debit')->sum('amount');
        $totalCredits = $transactions->where('type', 'credit')->sum('amount');
        $balance = $totalDebits - $totalCredits;

        if ($balance <= 0) {
            return response()->json(['message' => 'العملية مسددة بالكامل، لا يمكن إضافة دفعات جديدة'], 422);
        }

        if ($validated['amount'] > $balance) {
            return response()->json(['message' => 'المبلغ المدخل أكبر من الرصيد المتبقي (' . number_format($balance, 2) . ')'], 422);
        }

        $transaction = $requestedSurgery->transactions()->create([
            'type'           => 'credit', // Manual entries are now specifically payments
            'payment_method' => $validated['payment_method'],
            'amount'         => $validated['amount'],
            'description'    => $validated['description'],
            'notes'          => $validated['notes'] ?? null,
            'user_id'        => Auth::id(),
        ]);

        return response()->json($transaction->load('user:id,name'));
    }

    public function destroyTransaction(RequestedSurgery $requestedSurgery, RequestedSurgeryTransaction $transaction)
    {
        if ($transaction->requested_surgery_id !== $requestedSurgery->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent deleting debit transactions (charges)
        if ($transaction->type === 'debit') {
            return response()->json(['message' => 'لا يمكن حذف الرسوم التلقائية للعملية'], 422);
        }

        $transaction->delete();

        return response()->json(['message' => 'تم حذف المعاملة بنجاح']);
    }

    public function printLedger(RequestedSurgery $requestedSurgery)
    {
        $transactions = $requestedSurgery->transactions()->with('user:id,name')->orderBy('created_at', 'asc')->get();
        $totalDebits = $transactions->where('type', 'debit')->sum('amount');
        $totalCredits = $transactions->where('type', 'credit')->sum('amount');
        $balance = $totalDebits - $totalCredits;

        $ledgerData = [
            'transactions' => $transactions,
            'summary' => [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'balance' => $balance,
            ]
        ];

        $report = new \App\Services\Pdf\SurgeryLedgerReport($requestedSurgery, $ledgerData);
        $pdfContent = $report->generate();

        return response($pdfContent)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="surgery_ledger_' . $requestedSurgery->id . '.pdf"');
    }
}
