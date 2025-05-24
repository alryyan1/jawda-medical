<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
     * Save/Update results for a LabRequest.
     */
    public function saveResults(Request $request, LabRequest $labrequest)
    {
        // ... (validation and logic as defined before) ...
        // Ensure to return new LabRequestResource($labrequest->load(...all necessary relations for UI update...))
        return new LabRequestResource($labrequest->load(['mainTest.childTests.unit', 'results.childTest', 'requestingUser']));
    }

    /**
     * Update specific flags or comments of a LabRequest.
     */
    public function update(Request $request, LabRequest $labrequest)
    {
        // ... (validation for 'hidden', 'no_sample', 'valid', 'comment', 'sample_id' etc.)
        // ... (update logic) ...
        // e.g. $labrequest->update($request->only(['hidden', 'comment', 'no_sample', 'sample_id']));
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
     * Record a payment for a lab request.
     */
    public function recordPayment(Request $request, LabRequest $labrequest)
    {
        // ... (validation for amount, is_bankak, shift_id) ...
        // ... (logic to update labrequest.amount_paid, labrequest.is_paid, and potentially create a payment record) ...
        // This can be quite complex if you have a separate lab_request_payments table.
        // If updating labrequest directly:
        // ... (calculate balance, check overpayment) ...
        // $labrequest->amount_paid += $paymentAmount;
        // $labrequest->is_paid = ($labrequest->amount_paid >= $netPayable);
        // $labrequest->save();
        return new LabRequestResource($labrequest->load(['mainTest', 'requestingUser', 'depositUser']));
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