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
            'surgery_id' => 'required|exists:surgical_operations,id',
            'doctor_id'  => 'nullable|exists:doctors,id',
        ]);

        /** @var SurgicalOperation $surgicalOperation */
        $surgicalOperation = SurgicalOperation::with('charges')->findOrFail($validated['surgery_id']);

        DB::beginTransaction();
        try {
            // 1. Create the requested surgery record
            $requestedSurgery = RequestedSurgery::create([
                'admission_id' => $admission->id,
                'surgery_id'   => $surgicalOperation->id,
                'doctor_id'    => $validated['doctor_id'] ?? null,
                'user_id'      => Auth::id(),
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

    public function addTransaction(Request $request, RequestedSurgery $requestedSurgery)
    {
        $validated = $request->validate([
            'type'        => 'required|in:debit,credit',
            'amount'      => 'required|numeric|min:0.01',
            'description' => 'required|string|max:255',
            'notes'       => 'nullable|string',
        ]);

        $transaction = $requestedSurgery->transactions()->create([
            'type'        => $validated['type'],
            'amount'      => $validated['amount'],
            'description' => $validated['description'],
            'notes'       => $validated['notes'] ?? null,
            'user_id'     => Auth::id(),
        ]);

        return response()->json($transaction->load('user:id,name'));
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
