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
use App\Http\Resources\RequestedResultResource;
use App\Models\CbcBinding;
use App\Models\Setting;
use App\Models\SysmexResult;
use App\Services\Pdf\MyCustomTCPDF;
// If you create a specific resource for MainTestWithChildrenResults:
// use App\Http\Resources\MainTestWithChildrenResultsResource; 
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; // For logging errors
use Illuminate\Support\Facades\Schema;

class LabRequestController extends Controller
{
    public function __construct()
    {
        // Apply middleware for permissions. Adjust permission names as needed.
        // $this->middleware('can:list users')->only('index', 'getRolesList'); // Added getRolesList here too
        // $this->middleware('can:view users')->only('show');
        // $this->middleware('can:create users')->only('store');
        // $this->middleware('can:edit users')->only('update', 'updatePassword'); // Added updatePassword
        // $this->middleware('can:delete users')->only('destroy');
        // 'assign roles' permission is typically checked within store/update methods if request has roles
    }
    // Example in LabRequestController or VisitServiceController
// app/Http/Controllers/Api/LabRequestController.php
    public function clearPendingRequests(Request $request, DoctorVisit $visit)
    {
        // $this->authorize('cancel_multiple_lab_requests', $visit); // Permission
        $count = $visit->patientLabRequests()->delete();

        if ($count > 0) {
            // Invalidate relevant caches or trigger events if needed
            return response()->json(['message' => "تم إلغاء {$count} طلب فحص  بنجاح.", 'deleted_count' => $count]);
        }
        return response()->json(['message' => 'لا توجد طلبات فحص  قابلة للإلغاء لهذه الزيارة.', 'deleted_count' => 0]);
    }

    /**
     * Get the queue of patients with pending lab work.
     */
    // public function getLabPendingQueue(Request $request)
    // {
    //     $request->validate([
    //         'date_from' => 'nullable|date_format:Y-m-d',
    //         'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
    //         'shift_id' => 'nullable|integer|exists:shifts,id',
    //         'search' => 'nullable|string|max:100',
    //         'page' => 'nullable|integer|min:1',
    //         'per_page' => 'nullable|integer|min:5|max:100',
    //     ]);


    //     $query = DoctorVisit::query()
    //         ->select('doctorvisits.id as visit_id', 
    //                  'doctorvisits.created_at as visit_creation_time',
    //                  'patients.id as patient_id', 
    //                  'patients.name as patient_name'
    //         )
    //         ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
    //         // ->whereHas('patient.labRequests')
    //         // ->withCount('patient.labRequests as test_count')

    //         ->whereHas('patientLabRequests')
    //         ->withCount('patientLabRequests as test_count')
    //         ->withMin('patientLabRequests as oldest_request_time', 'created_at')
    //         ->with(['patientLabRequests']);

    //     if ($request->filled('shift_id')) {
    //         $query->where('doctorvisits.shift_id', $request->shift_id);
    //     }

    //     if ($request->filled('search')) {
    //         $searchTerm = $request->search;
    //         $query->where(function ($q_search) use ($searchTerm) {
    //             $q_search->where('patients.name', 'LIKE', "%{$searchTerm}%")
    //                      ->orWhere('patients.id', $searchTerm)
    //                      ->orWhereExists(function ($subQuery) use ($searchTerm) {
    //                         $subQuery->select(DB::raw(1))
    //                                  ->from('labrequests as lr_search_sub') // Use a different alias
    //                                  ->whereColumn('lr_search_sub.doctor_visit_id', 'doctorvisits.id')
    //                                  ->where(function ($lrInnerSearch) use ($searchTerm) {
    //                                     $lrInnerSearch->where('lr_search_sub.sample_id', 'LIKE', "%{$searchTerm}%")
    //                                                   ->orWhere('lr_search_sub.id', $searchTerm);
    //                                  });
    //                      });
    //         });
    //     }

    //     // This condition might be important to ensure only visits with truly pending tests are shown
    //     // It depends on how 'test_count' and the whereHas('labRequests'...) are defined.
    //     // $query->having('test_count', '>', 0); 

    //     $pendingVisits = $query->orderBy('doctorvisits.id','desc')->get();
    //     // return['sql' => $query->toSql(),'binds' => $query->getBindings()];

