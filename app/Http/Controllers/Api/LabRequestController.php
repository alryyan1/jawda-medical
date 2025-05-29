<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DoctorVisitResource;
use App\Models\LabRequest;
use App\Models\DoctorVisit;
use App\Models\MainTest;
use App\Models\Patient;
use App\Models\Company;
use App\Models\RequestedResult;
use App\Models\ChildTest;
use App\Models\Shift;
use Illuminate\Http\Request;
use App\Http\Resources\LabRequestResource;
use App\Http\Resources\MainTestStrippedResource;
use App\Http\Resources\PatientLabQueueItemResource;
// If you create a specific resource for MainTestWithChildrenResults:
// use App\Http\Resources\MainTestWithChildrenResultsResource; 
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; // For logging errors

class LabRequestController extends Controller
{
    public function __construct()
    {
        // Example Permissions (adjust to your defined permission names)
        // $this->middleware('can:view lab_queue')->only('getLabPendingQueue');
        // $this->middleware('can:view lab_requests')->only(['indexForVisit', 'show', 'getLabRequestForEntry']);
        // $this->middleware('can:request lab_tests')->only(['storeBatchForVisit', 'availableTestsForVisit']);
        // $this->middleware('can:edit lab_requests')->only(['update', 'updateFlags']); // updateFlags could be part of update
        // $this->middleware('can:cancel lab_requests')->only('destroy');
        // $this->middleware('can:record lab_payment')->only('recordPayment');
        // $this->middleware('can:enter lab_results')->only('saveResults');
        // $this->middleware('can:authorize lab_results')->only('authorizeResults');
    }
    // Example in LabRequestController or VisitServiceController
// app/Http/Controllers/Api/LabRequestController.php
public function clearPendingRequests(Request $request, DoctorVisit $visit)
{
    // $this->authorize('cancel_multiple_lab_requests', $visit); // Permission
    $count = $visit->labRequests()
                   ->where('is_paid', false)
                   ->where('done', false) // Assuming 'done' means processed/resulted
                   // Add other conditions that define "cancellable"
                   // ->whereNull('sample_id') // e.g., sample not yet taken
                   ->delete(); // Performs a mass delete on the query

    if ($count > 0) {
        // Invalidate relevant caches or trigger events if needed
        return response()->json(['message' => "تم إلغاء {$count} طلب فحص معلق بنجاح.", 'deleted_count' => $count]);
    }
    return response()->json(['message' => 'لا توجد طلبات فحص معلقة قابلة للإلغاء لهذه الزيارة.', 'deleted_count' => 0]);
}

