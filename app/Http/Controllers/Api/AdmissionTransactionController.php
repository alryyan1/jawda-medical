<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionTransaction;
use App\Services\Admissions\StayDaysCalculator;
use App\Services\Pdf\AdmissionLedgerReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Resources\AdmissionTransactionResource;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AdmissionTransactionController extends Controller
{
    /**
     * Sync or create the room_charges transaction for an admission based on current stay days and configurable rules.
     * Only runs for non–short-stay admissions that have a room (ward/room/bed).
     */
    protected function syncStayFeesTransaction(Admission $admission): void
    {
        if ($admission->short_stay_bed_id || ! $admission->room_id) {
            return;
        }

        $admission->loadMissing('room');

        $admissionAt = $this->buildAdmissionCarbon($admission->admission_date, $admission->admission_time);
        $endAt = $admission->status === 'discharged' && $admission->discharge_date && $admission->discharge_time
            ? $this->buildAdmissionCarbon($admission->discharge_date, $admission->discharge_time)
            : Carbon::now();

        $days = StayDaysCalculator::calculate($admissionAt, $endAt);
        $pricePerDay = (float) ($admission->room->price_per_day ?? 0);
        // حجز غرفة كاملة: ضرب السعر في 2
        if ($admission->booking_type === 'room') {
            $pricePerDay *= 2;
        }
        $total = round($days * $pricePerDay, 2);

        if ($total <= 0) {
            return;
        }

        $existing = $admission->transactions()->where('reference_type', 'room_charges')->first();

        if ($existing) {
            if (abs((float) $existing->amount - $total) < 0.01) {
                return;
            }
            $existing->update([
                'amount' => $total,
                'description' => 'رسوم إقامة (' . $days . ' ' . ($days === 1 ? 'يوم' : 'أيام') . ')',
                'user_id' => Auth::id(),
            ]);
        } else {
            $admission->transactions()->create([
                'type' => 'debit',
                'amount' => $total,
                'description' => 'رسوم إقامة (' . $days . ' ' . ($days === 1 ? 'يوم' : 'أيام') . ')',
                'reference_type' => 'room_charges',
                'reference_id' => null,
                'is_bank' => false,
                'user_id' => Auth::id(),
            ]);
        }
    }

    protected function buildAdmissionCarbon($date, $time): Carbon
    {
        $dateStr = $date instanceof Carbon ? $date->format('Y-m-d') : $date;
        $timeStr = $time instanceof Carbon ? $time->format('H:i:s') : ($time ?? '00:00:00');
        if (strlen($timeStr) === 5) {
            $timeStr .= ':00';
        }

        return Carbon::parse($dateStr . ' ' . $timeStr);
    }
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
     * Automatically syncs room_charges (stay fees) based on configurable day-count rules when ledger is opened.
     */
    public function ledger(Admission $admission)
    {
        $this->syncStayFeesTransaction($admission);

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