    //     return PatientLabQueueItemResource::collection($pendingVisits);
    // }
    public function getLabPendingQueue(Request $request)
    {
        $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'search' => 'nullable|string|max:100', // Patient name/ID, Visit ID, Sample ID, Lab Request ID
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
            'package_id' => 'nullable|integer|exists:packages,package_id',
            'has_unfinished_results' => 'nullable|boolean',
            'main_test_id' => 'nullable|integer|exists:main_tests,id',
        ]);

        $perPage = $request->input('per_page', 30);

        // The core of the queue is still a "DoctorVisit" because it represents an encounter.
        // We then filter these visits to only include those where the patient has relevant lab requests.
        $query = DoctorVisit::query()
            ->select(
                'doctorvisits.id as visit_id', // This is the ID of the DoctorVisit encounter
                'doctorvisits.created_at as visit_creation_time',
                'doctorvisits.visit_date', // Keep for context
                'patients.id as patient_id',
                'patients.name as patient_name'
                // Add other patient fields if needed by PatientLabQueueItemResource
            )
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
            // Ensure this visit's patient has lab requests that match the criteria
            ->whereHas('patientLabRequests', function ($lrQuery) use ($request) {




                // Apply NEW FILTERS to the lab requests
                if ($request->filled('package_id')) {
                    $lrQuery->whereHas('mainTest', function ($mtQuery) use ($request) {
                        $mtQuery->where('pack_id', $request->package_id);
                    });
                }

                if ($request->boolean('has_unfinished_results')) {
                    $lrQuery->whereHas('results', function ($resQuery) {
                        $resQuery->where(function ($q_empty_res) {
                            $q_empty_res->where('result', '=', '')->orWhereNull('result');
                        });
                    });
                }

                if ($request->filled('main_test_id')) {
                    $lrQuery->where('labrequests.main_test_id', $request->main_test_id);
                }
            });

        // Context for DoctorVisit itself (shift or date range)
        if ($request->filled('shift_id')) {
            $query->where('doctorvisits.shift_id', $request->shift_id);
        } elseif ($request->filled('date_from') && $request->filled('date_to')) {
            $query->whereBetween('doctorvisits.visit_date', [
                Carbon::parse($request->date_from)->startOfDay(),
                Carbon::parse($request->date_to)->endOfDay()
            ]);
        } else {
            $query->whereDate('doctorvisits.visit_date', Carbon::today());
        }


        // Filter by Main Test
        if ($request->filled('main_test_id')) {
            $query->whereHas('patientLabRequests', function ($q_lr) use ($request) {
                $q_lr->where('main_test_id', $request->main_test_id);
            });
        }

        // Filter by Package
        if ($request->filled('package_id')) {
            $query->whereHas('patientLabRequests.mainTest', function ($q_mt) use ($request) {
                $q_mt->where('pack_id', $request->package_id);
            });
        }

        // Filter by Company
        if ($request->filled('company_id')) {
            $query->whereHas('patient', function ($q_pat) use ($request) {
                $q_pat->where('company_id', $request->company_id);
            });
        }

        // Filter by Doctor (referring doctor of the visit)
        if ($request->filled('doctor_id')) {
            $query->where('doctorvisits.doctor_id', $request->doctor_id);
        }

        // Filter by Result Status (this is complex for a visit with multiple lab requests)