    /**
     * Get the queue of patients with pending lab work.
     */
    public function getLabPendingQueue(Request $request)
    {
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $dateFrom = $request->filled('date_from') ? Carbon::parse($request->date_from)->startOfDay() : Carbon::today()->startOfDay();
        $dateTo = $request->filled('date_to') ? Carbon::parse($request->date_to)->endOfDay() : Carbon::today()->endOfDay();
        $perPage = $request->input('per_page', 20);

        $query = DoctorVisit::query()
            ->select('doctorvisits.id as visit_id', 
                     'doctorvisits.created_at as visit_creation_time',
                     'patients.id as patient_id', 
                     'patients.name as patient_name'
            )
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
            ->whereBetween('doctorvisits.visit_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
            ->whereHas('labRequests', function ($q_lab) { // Only visits that have lab requests
                $q_lab->where('valid', true); // Consider only valid requests
                // Further filter for "pending results" based on your system's status logic
                // Example: If LabRequest has a 'result_status' column
                // $q_lab->whereNotIn('result_status', ['completed', 'authorized', 'cancelled']);
            })
            ->withCount(['labRequests as test_count' => function ($q_lab_count) {
                $q_lab_count->where('valid', true);
                // Add specific pending status filter for count if needed
            }])
            ->withMin('labRequests as oldest_request_time', 'labrequests.created_at') // Ensure this table alias is correct
            ->with(['labRequests:id,doctor_visit_id,sample_id']);

        if ($request->filled('shift_id')) {
            $query->where('doctorvisits.shift_id', $request->shift_id);
        }

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q_search) use ($searchTerm) {
                $q_search->where('patients.name', 'LIKE', "%{$searchTerm}%")
                         ->orWhere('patients.id', $searchTerm)
                         ->orWhereExists(function ($subQuery) use ($searchTerm) {
                            $subQuery->select(DB::raw(1))
                                     ->from('labrequests as lr_search_sub') // Use a different alias
                                     ->whereColumn('lr_search_sub.doctor_visit_id', 'doctorvisits.id')
                                     ->where(function ($lrInnerSearch) use ($searchTerm) {
                                        $lrInnerSearch->where('lr_search_sub.sample_id', 'LIKE', "%{$searchTerm}%")
                                                      ->orWhere('lr_search_sub.id', $searchTerm);
                                     });
                         });
            });
        }
        
        // This condition might be important to ensure only visits with truly pending tests are shown
        // It depends on how 'test_count' and the whereHas('labRequests'...) are defined.
        // $query->having('test_count', '>', 0); 

        $pendingVisits = $query->orderBy('oldest_request_time', 'asc')
                                 ->orderBy('visit_creation_time', 'asc')
                                 ->paginate($perPage);
    
        return PatientLabQueueItemResource::collection($pendingVisits);
    }

    /**
     * List lab requests for a specific visit. (For TestSelectionPanel)
     */
    public function indexForVisit(Request $request, DoctorVisit $visit)
    {
        $labRequests = $visit->labRequests()
                             ->with(['mainTest:id,main_test_name,price', 'requestingUser:id,name'])
                             ->orderBy('created_at', 'asc') // Or by main_test.name
                             ->get();
        return LabRequestResource::collection($labRequests);
    }
    
    /**
     * List available MainTests for selection (excluding already requested for this visit).
     */
    public function availableTestsForVisit(Request $request, DoctorVisit $visit)
    {
        $requestedTestIds = $visit->labRequests()->pluck('main_test_id')->toArray();
        $availableTests = MainTest::where('available', true)
                                ->whereNotIn('id', $requestedTestIds)
                                ->orderBy('main_test_name')
                                ->get(['id', 'main_test_name', 'price']);
        return MainTestStrippedResource::collection($availableTests);
    }
    // app/Http/Controllers/Api/LabRequestController.php
