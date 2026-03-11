<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\RequestedSurgery;
use App\Models\RequestedSurgeryFinance;
use App\Models\SurgicalOperation;
use App\Models\RequestedSurgeryTransaction;
use App\Services\FirebaseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
            $this->syncRequestedSurgeryApprovalToFirestore($admission->id, $requestedSurgery->id, $requestedSurgery->approved_by, $requestedSurgery->approved_at, $requestedSurgery->status);
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
            $this->syncRequestedSurgeryApprovalToFirestore($admission->id, $requestedSurgery->id, null, null, 'pending');
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

        $this->syncRequestedSurgeryApprovalToFirestore($admission->id, $requestedSurgery->id, $requestedSurgery->approved_by, $requestedSurgery->approved_at, 'rejected');

        return response()->json($requestedSurgery->load(['surgery', 'doctor', 'user', 'approvedBy', 'finances.financeCharge']));
    }

    /**
     * Sync approval from Firestore to database (when Firestore is updated externally).
     */
    public function syncApprovalFromFirestore(Request $request, Admission $admission, RequestedSurgery $requestedSurgery)
    {
        if ($requestedSurgery->admission_id !== $admission->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'approved_at' => 'nullable|string',
            'approved_by' => 'nullable|integer|exists:users,id',
            'status'      => 'required|in:pending,approved,rejected',
        ]);

        $approvedAt = null;
        if (!empty($validated['approved_at'])) {
            try {
                $approvedAt = \Carbon\Carbon::parse($validated['approved_at']);
            } catch (\Throwable $e) {
                return response()->json(['message' => 'Invalid approved_at format'], 422);
            }
        }

        $requestedSurgery->update([
            'approved_at' => $approvedAt,
            'approved_by' => $validated['approved_by'] ?? null,
            'status'      => $validated['status'],
        ]);

        return response()->json(
            $requestedSurgery->load(['surgery', 'doctor', 'user', 'approvedBy', 'finances.financeCharge'])
        );
    }

    /**
     * Fetch Firestore document and sync all requested surgeries with approved_at to DB.
     */
    public function syncAllFromFirestore(Admission $admission)
    {
        $firestorePath = "pharmacies/one_care/admissions/{$admission->id}";
        $fields = FirebaseService::getFirestoreDocumentFields($firestorePath);
        if (!$fields || !isset($fields['requested_surgeries']) || !is_array($fields['requested_surgeries'])) {
            return response()->json(['synced' => 0, 'message' => 'لا توجد بيانات في Firestore']);
        }

        $requestedSurgeriesData = $fields['requested_surgeries'];
        $synced = 0;

        foreach ($requestedSurgeriesData as $rs) {
            if (!is_array($rs)) {
                continue;
            }
            $approvedAt = $rs['approved_at'] ?? null;
            if (empty($approvedAt)) {
                continue;
            }

            $id = (int) ($rs['id'] ?? 0);
            if ($id <= 0) {
                continue;
            }

            $requestedSurgery = RequestedSurgery::where('id', $id)
                ->where('admission_id', $admission->id)
                ->first();

            if (!$requestedSurgery) {
                continue;
            }

            try {
                $parsedAt = \Carbon\Carbon::parse($approvedAt);
            } catch (\Throwable $e) {
                continue;
            }

            $requestedSurgery->update([
                'approved_at' => $parsedAt,
                'approved_by' => isset($rs['approved_by']) ? (int) $rs['approved_by'] : null,
                'status'      => $rs['status'] ?? 'approved',
            ]);
            $synced++;
        }

        return response()->json([
            'synced' => $synced,
            'surgeries' => $admission->requestedSurgeries()
                ->with(['surgery', 'doctor', 'user', 'approvedBy', 'finances.financeCharge'])
                ->get(),
        ]);
    }

    /**
     * Sync requested surgery approval (approved_at, approved_by, status) to Firestore.
     */
    protected function syncRequestedSurgeryApprovalToFirestore(int $admissionId, int $requestedSurgeryId, ?int $approvedBy, $approvedAt, string $status): void
    {
        $firestorePath = "pharmacies/one_care/admissions/{$admissionId}";
        $fields = FirebaseService::getFirestoreDocumentFields($firestorePath);
        if (!$fields || !isset($fields['requested_surgeries']) || !is_array($fields['requested_surgeries'])) {
            return;
        }

        $requestedSurgeriesData = $fields['requested_surgeries'];
        $updated = false;
        foreach ($requestedSurgeriesData as &$rs) {
            if (!is_array($rs)) {
                continue;
            }
            $id = $rs['id'] ?? null;
            if ($id === $requestedSurgeryId) {
                $rs['approved_by'] = $approvedBy;
                $rs['approved_at'] = $approvedAt instanceof \DateTimeInterface ? $approvedAt->format('Y-m-d H:i:s') : $approvedAt;
                $rs['status'] = $status;
                $updated = true;
                break;
            }
        }
        unset($rs);

        if ($updated) {
            FirebaseService::createOrUpdateFirestoreDocumentByPath($firestorePath, [
                'requested_surgeries' => $requestedSurgeriesData,
                'updated_at' => now(),
            ]);
        }
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

            // Enforce: total finance amount must not exceed initial_price
            $requestedSurgery = $requestedSurgeryFinance->requestedSurgery;
            $initialPrice = $requestedSurgery?->initial_price;
            if ($initialPrice !== null && (float) $initialPrice > 0) {
                $totalFinances = RequestedSurgeryFinance::where('requested_surgery_id', $requestedSurgeryFinance->requested_surgery_id)
                    ->sum('amount');
                if ($totalFinances > (float) $initialPrice) {
                    DB::rollBack();
                    return response()->json([
                        'message' => 'إجمالي التكاليف لا يمكن أن يتجاوز السعر المبدئي (' . number_format((float) $initialPrice, 0) . ' ج.س)',
                    ], 422);
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

    /**
     * Prepare WhatsApp: store admission in Firestore, generate PDF, upload to Storage, update Firestore with download_url.
     */
    public function prepareWhatsApp(Admission $admission, RequestedSurgery $requestedSurgery)
    {
        if ($requestedSurgery->admission_id !== $admission->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $admission->load(['patient', 'ward', 'bed', 'doctor', 'requestedSurgeries.surgery', 'requestedSurgeries.finances.financeCharge']);
        $requestedSurgery->load(['surgery', 'finances.financeCharge']);

        $patient = $admission->patient;
        $patientPhone = $patient->phone ?? '';
        $normalizedPhone = preg_replace('/[^0-9]/', '', $patientPhone);
        if ($normalizedPhone && strpos($normalizedPhone, '249') !== 0) {
            $normalizedPhone = (strpos($normalizedPhone, '0') === 0 ? substr($normalizedPhone, 1) : $normalizedPhone);
            $patientPhone = '249' . $normalizedPhone;
        } else {
            $patientPhone = $normalizedPhone ?: $patientPhone;
        }

        $requestedSurgeriesData = [];
        foreach ($admission->requestedSurgeries as $rs) {
            $financesData = [];
            foreach ($rs->finances ?? [] as $f) {
                $fc = $f->financeCharge;
                $financesData[] = [
                    'name' => $fc?->name ?? '',
                    'amount' => (float) ($f->amount ?? 0),
                    'payment_method' => $f->payment_method ?? 'cash',
                    'beneficiary' => $fc?->beneficiary ?? '',
                ];
            }
            $requestedSurgeriesData[] = [
                'id' => $rs->id,
                'surgery_name' => $rs->surgery?->name ?? '',
                'initial_price' => (float) ($rs->initial_price ?? 0),
                'status' => $rs->status ?? '',
                'total_price' => (float) $rs->total_price,
                'download_url' => null,
                'approved_by' => $rs->approved_by,
                'approved_at' => $rs->approved_at?->format('Y-m-d H:i:s'),
                'finances' => $financesData,
            ];
        }

        $firestorePath = "pharmacies/one_care/admissions/{$admission->id}";
        $admissionFields = [
            'admission_id' => $admission->id,
            'patient_name' => $patient->name ?? '',
            'patient_phone' => $patientPhone,
            'admission_date' => $admission->admission_date?->format('Y-m-d') ?? '',
            'status' => $admission->status ?? '',
            'surgery_name' => $requestedSurgery->surgery?->name ?? '',
            'total_price' => (float) ($requestedSurgery->total_price ?? 0),
            'requested_surgeries' => $requestedSurgeriesData,
            'updated_at' => now(),
        ];

        if (!FirebaseService::createOrUpdateFirestoreDocumentByPath($firestorePath, $admissionFields)) {
            Log::warning('Failed to store admission in Firestore', ['admission_id' => $admission->id]);
            return response()->json(['message' => 'فشل حفظ تفاصيل التنويم في Firestore'], 500);
        }

        $report = new \App\Services\Pdf\SurgeryFinanceReport($requestedSurgery);
        $pdfContent = $report->generate();

        $storagePath = "one_care/admissions/{$admission->id}/surgery_{$requestedSurgery->id}.pdf";
        try {
            $downloadUrl = FirebaseService::uploadPdfToStorage($pdfContent, $storagePath);
        } catch (\Throwable $e) {
            Log::error('Failed to upload surgery PDF to Firebase Storage', [
                'admission_id' => $admission->id,
                'requested_surgery_id' => $requestedSurgery->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'فشل رفع التقرير إلى التخزين'], 500);
        }

        foreach ($requestedSurgeriesData as &$rsData) {
            if ($rsData['id'] === $requestedSurgery->id) {
                $rsData['download_url'] = $downloadUrl;
                break;
            }
        }
        unset($rsData);

        $updateFields = [
            'download_url' => $downloadUrl,
            'requested_surgeries' => $requestedSurgeriesData,
            'updated_at' => now(),
        ];
        if (!FirebaseService::createOrUpdateFirestoreDocumentByPath($firestorePath, $updateFields)) {
            Log::warning('Failed to update Firestore with download_url', ['admission_id' => $admission->id]);
            return response()->json(['message' => 'فشل تحديث رابط التقرير في Firestore'], 500);
        }

        $pdfRequestsPath = "pharmacies/one_care/pdf_requests/{$patientPhone}";
        $pdfRequestFields = [
            'admission_id' => $admission->id,
            'download_url' => $downloadUrl,
            'requested_surgery_id' => $requestedSurgery->id,
            'updated_at' => now(),
        ];
        FirebaseService::createOrUpdateFirestoreDocumentByPath($pdfRequestsPath, $pdfRequestFields);

        return response()->json([
            'success' => true,
            'download_url' => $downloadUrl,
        ]);
    }

    /**
     * Mark request as sent (after prepareWhatsApp + WhatsApp send complete).
     */
    public function markRequestSent(Admission $admission, RequestedSurgery $requestedSurgery)
    {
        if ($requestedSurgery->admission_id !== $admission->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $requestedSurgery->update(['request_send_status' => true]);

        return response()->json(
            $requestedSurgery->load(['surgery', 'doctor', 'user', 'approvedBy', 'finances.financeCharge'])
        );
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
        $initialPrice = (float) ($requestedSurgery->initial_price ?? 0);
        $balance = $initialPrice - $totalCredits;

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

        $initialPrice = (float) ($requestedSurgery->initial_price ?? 0);
        $totalCredits = (float) $requestedSurgery->transactions()->where('type', 'credit')->sum('amount');
        $balance = $initialPrice - $totalCredits;

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