// You might need an aggregated status on the visit or a more complex subquery.
// Simple example: Show visit if *any* lab request matches the status.
        if ($request->filled('result_status_filter') && $request->result_status_filter !== 'all') {
            $query->whereHas('patientLabRequests', function ($q_lr) use ($request) {
                if ($request->result_status_filter === 'finished') {
                    $q_lr->whereIn('result_status', ['authorized', 'results_complete_pending_auth']);
                } elseif ($request->result_status_filter === 'pending') {
                    $q_lr->whereNotIn('result_status', ['authorized', 'results_complete_pending_auth', 'cancelled']);
                }
            });
        }

        // Filter by Print Status (Patient.result_print_date)
        if ($request->filled('print_status_filter') && $request->print_status_filter !== 'all') {
            $query->whereHas('patient', function ($q_pat) use ($request) {
                if ($request->print_status_filter === 'printed') {
                    $q_pat->whereNotNull('result_print_date');
                } elseif ($request->print_status_filter === 'not_printed') {
                    $q_pat->whereNull('result_print_date');
                }
            });
        }

        // Search functionality on DoctorVisit and related Patient/LabRequest attributes
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q_search) use ($searchTerm) {
                $q_search->where('patients.name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('patients.id', $searchTerm) // Patient ID
                    ->orWhere('doctorvisits.id', $searchTerm) // Visit ID
                    ->orWhereHas('patientLabRequests', function ($lrSearchQuery) use ($searchTerm) {
                        $lrSearchQuery->where('labrequests.sample_id', 'LIKE', "%{$searchTerm}%")
                            ->orWhere('labrequests.id', $searchTerm); // LabRequest ID
                    });
            });
        }

        // Eager load data needed for PatientLabQueueItemResource after filtering
        // This uses the hypothetical 'patientLabRequests' relation on DoctorVisit model.
        // If it doesn't exist, we have to construct this data manually or adjust the resource.
        // The resource will effectively re-query lab requests for the patient within context.
        // For now, let's assume the resource can handle getting the specific LRs.

        // Aggregates for display in the queue item (these define the "work unit" for the patient in context)
        // We need to count lab requests for *this specific visit's patient* that match the filters.
        $query->withCount([
            'patientLabRequests as test_count' => function ($lrQuery) use ($request) {

                // Re-apply filters to the count
                if ($request->filled('package_id')) {
                    $lrQuery->whereHas('mainTest', fn($mt) => $mt->where('pack_id', $request->package_id));
                }
                if ($request->boolean('has_unfinished_results')) {
                    $lrQuery->whereHas('results', fn($r) => $r->where('result', '=', '')->orWhereNull('result'));
                }
                if ($request->filled('main_test_id')) {
                    $lrQuery->where('labrequests.main_test_id', $request->main_test_id);
                }
                // Contextual filter for count based on how lab requests are tied to this visit's context
                // (e.g., created on same day as visit_date)
    
            }
        ]);

        $query->withMin([
            'patientLabRequests as oldest_request_time_for_patient_in_context' => function ($lrQuery) use ($request) {

                // Re-apply filters for oldest time context
                if ($request->filled('package_id')) {
                    $lrQuery->whereHas('mainTest', fn($mt) => $mt->where('pack_id', $request->package_id));
                }
                if ($request->boolean('has_unfinished_results')) {
                    $lrQuery->whereHas('results', fn($r) => $r->where('result', '=', '')->orWhereNull('result'));
                }
                if ($request->filled('main_test_id')) {
                    $lrQuery->where('labrequests.main_test_id', $request->main_test_id);
                }
                $lrQuery->whereDate('labrequests.created_at', DB::raw('DATE(doctorvisits.visit_date)'));
            }
        ], 'labrequests.created_at');

        // This will fetch DoctorVisit records. The PatientLabQueueItemResource will need to correctly
        // extract/derive lab_request_ids, sample_id, and all_requests_paid based on the DoctorVisit and its Patient.
        $pendingVisits = $query->orderBy('doctorvisits.id', 'desc')->get();

        return PatientLabQueueItemResource::collection($pendingVisits);
    }

 /**
     * Get the queue of patients registered through the lab for a specific shift or day.
     * This method shows ALL lab-specific visits, even those without tests added yet.
     */
    public function getNewlyRegisteredLabPendingQueue(Request $request)
    {
        // if (!Auth::user()->can('view lab_reception_queue')) { /* ... */ }

        $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        // This query fetches DoctorVisits marked as 'only_lab'
        $query = DoctorVisit::query()
            ->select(
                'doctorvisits.id as visit_id',
                'doctorvisits.created_at as visit_creation_time',
                'patients.id as patient_id',
                'patients.name as patient_name'
            )
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id');
            // ->where('doctorvisits.only_lab', true); // ** THE KEY FILTER **

        // Prioritize shift_id if provided
        if ($request->filled('shift_id')) {
            $query->where('doctorvisits.shift_id', $request->shift_id);
        } 

        // Apply search filter
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q_search) use ($searchTerm) {
                $q_search->where('patients.name', 'LIKE', "%{$searchTerm}%")
                         ->orWhere('patients.id', $searchTerm)
                         ->orWhere('doctorvisits.id', $searchTerm);
            });
        }
        
        // Eager load details needed for the PatientLabQueueItemResource
        $query->withCount(['patientLabRequests as test_count']) // Count all lab requests
            ->withMin(['patientLabRequests as oldest_request_time'], 'labrequests.created_at') // Get time of first request
            ->with(['patientLabRequests:labrequests.id,doctor_visit_id,sample_id,is_paid']); // Eager load for status check

        $pendingVisits = $query->orderBy('doctorvisits.id', 'desc')->get();
    
        return PatientLabQueueItemResource::collection($pendingVisits);
    }


    /**
     * List lab requests for a specific visit. (For TestSelectionPanel)
     */
    public function indexForVisit(Request $request, DoctorVisit $visit)
    {
        $labRequests = $visit->patientLabRequests()
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
        $requestedTestIds = $visit->patient()->pluck('main_test_id')->toArray();
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
            'shift_id' => ['required', 'integer', 'exists:shifts,id', Rule::exists('shifts', 'id')->where('is_closed', false)],
            'payment_notes' => 'nullable|string|max:255', // Optional overall payment note
        ]);

        $totalPaymentAmount = (float) $validated['total_payment_amount'];
        $isBankak = (bool) $validated['is_bankak'];
        $currentShiftId = $validated['shift_id'];
        $userId = Auth::id();

        // Get all unpaid lab requests for this visit, ordered (e.g., by creation date)
        $unpaidRequests = $visit->patientLabRequests()
            ->where('is_paid', false)
            ->orderBy('created_at', 'asc') // Pay oldest first
            ->get();

        if ($unpaidRequests->isEmpty()) {
            return response()->json(['message' => 'جميع طلبات المختبر لهذه الزيارة مدفوعة بالفعل.'], 400);
        }

        $totalBalanceDueForAll = 0;
        foreach ($unpaidRequests as $lr) {
            $price = (float) $lr->price;
            $count = (int) ($lr->count ?? 1);
            $itemSubTotal = $price;
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
                if ($remainingPaymentToDistribute <= 0)
                    break;

                $price = (float) $labrequest->price;
                $count = (int) ($labrequest->count ?? 1);
                $itemSubTotal = $price * $count;
                $discountAmount = ($itemSubTotal * ((int) ($labrequest->discount_per ?? 0) / 100));
                $enduranceAmount = (float) ($labrequest->endurance ?? 0);
                $netPayableByPatient = $itemSubTotal - $discountAmount - ($visit->patient->company_id ? $enduranceAmount : 0);

                $balanceForItem = $netPayableByPatient - (float) $labrequest->amount_paid;

                if ($balanceForItem <= 0)
                    continue; // Already paid or overpaid somehow

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
                'patientLabRequests.mainTest',
                'patientLabRequests.requestingUser:id,name',
                'patientLabRequests.depositUser:id,name'
            ]));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Batch lab request payment failed for Visit ID {$visit->id}: " . $e->getMessage());
            return response()->json(['message' => 'فشل تسجيل الدفعة المجمعة.', 'error' => 'خطأ داخلي.', 'error_details' => $e->getMessage()], 500);
        }
    }


    /**
     * Reset all child test results for a given LabRequest to their default values.
     */
    public function setDefaultResults(Request $request, LabRequest $labrequest)
    {
        // Authorization check: e.g., can user edit results for this lab request?
        // if (!Auth::user()->can('edit lab_results', $labrequest)) {
        //     return response()->json(['message' => 'Unauthorized to modify results.'], 403);
        // }
        // if ($labrequest->approve && !Auth::user()->can('edit_authorized_lab_results')) {
        //    return response()->json(['message' => 'Cannot reset results for an authorized request without specific permission.'], 403);
        // }


        DB::beginTransaction();
        try {
            $updatedResultsCount = 0;
            // Eager load childTest to access defval
            foreach ($labrequest->results()->with('childTest')->get() as $requestedResult) {
                if ($requestedResult->childTest) {
                    $defaultValue = $requestedResult->childTest->defval ?? ''; // Use empty string if defval is null

                    // Only update if the current result is different from the default
                    // or if you want to force an update (e.g., to reset entered_by/at)
                    if ($requestedResult->result !== $defaultValue /* || any_other_condition_to_force_update */) {
                        $requestedResult->result = $defaultValue;
                        // If you have tracking fields, you might want to update them or clear authorization
                        // $requestedResult->entered_by_user_id = Auth::id();
                        // $requestedResult->entered_at = now();
                        // $requestedResult->flags = null; // Reset flags
                        // $requestedResult->result_comment = null; // Reset comment
                        // $requestedResult->authorized_at = null;
                        // $requestedResult->authorized_by_user_id = null;
                        $requestedResult->save();
                        $updatedResultsCount++;
                    }
                }
            }

            // Update overall LabRequest status if needed
            if ($updatedResultsCount > 0 || $labrequest->results()->doesntExist()) { // If any result changed or no results (all were blank)
                // If all results are now effectively empty (matching their defval, which might be empty)
                $allChildTestsCount = $labrequest->mainTest->childTests()->count();
                $nonEmptyResultsCount = $labrequest->results()->where(fn($q) => $q->whereNotNull('result')->where('result', '!=', ''))->count();

                if ($nonEmptyResultsCount === 0) {
                    $labrequest->result_status = 'pending_entry';
                } elseif ($nonEmptyResultsCount < $allChildTestsCount) {
                    $labrequest->result_status = 'results_partial';
                } else { // All results have some value (even if it's the default)
                    $labrequest->result_status = 'results_complete_pending_auth';
                }

                // If resetting to default implies un-authorizing the main request
                if ($labrequest->approve) {
                    $labrequest->approve = false;
                    $labrequest->authorized_at = null;
                    $labrequest->authorized_by_user_id = null;
                }
                $labrequest->saveQuietly();
            }

            DB::commit();

            // Return the updated LabRequest with its results
            return new LabRequestResource(
                $labrequest->fresh()->load([
                    'mainTest.childTests.unit',
                    'results.childTest.unit',
                    // 'results.enteredBy' // if you have it
                ])
            );

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error resetting results to default for LabRequest ID {$labrequest->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to reset results to default values.', 'error' => $e->getMessage()], 500);
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
                if (!$mainTest)
                    continue;

                $alreadyExists = $visit->patientLabRequests()->where('main_test_id', $mainTestId)->exists();
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
                    'hidden' => $request->input('hidden', true), // Assuming default is visible
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
            'mainTest.childTests.unit',
            'mainTest.childTests.childGroup',
            'mainTest.childTests.options',
            'patient:id,name,phone,gender,age_year,age_month,age_day,company_id', // Load company_id for patient
            'patient.company:id,name', // Load patient's company if exists
            'requestingUser:id,name',
            'depositUser:id,name',
            'results.childTest:id,child_test_name',
            'results.enteredBy:id,name',
            'results.authorizedBy:id,name',
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
                    'childTests.unit:id,name',
                    'childTests.childGroup:id,name',
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
                    'low' => $childTest->low,
                    'upper' => $childTest->upper,
                    'defval' => $childTest->defval,
                    'unit_id' => $childTest->unit_id,
                    'unit_name' => $childTest->unit->name ?? null,
                    'unit' => $childTest->unit ? ['id' => $childTest->unit->id, 'name' => $childTest->unit->name] : null,
                    'normalRange' => $childTest->normalRange,
                    'max' => $childTest->max,
                    'lowest' => $childTest->lowest,
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
     * Update a single requested result (autosave).
     */
    public function saveSingleResult(Request $request, LabRequest $labrequest, ChildTest $childTest)
    {
        // ... (Authorization checks) ...
        if ($labrequest->main_test_id !== $childTest->main_test_id) {
            return response()->json(['message' => 'Child test does not belong to this lab request.'], 422);
        }

        $validated = $request->validate([
            'result_value' => 'nullable|string|max:65000', // MySQL TEXT can hold more
            // 'result_flags' => 'nullable|string|max:50', // Not in new schema
            // 'result_comment' => 'nullable|string|max:500', // Not in new schema
            'normal_range_text' => 'nullable|string|max:1000', // For the snapshot
            'unit_id_from_input' => 'nullable|integer|exists:units,id', // If unit can be overridden per result
        ]);

        $unitIdToSave = $validated['unit_id_from_input'] ?? $childTest->unit_id;
        $normalRangeToSave = $validated['normal_range_text'] ?? $childTest->normalRange ??
            (($childTest->low !== null && $childTest->upper !== null) ? $childTest->low . ' - ' . $childTest->upper : 'N/A');


        $requestedResult = RequestedResult::updateOrCreate(
            [
                'lab_request_id' => $labrequest->id,
                'child_test_id' => $childTest->id,
            ],
            [
                'patient_id' => $labrequest->pid,
                'main_test_id' => $labrequest->main_test_id,
                'result' => $validated['result_value'] ?? '',
                'normal_range' => $normalRangeToSave,
                'unit_id' => $unitIdToSave,
                // 'entered_by_user_id' => Auth::id(), // Add if re-introducing these fields
                // 'entered_at' => now(),             // Add if re-introducing these fields
            ]
        );

        // ... (Update LabRequest status logic as before) ...
        $expectedChildTestsCount = $labrequest->mainTest->childTests()->count();
        $enteredResultsCount = $labrequest->results()->where(fn($q) => $q->whereNotNull('result')->where('result', '!=', ''))->count();

        if ($enteredResultsCount === 0) {
            $labrequest->result_status = 'pending_entry';
        } elseif ($enteredResultsCount < $expectedChildTestsCount) {
            $labrequest->result_status = 'results_partial';
        } elseif ($enteredResultsCount >= $expectedChildTestsCount) {
            $labrequest->result_status = 'results_complete_pending_auth'; // Or 'results_complete' if no separate auth step
        }
        $labrequest->saveQuietly();


        return new RequestedResultResource($requestedResult->load(['childTest.unit', /* 'enteredBy' */]));
    }
    public function generateLabThermalReceiptPdf(Request $request, DoctorVisit $visit)
    {
        // Permission Check: e.g., can('print lab_receipt', $visit)
        // if (!Auth::user()->can('print lab_receipt', $visit)) { /* ... */ }

        $visit->load([
            'patient:id,name,phone,company_id',
            'patient.company:id,name',
            'labRequests' => function ($query) {
                $query->where('is_paid', true) // Typically only paid items on receipt
                    ->orWhere('amount_paid', '>', 0); // Or partially paid
            },
            'labRequests.mainTest:id,main_test_name',
            'labRequests.depositUser:id,name', // User who handled deposit if tracked per request
            'user:id,name', // User who created the visit (receptionist)
            'doctor:id,name', // Doctor of the visit
        ]);

        $labRequestsToPrint = $visit->labRequests;

        if ($labRequestsToPrint->isEmpty()) {
            return response()->json(['message' => 'لا توجد طلبات مختبر مدفوعة لهذه الزيارة لإنشاء إيصال.'], 404);
        }

        $appSettings = Setting::instance();
        $isCompanyPatient = !empty($visit->patient->company_id);
        $cashierName = Auth::user()?->name ?? $visit->user?->name ?? $labRequestsToPrint->first()?->depositUser?->name ?? 'النظام';


        // --- PDF Instantiation with Thermal Defaults ---
        $pdf = new MyCustomTCPDF('إيصال مختبر', "زيارة رقم: {$visit->id}");
        $thermalWidth = (float) ($appSettings?->thermal_printer_width ?? 76); // Get from settings
        $pdf->setThermalDefaults($thermalWidth); // Set narrow width, small margins, basic font
        $pdf->AddPage();

        $fontName = $pdf->getDefaultFontFamily();
        $isRTL = $pdf->getRTL(); // Should be true for Arabic
        $alignStart = $isRTL ? 'R' : 'L';
        $alignEnd = $isRTL ? 'L' : 'R';
        $alignCenter = 'C';
        $lineHeight = 3.5; // Small line height for thermal

        // --- Clinic/Company Header ---
        // (Simplified version of your generateThermalServiceReceipt header)
        $logoData = null;
        if ($appSettings?->logo_base64 && str_starts_with($appSettings->logo_base64, 'data:image')) {
            try {
                $logoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $appSettings->logo_base64));
            } catch (\Exception $e) {
            }
        }

        if ($logoData) {
            $pdf->Image('@' . $logoData, '', $pdf->GetY() + 1, 15, 0, '', '', 'T', false, 300, $alignCenter, false, false, 0, false, false, false);
            $pdf->Ln($logoData ? 10 : 1); // Adjust Ln based on logo height
        }

        $pdf->SetFont($fontName, 'B', $logoData ? 8 : 9);
        $pdf->MultiCell(0, $lineHeight, $appSettings?->hospital_name ?: ($appSettings?->lab_name ?: config('app.name')), 0, $alignCenter, false, 1);

        $pdf->SetFont($fontName, '', 6);
        if ($appSettings?->address)
            $pdf->MultiCell(0, $lineHeight - 0.5, $appSettings->address, 0, $alignCenter, false, 1);
        if ($appSettings?->phone)
            $pdf->MultiCell(0, $lineHeight - 0.5, ($isRTL ? "هاتف: " : "Tel: ") . $appSettings->phone, 0, $alignCenter, false, 1);
        if ($appSettings?->vatin)
            $pdf->MultiCell(0, $lineHeight - 0.5, ($isRTL ? "ر.ض: " : "VAT: ") . $appSettings->vatin, 0, $alignCenter, false, 1);

        $pdf->Ln(1);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(1);

        // --- Receipt Info ---
        $pdf->SetFont($fontName, '', 6.5);
        $receiptNumber = "LAB-" . $visit->id . "-" . $labRequestsToPrint->first()?->id;
        $pdf->Cell(0, $lineHeight, ($isRTL ? "إيصال رقم: " : "Receipt #: ") . $receiptNumber, 0, 1, $alignStart);
        $pdf->Cell(0, $lineHeight, ($isRTL ? "زيارة رقم: " : "Visit #: ") . $visit->id, 0, 1, $alignStart);
        $pdf->Cell(0, $lineHeight, ($isRTL ? "التاريخ: " : "Date: ") . Carbon::now()->format('Y/m/d H:i A'), 0, 1, $alignStart);
        $pdf->Cell(0, $lineHeight, ($isRTL ? "المريض: " : "Patient: ") . $visit->patient->name, 0, 1, $alignStart);
        if ($visit->patient->phone)
            $pdf->Cell(0, $lineHeight, ($isRTL ? "الهاتف: " : "Phone: ") . $visit->patient->phone, 0, 1, $alignStart);
        if ($isCompanyPatient && $visit->patient->company)
            $pdf->Cell(0, $lineHeight, ($isRTL ? "الشركة: " : "Company: ") . $visit->patient->company->name, 0, 1, $alignStart);
        if ($visit->doctor)
            $pdf->Cell(0, $lineHeight, ($isRTL ? "الطبيب: " : "Doctor: ") . $visit->doctor->name, 0, 1, $alignStart);
        $pdf->Cell(0, $lineHeight, ($isRTL ? "الكاشير: " : "Cashier: ") . $cashierName, 0, 1, $alignStart);

        // Barcode for Visit ID or a specific Lab Request ID
        if ($appSettings?->barcode && $labRequestsToPrint->first()?->id) {
            $pdf->Ln(1);
            $barcodeValue = (string) $labRequestsToPrint->first()->id; // Or a composite ID
            $style = ['position' => '', 'align' => 'C', 'stretch' => false, 'fitwidth' => true, 'cellfitalign' => '', 'border' => false, 'hpadding' => 'auto', 'vpadding' => 'auto', 'fgcolor' => [0, 0, 0], 'bgcolor' => false, 'text' => true, 'font' => $fontName, 'fontsize' => 5, 'stretchtext' => 4];
            $pdf->write1DBarcode($barcodeValue, 'C128B', '', '', '', 10, 0.3, $style, 'N');
            $pdf->Ln(1);
        }

        $pdf->Ln(1);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(0.5);

        // --- Items Table ---
        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        // Adjust for thermal: Name takes most, Qty small, Price, Total
        $nameWidth = $pageUsableWidth * 0.50;
        $qtyWidth = $pageUsableWidth * 0.10;
        $priceWidth = $pageUsableWidth * 0.20;
        $totalWidth = $pageUsableWidth * 0.20;

        $pdf->SetFont($fontName, 'B', 6.5); // Bold for item headers
        $pdf->Cell($nameWidth, $lineHeight, ($isRTL ? 'البيان' : 'Item'), 'B', 0, $alignStart);
        $pdf->Cell($qtyWidth, $lineHeight, ($isRTL ? 'كمية' : 'Qty'), 'B', 0, $alignCenter);
        $pdf->Cell($priceWidth, $lineHeight, ($isRTL ? 'سعر' : 'Price'), 'B', 0, $alignCenter);
        $pdf->Cell($totalWidth, $lineHeight, ($isRTL ? 'إجمالي' : 'Total'), 'B', 1, $alignCenter);
        $pdf->SetFont($fontName, '', 6.5);

        $subTotalLab = 0;
        $totalDiscountOnLab = 0;
        $totalEnduranceOnLab = 0;

        foreach ($labRequestsToPrint as $lr) {
            $testName = $lr->mainTest?->main_test_name ?? 'فحص غير معروف';
            $quantity = (int) ($lr->count ?? 1);
            $unitPrice = (float) ($lr->price ?? 0);
            $itemGrossTotal = $unitPrice * $quantity;
            $subTotalLab += $itemGrossTotal;

            $itemDiscountPercent = (float) ($lr->discount_per ?? 0);
            $itemDiscountAmount = ($itemGrossTotal * $itemDiscountPercent) / 100;
            $totalDiscountOnLab += $itemDiscountAmount;

            $itemNetAfterDiscount = $itemGrossTotal - $itemDiscountAmount;

            $itemEndurance = 0;
            if ($isCompanyPatient) {
                $itemEndurance = (float) ($lr->endurance ?? 0) * $quantity;
                $totalEnduranceOnLab += $itemEndurance;
            }

            // For display in table, show gross total before endurance/patient payment for clarity
            $currentYbeforeMultiCell = $pdf->GetY();
            $pdf->MultiCell($nameWidth, $lineHeight - 0.5, $testName, 0, $alignStart, false, 0, '', '', true, 0, false, true, 0, 'T');
            $yAfterMultiCell = $pdf->GetY();
            $pdf->SetXY($pdf->getMargins()['left'] + $nameWidth, $currentYbeforeMultiCell); // Reset X and Y for subsequent cells

            $pdf->Cell($qtyWidth, $lineHeight - 0.5, $quantity, 0, 0, $alignCenter);
            $pdf->Cell($priceWidth, $lineHeight - 0.5, number_format($unitPrice, 2), 0, 0, $alignCenter);
            $pdf->Cell($totalWidth, $lineHeight - 0.5, number_format($itemGrossTotal, 2), 0, 1, $alignCenter);
            $pdf->SetY(max($yAfterMultiCell, $currentYbeforeMultiCell + $lineHeight - 0.5)); // Ensure Y moves past tallest cell
        }
        $pdf->Ln(0.5);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(0.5);

        // --- Totals Section ---
        $pdf->SetFont($fontName, '', 7);

        $this->drawThermalTotalRow($pdf, ($isRTL ? 'إجمالي الفحوصات:' : 'Subtotal:'), $subTotalLab, $pageUsableWidth);
        if ($totalDiscountOnLab > 0) {
            $this->drawThermalTotalRow($pdf, ($isRTL ? 'إجمالي الخصم:' : 'Discount:'), -$totalDiscountOnLab, $pageUsableWidth, false, 'text-red-500'); // No bold, is reduction
        }

        $netAfterDiscount = $subTotalLab - $totalDiscountOnLab;
        // $pdf->drawThermalTotalRow($pdf, ($isRTL ? 'الصافي بعد الخصم:' : 'Net After Discount:'), $netAfterDiscount, $pageUsableWidth);

        if ($isCompanyPatient && $totalEnduranceOnLab > 0) {
            $this->drawThermalTotalRow($pdf, ($isRTL ? 'تحمل الشركة:' : 'Company Share:'), -$totalEnduranceOnLab, $pageUsableWidth, false, 'text-blue-500');
        }

        $netPayableByPatient = $netAfterDiscount - ($isCompanyPatient ? $totalEnduranceOnLab : 0);
        $pdf->SetFont($fontName, 'B', 7.5); // Bold for final amount
        $this->drawThermalTotalRow($pdf, ($isRTL ? 'صافي المطلوب من المريض:' : 'Patient Net Payable:'), $netPayableByPatient, $pageUsableWidth, true);
        $pdf->SetFont($fontName, '', 7);

        // Sum amount_paid from the $labRequestsToPrint collection for what was actually paid for these items
        $totalActuallyPaidForTheseLabs = $labRequestsToPrint->sum('amount_paid');
        $this->drawThermalTotalRow($pdf, ($isRTL ? 'المبلغ المدفوع:' : 'Amount Paid:'), $totalActuallyPaidForTheseLabs, $pageUsableWidth);

        $balanceDueForTheseLabs = $netPayableByPatient - $totalActuallyPaidForTheseLabs;
        $pdf->SetFont($fontName, 'B', 7.5);
        $this->drawThermalTotalRow($pdf, ($isRTL ? 'المبلغ المتبقي:' : 'Balance Due:'), $balanceDueForTheseLabs, $pageUsableWidth, true);

        $pdf->Ln(2);
        if ($appSettings?->show_water_mark) { // Watermark
            $pdf->SetFont($fontName, 'B', 30);
            $pdf->SetTextColor(220, 220, 220);
            $pdf->Rotate(45, $pdf->GetX() + ($pageUsableWidth / 3), $pdf->GetY() + 10); // Adjust X,Y for watermark position
            $pdf->Text($pdf->GetX() + ($pageUsableWidth / 4), $pdf->GetY(), $isCompanyPatient ? $visit->patient->company->name : "PAID");
            $pdf->Rotate(0);
            $pdf->SetTextColor(0, 0, 0);
        }

        $pdf->Ln(3);
        $pdf->SetFont($fontName, 'I', 6);
        $footerMessage = $appSettings?->receipt_footer_message ?: ($isRTL ? 'شكراً لزيارتكم!' : 'Thank you for your visit!');
        $pdf->MultiCell(0, $lineHeight - 1, $footerMessage, 0, $alignCenter, false, 1);
        $pdf->Ln(3); // Extra space at the end for cutting

        // --- Output PDF ---
        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $visit->patient->name);
        $pdfFileName = 'LabReceipt_Visit_' . $visit->id . '_' . $patientNameSanitized . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S'); // S returns as string

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }

    // Helper from your existing ReportController (ensure it's accessible or duplicate here)
    protected function drawThermalTotalRow(MyCustomTCPDF $pdf, string $label, float $value, float $pageUsableWidth, bool $isBoldValue = false, string $valueCssClass = '')
    {
        $fontName = $pdf->getDefaultFontFamily();
        $currentFontSizePt = $pdf->getFontSizePt();
        $currentFontStyle = $pdf->getFontStyle();
        $lineHeight = 3.5;

        $labelWidth = $pageUsableWidth * 0.60;
        $valueWidth = $pageUsableWidth * 0.40;
        $isRTL = $pdf->getRTL();
        $alignStart = $isRTL ? 'R' : 'L';
        $alignEnd = $isRTL ? 'L' : 'R';

        if ($isBoldValue)
            $pdf->SetFont($fontName, 'B', $currentFontSizePt + 0.5);
        // Could add color logic based on valueCssClass if TCPDF supported it easily here

        $pdf->Cell($labelWidth, $lineHeight, $label, 0, 0, $alignStart);
        $pdf->Cell($valueWidth, $lineHeight, number_format($value, 2), 0, 1, $alignEnd);

        if ($isBoldValue)
            $pdf->SetFont($fontName, $currentFontStyle, $currentFontSizePt);
    }

    /**
     * Save/Update ALL results for a given LabRequest.
     * This is kept if you still want a way to submit all results at once,
     * but primary interaction is now single field autosave.
     */
    public function saveResults(Request $request, LabRequest $labrequest)
    {
        // ... (Authorization and validation as before, but adapt to new schema)
        // Example simplified validation for the new schema:
        $validatedData = $request->validate([
            'results' => 'present|array',
            'results.*.child_test_id' => ['required', 'integer', Rule::exists('child_tests', 'id')->where('main_test_id', $labrequest->main_test_id)],
            'results.*.result_value' => 'nullable|string|max:65000',
            'results.*.normal_range_text' => 'required|string|max:1000', // Now normal_range is NOT NULL
            'results.*.unit_id_from_input' => 'nullable|integer|exists:units,id', // From input
            'main_test_comment' => 'nullable|string|max:2000',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validatedData['results'] as $resultInput) {
                $childTest = ChildTest::find($resultInput['child_test_id']); // Get child test for its default unit if needed
                RequestedResult::updateOrCreate(
                    [
                        'lab_request_id' => $labrequest->id,
                        'child_test_id' => $resultInput['child_test_id'],
                    ],
                    [
                        'patient_id' => $labrequest->pid,
                        'main_test_id' => $labrequest->main_test_id,
                        'result' => $resultInput['result_value'] ?? '',
                        'normal_range' => $resultInput['normal_range_text'], // Must be provided
                        'unit_id' => $resultInput['unit_id_from_input'] ?? $childTest?->unit_id,
                        // 'entered_by_user_id' => Auth::id(), 'entered_at' => now(), // If re-adding
                    ]
                );
            }
            // ... (update labrequest comment and status as before) ...
            if ($request->has('main_test_comment')) {
                $labrequest->comment = $validatedData['main_test_comment'];
            }
            // ... (status update logic) ...
            $labrequest->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Full saveResults Error: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل حفظ النتائج.', 'error' => $e->getMessage()], 500);
        }
        return new LabRequestResource($labrequest->fresh()->load(['mainTest.childTests.unit', 'results.childTest.unit', /* 'results.enteredBy' */]));
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
            'sample_id' => ['nullable', 'string', 'max:255', Rule::unique('labrequests')->ignore($labrequest->id)],
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
        $labrequest->delete();
        return response()->json(null, 204);
    }

    public function unpay(LabRequest $labrequest)
    {
        $labrequest->is_paid = false;
        $labrequest->amount_paid = 0;
        $labrequest->save();
        return new LabRequestResource($labrequest->load(['mainTest', 'requestingUser', 'depositUser']));
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
    public function populateCbcResultsFromSysmex(Request $request, LabRequest $labrequest)
    {
        $doctorvisit = Doctorvisit::find($request->get('doctor_visit_id_for_sysmex'));
        $patient = $doctorvisit->patient;
        $main_test_id = $request->get('main_test_id');
        $sysmex = SysmexResult::where('doctorvisit_id', $doctorvisit->id)
            ->orderBy('id', 'desc')
            ->first();
        if ($sysmex == null) {
            return ['status' => false, 'message' => 'no data found'];
        }
        $bindings = CbcBinding::all();
        $object = null;

        // return ['patient'=>$patient->id,'main_test_id'=>$main_test_id];
        foreach ($bindings as $binding) {
            $object[$binding->name_in_sysmex_table] = [
                'child_id' => [$binding->child_id_array],
                'result' => $sysmex[$binding->name_in_sysmex_table]
            ];
            $child_array = explode(',', $binding->child_id_array);
            foreach ($child_array as $child_id) {
                $requested_result = RequestedResult::where('child_test_id', $child_id)->where('main_test_id', '=', $main_test_id)->where('patient_id', '=', $patient->id)->first();
                if ($requested_result != null) {
                    // return ['status' => false, 'message' => 'requested result found'];

                    $requested_result->update(['result' => $sysmex[$binding->name_in_sysmex_table]]);
                }
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'CBC results populated successfully.',
            'data' => new LabRequestResource($labrequest), // Assuming loadDefaultRelations loads what UI needs
            'cbcObj' => $object // Your debug object
        ]);
    }
}