public function batchPayLabRequests(Request $request, DoctorVisit $visit)
{
    // $this->authorize('record_batch_lab_payment', $visit);
    $validated = $request->validate([
        'total_payment_amount' => 'required|numeric|min:0.01',
        'is_bankak' => 'required|boolean',
        'shift_id' => ['required', 'integer', 'exists:shifts,id', Rule::exists('shifts','id')->where('is_closed',false)],
        'payment_notes' => 'nullable|string|max:255', // Optional overall payment note
    ]);

    $totalPaymentAmount = (float) $validated['total_payment_amount'];
    $isBankak = (bool) $validated['is_bankak'];
    $currentShiftId = $validated['shift_id'];
    $userId = Auth::id();

    // Get all unpaid lab requests for this visit, ordered (e.g., by creation date)
    $unpaidRequests = $visit->labRequests()
        ->where('is_paid', false)
        ->orderBy('created_at', 'asc') // Pay oldest first
        ->get();

    if ($unpaidRequests->isEmpty()) {
        return response()->json(['message' => 'جميع طلبات المختبر لهذه الزيارة مدفوعة بالفعل.'], 400);
    }

    $totalBalanceDueForAll = 0;
    foreach($unpaidRequests as $lr) {
        $price = (float) $lr->price;
        $count = (int) ($lr->count ?? 1);
        $itemSubTotal = $price * $count;
        $discountAmount = ($itemSubTotal * ((int) ($lr->discount_per ?? 0) / 100));
        $enduranceAmount = (float) ($lr->endurance ?? 0);
        $netPayableByPatient = $itemSubTotal - $discountAmount - ($visit->patient->company_id ? $enduranceAmount : 0);
        $totalBalanceDueForAll += ($netPayableByPatient - (float) $lr->amount_paid);
    }
    
    // Prevent overpayment for the batch
    if ($totalPaymentAmount > $totalBalanceDueForAll + 0.009) { // Allow for small float inaccuracies
        return response()->json(['message' => 'المبلغ الإجمالي المدفوع يتجاوز إجمالي الرصيد المستحق لطلبات المختبر.', 'total_due' => $totalBalanceDueForAll], 422);
    }

    DB::beginTransaction();
    try {
        $remainingPaymentToDistribute = $totalPaymentAmount;
        $paidRequestsCount = 0;

        foreach ($unpaidRequests as $labrequest) {
            if ($remainingPaymentToDistribute <= 0) break;

            $price = (float) $labrequest->price;
            $count = (int) ($labrequest->count ?? 1);
            $itemSubTotal = $price * $count;
            $discountAmount = ($itemSubTotal * ((int) ($labrequest->discount_per ?? 0) / 100));
            $enduranceAmount = (float) ($labrequest->endurance ?? 0);
            $netPayableByPatient = $itemSubTotal - $discountAmount - ($visit->patient->company_id ? $enduranceAmount : 0);
            
            $balanceForItem = $netPayableByPatient - (float) $labrequest->amount_paid;

            if ($balanceForItem <= 0) continue; // Already paid or overpaid somehow

            $paymentForThisItem = min($remainingPaymentToDistribute, $balanceForItem);

            // Create a deposit record if you have a lab_request_deposits table
            // LabRequestDeposit::create([
            //     'lab_request_id' => $labrequest->id,
            //     'amount' => $paymentForThisItem,
            //     'is_bank' => $isBankak,
            //     'user_id' => $userId,
            //     'shift_id' => $currentShiftId,
            //     'notes' => $request->input('payment_notes'), // Overall note for all payments in this batch
            // ]);

            $labrequest->amount_paid += $paymentForThisItem;
            $labrequest->is_bankak = $isBankak; // Set payment method for the latest payment part
            $labrequest->user_deposited = $userId;

            if ($labrequest->amount_paid >= $netPayableByPatient - 0.009) {
                $labrequest->is_paid = true;
                $labrequest->amount_paid = $netPayableByPatient; // Ensure exact amount if fully paid
            }
            $labrequest->save();
            $paidRequestsCount++;
            $remainingPaymentToDistribute -= $paymentForThisItem;
        }
        DB::commit();
        // It's better to return the updated visit with all its lab requests
        // so the frontend can update everything at once.
        return new DoctorVisitResource($visit->fresh()->load([
            'labRequests.mainTest', 
            'labRequests.requestingUser:id,name', 
            'labRequests.depositUser:id,name'
        ]));

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error("Batch lab request payment failed for Visit ID {$visit->id}: " . $e->getMessage());
        return response()->json(['message' => 'فشل تسجيل الدفعة المجمعة.', 'error' => 'خطأ داخلي.'], 500);
    }
}

    // Store multiple lab requests for a visit
    public function storeBatchForVisit(Request $request, DoctorVisit $visit)
    {
        // $this->authorize('create', LabRequest::class);
        $validated = $request->validate([
            'main_test_ids' => 'required|array',
            'main_test_ids.*' => 'required|integer|exists:main_tests,id',
            'comment' => 'nullable|string',
        ]);

        $patient = $visit->patient()->firstOrFail();
        $company = $patient->company_id ? Company::find($patient->company_id) : null;

        $createdLabRequests = []; // To hold the LabRequest models created
        DB::beginTransaction();
        try {
            foreach ($validated['main_test_ids'] as $mainTestId) {
                $mainTest = MainTest::with('childTests.unit')->find($mainTestId); // Eager load child tests and their units
                if (!$mainTest) continue;

                $alreadyExists = $visit->labRequests()->where('main_test_id', $mainTestId)->exists();
                if ($alreadyExists && !$request->input('allow_duplicates', false)) {
                    // Handle or log duplicate, for now skipping
                    continue;
                }

                $price = $mainTest->price;
                $endurance = 0;
                $approve = true; // Default approval

                if ($company) {
                    $contract = $company->contractedMainTests()
                        ->where('main_tests.id', $mainTestId)
                        ->first();
                    if ($contract && $contract->pivot->status) {
                        $price = $contract->pivot->price;
                        $approve = $contract->pivot->approve;
                        if ($contract->pivot->use_static) {
                            $endurance = $contract->pivot->endurance_static;
                        } else {
                            $endurance = ($price * $contract->pivot->endurance_percentage) / 100;
                        }
                    }
                }

                $labRequest = LabRequest::create([
                    'main_test_id' => $mainTestId,
                    'pid' => $visit->patient_id,
                    'doctor_visit_id' => $visit->id,
                    'hidden' => $request->input('hidden', false), // Assuming default is visible
                    'is_lab2lab' => $request->input('is_lab2lab', false),
                    'valid' => true,
                    'no_sample' => false, // Sample presumably will be collected
                    'price' => $price,
                    'amount_paid' => 0,
                    'discount_per' => 0,
                    'is_bankak' => false,
                    'comment' => $request->input('comment', null),
                    'user_requested' => Auth::id(),
                    'approve' => $approve,
                    'endurance' => $endurance,
                    'is_paid' => false,
                    // 'sample_id' => null, // Generate this upon sample collection
                ]);

                // --- NEW: Create placeholder RequestedResult entries ---
                if ($mainTest->childTests->isNotEmpty()) {
                    $requestedResultsData = [];
                    foreach ($mainTest->childTests as $childTest) {
                        $requestedResultsData[] = [
                            'lab_request_id' => $labRequest->id,
                            'patient_id' => $visit->patient_id,
                            'main_test_id' => $mainTest->id,
                            'child_test_id' => $childTest->id,
                            'result' => '', // Initial empty result
                            // Capture normal range and unit AT THE TIME OF REQUEST
                            'normal_range' => $childTest->normalRange ?? ($childTest->low !== null && $childTest->upper !== null ? $childTest->low . ' - ' . $childTest->upper : null),
                            'unit_id' => $childTest->unit?->id, // From eager loaded unit
                            'created_at' => now(),
                            'updated_at' => now(),
                            // 'entered_by_user_id' => null, // Will be set upon result entry
                        ];
                    }
                    if (!empty($requestedResultsData)) {
                        RequestedResult::insert($requestedResultsData); // Bulk insert for efficiency
                    }
                }
                // --- End NEW ---

                $createdLabRequests[] = $labRequest;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            // Log::error("Failed to request lab tests for visit {$visit->id}: " . $e->getMessage());
            return response()->json(['message' => 'فشل طلب الفحوصات.', 'error' => $e->getMessage()], 500);
        }

        $loadedLabRequests = collect($createdLabRequests)->map(fn($lr) => $lr->load(['mainTest.childTests.unit', 'requestingUser:id,name']));
        return LabRequestResource::collection($loadedLabRequests);
    }
    /**
     * Display the specified lab request. (Generic show, can be used by getLabRequestForEntry)
     */
    public function show(LabRequest $labrequest)
    {
        $labrequest->load([
            'mainTest.childTests.unit', 'mainTest.childTests.childGroup', 'mainTest.childTests.options',
            'patient:id,name,phone,gender,age_year,age_month,age_day,company_id', // Load company_id for patient
            'patient.company:id,name', // Load patient's company if exists
            'requestingUser:id,name', 'depositUser:id,name',
            'results.childTest:id,child_test_name', 'results.enteredBy:id,name', 'results.authorizedBy:id,name',
        ]);
        return new LabRequestResource($labrequest);
    }
    
    /**
     * Get a specific LabRequest prepared for result entry.
     * Includes MainTest, its ChildTests (with options, units), and existing results.
     */
    public function getLabRequestForEntry(LabRequest $labrequest)
    {
        $labrequest->load([
            'mainTest' => function ($query) {
                $query->with([
                    'childTests' => fn($q_ct) => $q_ct->orderBy('test_order')->orderBy('child_test_name'),
                    'childTests.unit:id,name', 'childTests.childGroup:id,name',
                    'childTests.options:id,child_test_id,name'
                ]);
            },
            'results.childTest:id,child_test_name', // Needed to map existing results to child tests
        ]);

        // Transform into the MainTestWithChildrenResults structure
        $mainTestWithChildrenResults = [
            'lab_request_id' => $labrequest->id,
            'main_test_id' => $labrequest->mainTest->id,
            'main_test_name' => $labrequest->mainTest->main_test_name,
            'is_trailer_hidden' => $labrequest->hidden,
            'main_test_comment' => $labrequest->comment,
            'child_tests_with_results' => $labrequest->mainTest->childTests->map(function ($childTest) use ($labrequest) {
                $existingResult = $labrequest->results->firstWhere('child_test_id', $childTest->id);
                return [
                    'id' => $childTest->id, // ChildTest ID
                    'main_test_id' => $childTest->main_test_id,
                    'child_test_name' => $childTest->child_test_name,
                    'low' => $childTest->low, 'upper' => $childTest->upper, 'defval' => $childTest->defval,
                    'unit_id' => $childTest->unit_id, 'unit_name' => $childTest->unit->name ?? null,
                    'unit' => $childTest->unit ? ['id' => $childTest->unit->id, 'name' => $childTest->unit->name] : null,
                    'normalRange' => $childTest->normalRange,
                    'max' => $childTest->max, 'lowest' => $childTest->lowest,
                    'test_order' => $childTest->test_order,
                    'child_group_id' => $childTest->child_group_id,
                    'child_group_name' => $childTest->childGroup->name ?? null,
                    'child_group' => $childTest->childGroup ? ['id' => $childTest->childGroup->id, 'name' => $childTest->childGroup->name] : null,
                    'options' => $childTest->options->map(fn($opt) => ['id' => $opt->id, 'name' => $opt->name, 'child_test_id' => $opt->child_test_id])->all(),
                    'result_id' => $existingResult->id ?? null,
                    'result_value' => $existingResult->result ?? null,
                    'result_flags' => $existingResult->flags ?? null,
                    'result_comment' => $existingResult->result_comment ?? null,
                    'is_result_authorized' => isset($existingResult->authorized_at),
                    'entered_at' => $existingResult->entered_at?->toIso8601String(),
                ];
            })->all(),
        ];
        return response()->json(['data' => $mainTestWithChildrenResults]);
    }


    /**
     * Save or Update results for a given LabRequest.
     * The frontend sends an array of results, each corresponding to a ChildTest.
     */
    public function saveResults(Request $request, LabRequest $labrequest)
    {
        // Permission Check: e.g., can('enter lab_results')
        // if (!Auth::user()->can('enter lab_results', $labrequest)) { // Policy based on labrequest
        //     return response()->json(['message' => 'Unauthorized to enter results for this request.'], 403);
        // }

        // Check if results can still be entered (e.g., not already fully authorized)
        // if ($labrequest->isFullyAuthorized()) { // Assuming you add such a method to LabRequest model
        //     return response()->json(['message' => 'لا يمكن تعديل النتائج بعد الاعتماد النهائي.'], 403);
        // }

        $validatedData = $request->validate([
            'results' => 'present|array', // Must be present, can be empty array if clearing all results (unlikely)
            'results.*.child_test_id' => [
                'required', 
                'integer', 
                // Ensure child_test_id actually belongs to the main_test of this labrequest
                Rule::exists('child_tests', 'id')->where(function ($query) use ($labrequest) {
                    $query->where('main_test_id', $labrequest->main_test_id);
                }),
            ],
            'results.*.result' => 'nullable|string|max:2000', // Max length for a result value
            'results.*.flags' => 'nullable|string|max:50',   // e.g., H, L, HH, LL, CRIT
            'results.*.comment' => 'nullable|string|max:500', // Per-result comment
            'main_test_comment' => 'nullable|string|max:2000', // Overall comment for the LabRequest
            // 'sample_received_at' => 'nullable|date_format:Y-m-d H:i:s', // If lab tracks this
            // 'results_entered_partially' => 'nullable|boolean', // If technician can mark as partial
        ]);

        DB::beginTransaction();
        try {
            $userId = Auth::id();
            $now = now();
            $updatedResultIds = [];

            foreach ($validatedData['results'] as $resultInput) {
                $childTest = ChildTest::with('unit')->find($resultInput['child_test_id']); // Find child test to get its unit/range if needed
                if (!$childTest) continue; // Should not happen due to validation

                // Find existing or create new RequestedResult
                // Using updateOrCreate is efficient for this.
                $requestedResult = RequestedResult::updateOrCreate(
                    [
                        'lab_request_id' => $labrequest->id,
                        'child_test_id' => $resultInput['child_test_id'],
                    ],
                    [
                        'patient_id' => $labrequest->pid,
                        'main_test_id' => $labrequest->main_test_id,
                        'result' => $resultInput['result'] ?? '',
                        'flags' => $resultInput['flags'] ?? null,
                        'result_comment' => $resultInput['comment'] ?? null,
                        'entered_by_user_id' => $userId,
                        'entered_at' => $now,
                        // Snapshot normal range and unit if not already done when placeholders were created
                        // Or if they can be updated during result entry (less common for locked-in ranges)
                        'normal_range' => $requestedResult->normal_range ?? // Keep existing if already set
                                          ($childTest->normalRange ?? 
                                          (($childTest->low !== null && $childTest->upper !== null) ? $childTest->low . ' - ' . $childTest->upper : null)),
                        'unit_name' => $requestedResult->unit_name ?? $childTest->unit?->name,
                        // Reset authorization if result is changed
                        'authorized_at' => null, 
                        'authorized_by_user_id' => null,
                    ]
                );
                $updatedResultIds[] = $requestedResult->id;
            }

            // Optionally, if results are removed from frontend, delete them from DB
            // This requires frontend to send ALL results, even empty ones, or a separate mechanism.
            // For now, we only update or create based on what's sent.
            // $labrequest->results()->whereNotIn('id', $updatedResultIds)->delete();


            // Update LabRequest's overall comment
            if ($request->has('main_test_comment')) {
                $labrequest->comment = $validatedData['main_test_comment'];
            }
            
            // Update LabRequest status based on results
            // This logic is crucial and depends on your workflow
            $allChildTestsCount = $labrequest->mainTest->childTests()->count();
            $enteredResultsCount = $labrequest->results()->whereNotNull('result')->count();

            if ($enteredResultsCount === 0) {
                // $labrequest->result_status = 'pending_entry'; // Example status
            } elseif ($enteredResultsCount < $allChildTestsCount) {
                // $labrequest->result_status = 'results_partial';
            } elseif ($enteredResultsCount >= $allChildTestsCount) {
                // $labrequest->result_status = 'results_complete';
                // $labrequest->done = true; // If 'done' means all results are in
            }
            // If you have a 'sample_received_at' field
            // if ($request->filled('sample_received_at')) {
            //    $labrequest->sample_received_at = Carbon::parse($request->sample_received_at);
            // }
            // If a sample_status field exists:
            // $labrequest->sample_status = 'processed';


            $labrequest->save(); // Save changes to LabRequest itself

            DB::commit();

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to save lab results for LabRequest ID {$labrequest->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل حفظ النتائج.', 'error' => 'حدث خطأ داخلي.'.$e->getMessage()], 500);
        }

        // Return the updated LabRequest with all necessary relations for the frontend to refresh
        return new LabRequestResource(
            $labrequest->fresh()->load([
                'mainTest.childTests.unit', 
                'mainTest.childTests.childGroup', 
                'mainTest.childTests.options',
                'results.childTest:id,child_test_name', // Load childTest for each result
                'results.enteredBy:id,name',
                'requestingUser:id,name'
            ])
        );
    }

    /**
     * Update specific flags or comments of a LabRequest.
     */
   /**
     * Update specific flags or comments of a LabRequest.
     */
    public function update(Request $request, LabRequest $labrequest)
    {
        $validatedData = $request->validate([
            'hidden' => 'sometimes|boolean',
            'is_lab2lab' => 'sometimes|boolean',
            'valid' => 'sometimes|boolean',
            'no_sample' => 'sometimes|boolean',
            'comment' => 'nullable|string|max:1000',
            'sample_id' => ['nullable','string','max:255', Rule::unique('labrequests')->ignore($labrequest->id)],
            'approve' => 'sometimes|boolean', // For insurance/admin approval of the request itself
            'discount_per' => 'sometimes|integer|min:0|max:100',
            'endurance' => 'sometimes|numeric|min:0',
            'is_bankak' => 'sometimes|boolean', // If payment method choice is saved before actual payment
            'count' => 'sometimes|integer|min:1', // If count is editable for a lab request
        ]);

        // Prevent updating financial fields if already paid or processed, unless specific permission
        // if (($request->has('price') || $request->has('discount_per') || $request->has('endurance')) && ($labrequest->is_paid || $labrequest->done) && !Auth::user()->can('override_paid_labrequest_financials')) {
        //     return response()->json(['message' => 'لا يمكن تعديل البيانات المالية لطلب مدفوع أو مكتمل.'], 403);
        // }

        $labrequest->update($validatedData);
        return new LabRequestResource($labrequest->load(['mainTest', 'requestingUser']));
    }

    /**
     * Cancel/Delete a lab request.
     */
    public function destroy(LabRequest $labrequest)
    {
        // ... (logic for cancellation/deletion with checks) ...
        return response()->json(null, 204);
    }

    /**
     * Record a full payment for a lab request.
     */
    public function recordPayment(Request $request, LabRequest $labrequest)
    {
        // Permission Check: e.g., can('record lab_payment')
        // if (!Auth::user()->can('record lab_payment')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        if ($labrequest->is_paid) {
            return response()->json(['message' => 'هذا الطلب مدفوع بالفعل.'], 400);
        }

        $validated = $request->validate([
            'is_bankak' => 'required|boolean', // Or 'is_bank' if that's your field name
            
            
            // 'payment_datetime' => 'nullable|date_format:Y-m-d H:i:s', // Optional: if payment time can be backdated
        ]);

        DB::beginTransaction();
        try {
            // Calculate net payable for this item to confirm the amount being settled
            $price = (float) $labrequest->price;
            $count = (int) ($labrequest->count ?? 1);
            $itemSubTotal = $price * $count;
            $discountAmount = ($itemSubTotal * ((int) ($labrequest->discount_per ?? 0) / 100));
            // Add fixed discount if applicable: + (float)($labrequest->fixed_discount_amount ?? 0);
            $enduranceAmount = (float) ($labrequest->endurance ?? 0);
            
            $netPayableByPatient = $itemSubTotal - $discountAmount - $enduranceAmount;
            
            // Amount being effectively "paid" now is the remaining balance to reach netPayableByPatient
            $amountBeingPaidNow = $netPayableByPatient - (float) $labrequest->amount_paid;

            if ($amountBeingPaidNow <= 0.009 && $labrequest->amount_paid > 0) { // Already paid or overpaid slightly (float issues)
                // If it's already considered paid (or very close to it), just ensure flags are set and return
                $labrequest->is_paid = true;
                $labrequest->amount_paid = $netPayableByPatient; // Ensure it's exact
                // $labrequest->payment_at = $request->input('payment_datetime', Carbon::now()); // If you add payment_at
                $labrequest->save();
                DB::commit();
                return new LabRequestResource($labrequest->load(['mainTest', 'requestingUser', 'depositUser']));
            }
            
            if ($amountBeingPaidNow <= 0.009) { // No balance due
                DB::rollBack(); // No actual payment to make
                return response()->json(['message' => 'لا يوجد مبلغ مستحق لهذا الطلب.'], 400);
            }


            // Update LabRequest directly
            $labrequest->amount_paid = $netPayableByPatient; // Mark as fully paid up to net patient payable
            $labrequest->is_paid = true;
            $labrequest->is_bankak = $validated['is_bankak']; // Store the method of this payment
            $labrequest->user_deposited = Auth::id();       // User who processed this payment
            // $labrequest->payment_at = $request->input('payment_datetime', Carbon::now()); // If you add a payment_at timestamp
            // $labrequest->payment_shift_id = $validated['shift_id']; // If you want to store the specific shift of payment
            
            $labrequest->save();

            // NO RequestedServiceDeposit::create(...) here for labrequest payments

            DB::commit();
            return new LabRequestResource($labrequest->load(['mainTest', 'requestingUser', 'depositUser']));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lab request payment failed for ID {$labrequest->id}: " . $e->getMessage());
            return response()->json(['message' => 'فشل تسجيل الدفعة.', 'error' => 'خطأ داخلي.'], 500);
        }
    }
    

    /**
     * Authorize all results for a lab request.
     */
    public function authorizeResults(Request $request, LabRequest $labrequest)
    {
        // ... (logic to check if all results entered, then update RequestedResult.authorized_at/by) ...
        // ... (update LabRequest status to 'authorized') ...
        return new LabRequestResource($labrequest->load(['mainTest.childTests.unit', 'results.authorizedBy', 'requestingUser']));
    }
}