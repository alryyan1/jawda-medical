<?php

namespace App\Http\Controllers\Api;

use App\Events\LabPaymentUpdated;
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
            $query->where('doctorvisits.shift_id', $request->shift_id);
        
      


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

        // $query->withMin([
        //     'patientLabRequests as oldest_request_time_for_patient_in_context' => function ($lrQuery) use ($request) {

        //         // Re-apply filters for oldest time context
        //         if ($request->filled('package_id')) {
        //             $lrQuery->whereHas('mainTest', fn($mt) => $mt->where('pack_id', $request->package_id));
        //         }
        //         if ($request->boolean('has_unfinished_results')) {
        //             $lrQuery->whereHas('results', fn($r) => $r->where('result', '=', '')->orWhereNull('result'));
        //         }
        //         if ($request->filled('main_test_id')) {
        //             $lrQuery->where('labrequests.main_test_id', $request->main_test_id);
        //         }
        //         $lrQuery->whereDate('labrequests.created_at', DB::raw('DATE(doctorvisits.visit_date)'));
        //     }
        // ], 'labrequests.created_at');

        // This will fetch DoctorVisit records. The PatientLabQueueItemResource will need to correctly
        // extract/derive lab_request_ids, sample_id, and all_requests_paid based on the DoctorVisit and its Patient.
        $pendingVisits = $query->orderBy('doctorvisits.id', 'desc')->get();

        return PatientLabQueueItemResource::collection($pendingVisits);
    }

    /**
     * Get a single PatientLabQueueItem by visit_id
     */
    public function getSinglePatientLabQueueItem($visitId)
    {
        $visit = DoctorVisit::query()
            ->select(
                'doctorvisits.id as visit_id',
                'doctorvisits.created_at as visit_creation_time',
                'doctorvisits.visit_date',
                'patients.id as patient_id',
                'patients.name as patient_name'
            )
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
            ->where('doctorvisits.id', $visitId)
            ->whereHas('patientLabRequests') // Ensure the visit has lab requests
            ->first();

        if (!$visit) {
            return response()->json(['error' => 'Visit not found or has no lab requests'], 404);
        }

        return new PatientLabQueueItemResource($visit);
    }

    /**
     * Get patients ready for print (pending_result_count == 0)
     */
    public function getLabReadyForPrintQueue(Request $request)
    {
        $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
            'package_id' => 'nullable|integer|exists:packages,package_id',
            'main_test_id' => 'nullable|integer|exists:main_tests,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
        ]);

        $perPage = $request->input('per_page', 30);

        // Base query for DoctorVisit
        $query = DoctorVisit::query()
            ->select(
                'doctorvisits.id as visit_id',
                'doctorvisits.created_at as visit_creation_time',
                'doctorvisits.visit_date',
                'patients.id as patient_id',
                'patients.name as patient_name'
            )
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
            ->whereHas('patientLabRequests', function ($lrQuery) use ($request) {
                // Apply filters to lab requests
                if ($request->filled('package_id')) {
                    $lrQuery->whereHas('mainTest', function ($mtQuery) use ($request) {
                        $mtQuery->where('pack_id', $request->package_id);
                    });
                }

                if ($request->filled('main_test_id')) {
                    $lrQuery->where('labrequests.main_test_id', $request->main_test_id);
                }
            })
            ->whereHas('patientLabRequests.results', function ($resultsQuery) {
                // Ensure there are results
                $resultsQuery->whereNotNull('result')
                           ->where('result', '!=', '');
            })
            ->whereDoesntHave('patientLabRequests.results', function ($resultsQuery) {
                // Ensure no pending results (empty or null)
                $resultsQuery->where(function ($q) {
                    $q->whereNull('result')
                      ->orWhere('result', '=', '');
                });
            });

        // Apply shift filter
        if ($request->filled('shift_id')) {
            $query->where('doctorvisits.shift_id', $request->shift_id);
        }

        // Filter by Company
        if ($request->filled('company_id')) {
            $query->whereHas('patient', function ($q_pat) use ($request) {
                $q_pat->where('company_id', $request->company_id);
            });
        }

        // Filter by Doctor
        if ($request->filled('doctor_id')) {
            $query->where('doctorvisits.doctor_id', $request->doctor_id);
        }

        // Filter by Package
        if ($request->filled('package_id')) {
            $query->whereHas('patientLabRequests.mainTest', function ($q_mt) use ($request) {
                $q_mt->where('pack_id', $request->package_id);
            });
        }

        // Filter by Main Test
        if ($request->filled('main_test_id')) {
            $query->whereHas('patientLabRequests', function ($q_lr) use ($request) {
                $q_lr->where('main_test_id', $request->main_test_id);
            });
        }

        // Search filter
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('patients.name', 'like', "%{$searchTerm}%")
                  ->orWhere('patients.phone', 'like', "%{$searchTerm}%")
                  ->orWhere('doctorvisits.id', 'like', "%{$searchTerm}%");
            });
        }

        // Count lab requests for this visit
        $query->withCount([
            'patientLabRequests as test_count' => function ($lrQuery) use ($request) {
                if ($request->filled('package_id')) {
                    $lrQuery->whereHas('mainTest', fn($mt) => $mt->where('pack_id', $request->package_id));
                }
                if ($request->filled('main_test_id')) {
                    $lrQuery->where('labrequests.main_test_id', $request->main_test_id);
                }
            }
        ]);

        // Get the oldest request time for this visit
        $query->withMin('patientLabRequests', 'created_at', 'oldest_request_time');

        // Eager load relations needed by the resource
        $query->with(['patient', 'patientLabRequests.mainTest', 'patientLabRequests.results']);

        // Order by visit ID descending
        $readyForPrintVisits = $query->orderBy('doctorvisits.id', 'desc')->get();

        return PatientLabQueueItemResource::collection($readyForPrintVisits);
    }

    /**
     * Get patients with unfinished results (pending_result_count > 0)
     */
    public function getLabUnfinishedResultsQueue(Request $request)
    {
        $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
            'package_id' => 'nullable|integer|exists:packages,package_id',
            'main_test_id' => 'nullable|integer|exists:main_tests,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
        ]);

        $perPage = $request->input('per_page', 30);

        // Base query for DoctorVisit
        $query = DoctorVisit::query()
            ->select(
                'doctorvisits.id as visit_id',
                'doctorvisits.created_at as visit_creation_time',
                'doctorvisits.visit_date',
                'patients.id as patient_id',
                'patients.name as patient_name'
            )
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
            ->whereHas('patientLabRequests', function ($lrQuery) use ($request) {
                // Apply filters to lab requests
                if ($request->filled('package_id')) {
                    $lrQuery->whereHas('mainTest', function ($mtQuery) use ($request) {
                        $mtQuery->where('pack_id', $request->package_id);
                    });
                }

                if ($request->filled('main_test_id')) {
                    $lrQuery->where('labrequests.main_test_id', $request->main_test_id);
                }
            })
            ->whereHas('patientLabRequests.results', function ($resultsQuery) {
                // Ensure there are some pending results (empty or null)
                $resultsQuery->where(function ($q) {
                    $q->whereNull('result')
                      ->orWhere('result', '=', '');
                });
            });

        // Apply shift filter
        if ($request->filled('shift_id')) {
            $query->where('doctorvisits.shift_id', $request->shift_id);
        }

        // Filter by Company
        if ($request->filled('company_id')) {
            $query->whereHas('patient', function ($q_pat) use ($request) {
                $q_pat->where('company_id', $request->company_id);
            });
        }

        // Filter by Doctor
        if ($request->filled('doctor_id')) {
            $query->where('doctorvisits.doctor_id', $request->doctor_id);
        }

        // Filter by Package
        if ($request->filled('package_id')) {
            $query->whereHas('patientLabRequests.mainTest', function ($q_mt) use ($request) {
                $q_mt->where('pack_id', $request->package_id);
            });
        }

        // Filter by Main Test
        if ($request->filled('main_test_id')) {
            $query->whereHas('patientLabRequests', function ($q_lr) use ($request) {
                $q_lr->where('main_test_id', $request->main_test_id);
            });
        }

        // Search filter
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('patients.name', 'like', "%{$searchTerm}%")
                  ->orWhere('patients.phone', 'like', "%{$searchTerm}%")
                  ->orWhere('doctorvisits.id', 'like', "%{$searchTerm}%");
            });
        }

        // Count lab requests for this visit
        $query->withCount([
            'patientLabRequests as test_count' => function ($lrQuery) use ($request) {
                if ($request->filled('package_id')) {
                    $lrQuery->whereHas('mainTest', fn($mt) => $mt->where('pack_id', $request->package_id));
                }
                if ($request->filled('main_test_id')) {
                    $lrQuery->where('labrequests.main_test_id', $request->main_test_id);
                }
            }
        ]);

        // Get the oldest request time for this visit
        $query->withMin('patientLabRequests', 'created_at', 'oldest_request_time');

        // Eager load relations needed by the resource
        $query->with(['patient', 'patientLabRequests.mainTest', 'patientLabRequests.results']);

        // Order by visit ID descending
        $unfinishedVisits = $query->orderBy('doctorvisits.id', 'desc')->get();

        return PatientLabQueueItemResource::collection($unfinishedVisits);
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
            if ($request->filled('isBankak')) {
                // $q_lab->where('is_bankak', $request->boolean('is_bankak'));
                $query->whereHas('patientLabRequests', function ($q_lr) use ($request) {
                    $q_lr->where('is_bankak', $request->boolean('isBankak'));
                });
            }
        // Prioritize shift_id if provided
        if ($request->filled('shift_id')) {
            $query->where('doctorvisits.shift_id', $request->shift_id);
        } 
  // NEW Filter for referring doctor
    $query->where('patients.user_id', Auth::id());
    // --- APPLY FILTERS ON RELATED TABLES ---
    if ($request->filled('company_id')) {
        $query->where('patients.company_id', $request->company_id);
    }

    if ($request->filled('doctor_id')) {
        $query->where('patients.doctor_id', $request->doctor_id);
    }
    
    if ($request->filled('specialist_id')) {
        $query->whereHas('doctor.specialist', function($q_spec) use ($request) {
            $q_spec->where('id', $request->specialist_id);
        });
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
            // ->withMin('patientLabRequests', 'labrequests.created_at', 'oldest_request_time') // Get time of first request
            ->with(['patientLabRequests:labrequests.id,doctor_visit_id,is_paid']); // Eager load for status check

        // Log the raw SQL query
        // \Log::info('Lab Reception Queue SQL Query:', [
        //     'sql' => $query->toSql(),
        //     'bindings' => $query->getBindings(),
        //     'filters' => $request->all()
        // ]);
        
        $pendingVisits = $query->orderBy('doctorvisits.id', 'desc')->get();
    
        return PatientLabQueueItemResource::collection($pendingVisits);
    }


    /**
     * List lab requests for a specific visit. (For TestSelectionPanel)
     */
    public function indexForVisit(Request $request, DoctorVisit $visit)
    {
        $labRequests = $visit->patientLabRequests()
            ->with(['mainTest:id,main_test_name,price,container_id', 'mainTest.container:id,container_name', 'requestingUser:id,name'])
            ->orderBy('created_at', 'asc') // Or by main_test.name
            ->get();
        return LabRequestResource::collection($labRequests);
    }

    /**
     * Generate and download a barcode PDF for a specific container in a visit.
     */
    public function generateContainerBarcode(Request $request, DoctorVisit $visit, $containerId)
    {
        try {
            // Get the patient information
            $patient = $visit->patient;
            
            // Get container information
            $container = \App\Models\Container::find($containerId);
            if (!$container) {
                return response()->json(['message' => 'Container not found'], 404);
            }
            
            // Create barcode data (visit ID + container ID)
            $barcodeData = $visit->id . '-' . $containerId;
            
            // Generate PDF with barcode
            $pdf = new \App\Mypdf\Pdf();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            
            // Add patient information
            $pdf->Cell(0, 10, 'Patient: ' . $patient->name, 0, 1, 'C');
            $pdf->Cell(0, 10, 'Visit ID: ' . $visit->id, 0, 1, 'C');
            $pdf->Cell(0, 10, 'Container: ' . $container->container_name, 0, 1, 'C');
            $pdf->Ln(10);
            
            // Add barcode
            $pdf->write1DBarcode($barcodeData, 'C128', 50, 50, 100, 20, 0.4, [
                'position' => 'C',
                'border' => true,
                'padding' => 4,
                'fgcolor' => [0, 0, 0],
                'bgcolor' => [255, 255, 255],
                'text' => true,
                'font' => 'helvetica',
                'fontsize' => 8,
                'stretchtext' => 4
            ]);
            
            // Add barcode text
            $pdf->SetXY(50, 75);
            $pdf->SetFont('Arial', '', 10);
            $pdf->Cell(100, 10, $barcodeData, 0, 0, 'C');
            
            $filename = 'container_barcode_' . $visit->id . '_' . $containerId . '.pdf';
            
            return response($pdf->Output('S'), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $filename . '"'
            ]);
            
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error generating container barcode: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to generate barcode'], 500);
        }
    }

    // Removed: printAllSamples method (deprecated feature)

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
            $doctorvisitResource = new DoctorVisitResource($visit);
            $labCalculation = $doctorvisitResource->calculateFinancialSummary();
            $totalBalanceDueForAll += $labCalculation['balance_due'];
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

                $doctorvisitResource = new DoctorVisitResource($visit);
                $labCalculation = $doctorvisitResource->calculateLabRequestFinancials($labrequest, $visit->patient->company_id ? true : false);
                $netPayableByPatient = $labCalculation['net_payable'];



                // Create a deposit record if you have a lab_request_deposits table
                // LabRequestDeposit::create([
                //     'lab_request_id' => $labrequest->id,
                //     'amount' => $paymentForThisItem,
                //     'is_bank' => $isBankak,
                //     'user_id' => $userId,
                //     'shift_id' => $currentShiftId,
                //     'notes' => $request->input('payment_notes'), // Overall note for all payments in this batch
                // ]);

                $labrequest->amount_paid += 0;
                $labrequest->is_bankak = $isBankak; // Set payment method for the latest payment part
                $labrequest->user_deposited = $userId;
                $labrequest->is_paid = true;

            
                $labrequest->save();
                $paidRequestsCount++;
                $remainingPaymentToDistribute -= $netPayableByPatient;
            }
            DB::commit();

            // It's better to return the updated visit with all its lab requests
            // so the frontend can update everything at once.
            return new DoctorVisitResource($visit->fresh()->load([
                'patientLabRequests.mainTest',
                'patientLabRequests.requestingUser:id,name',
                'patientLabRequests.depositUser:id,name',
                'patient.doctor'
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
                    // $labrequest->authorized_at = null;
                    // $labrequest->authorized_by_user_id = null;
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
                if($mainTest->available == false){
                    return response()->json(['message' => 'الفحص غير متوفر.'], 400);
                }

                $price = $mainTest->price;
                $endurance = 0;
                $approve = true; // Default approval

                if ($company) {
                    $contract = $company->contractedMainTests()
                        ->where('main_tests.id', $mainTestId)
                        ->first();
                        if(!$contract->pivot->status ){
                            return response()->json(['message' => 'الفحص غير مفعل في العقد الموقع مع الشركة.'], 400);
                        }
                    if ($contract && $contract->pivot->status) {
                        $price = $contract->pivot->price;
                        $approve = $contract->pivot->approve;
                        if ($contract->pivot->use_static) {
                            $endurance = $contract->pivot->endurance_static;
                        } else {
                            if($contract->pivot->endurance_percentage > 0 ) {
                                    //log here
                                    Log::info('endurance_percentage: ' . $contract->pivot->endurance_percentage);
                                    //log the contract
                                    Log::info('contract: ' . $contract);
                                $amount_company_will_endure = ($price * $contract->pivot->endurance_percentage) / 100;
                                $endurance = $price - $amount_company_will_endure;
                            } else{
                                 if($patient->relation != null){
                                $amount_company_will_endure = ($price * $patient->relation->lab_endurance) / 100;
                                $endurance = $price - $amount_company_will_endure;
                            }else{
                                if($patient->subcompany_id != null){
                                    $amount_company_will_endure = ($price * $patient->subcompany->lab_endurance) / 100;
                                    $endurance = $price - $amount_company_will_endure;
                                }else{
                                    Log::info('patient->company: ' . $patient->company);
                                    Log::info('patient->company->lab_endurance: ' . $patient->company->lab_endurance);
                                    $amount_company_will_endure = ($price * $patient->company->lab_endurance) / 100;
                                    $endurance = $price - $amount_company_will_endure;
                                }

                            }
                            } 
                              
                            
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
                    'normalRange' => $existingResult ? $existingResult->normal_range : null,
                    'normal_range' => $existingResult ? $existingResult->normal_range : null,
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
                    'entered_at' => null,
                ];
            })->all(),
        ];
        return response()->json(['data' => $mainTestWithChildrenResults]);
    }


    /**
     * Update a single requested result (autosave).
     */
    public function saveSingleResult(Request $request, RequestedResult $requestedResult)
    {
        // ... (Authorization checks) ...
      

        
        $requestedResult->update([
            'result' => $request->input('result'),
        ]);

        return new RequestedResultResource($requestedResult->load(['childTest.unit', /* 'enteredBy' */]));
    }

    /**
     * Update only the normal range for a specific child test result
     */
    public function updateNormalRange(Request $request, LabRequest $labrequest, ChildTest $childTest)
    {
        // Authorization checks
        if ($labrequest->main_test_id !== $childTest->main_test_id) {
            return response()->json(['message' => 'Child test does not belong to this lab request.'], 422);
        }

        $validated = $request->validate([
            'normal_range' => 'required|string|max:1000',
        ]);

        // Find or create the requested result
        $requestedResult = RequestedResult::updateOrCreate(
            [
                'lab_request_id' => $labrequest->id,
                'child_test_id' => $childTest->id,
            ],
            [
                'patient_id' => $labrequest->pid,
                'main_test_id' => $labrequest->main_test_id,
                'result' => '', // Default empty result
                'normal_range' => $validated['normal_range'],
                'unit_id' => $childTest->unit_id,
            ]
        );

        // Update only the normal_range field
        $requestedResult->update([
            'normal_range' => $validated['normal_range']
        ]);

        return new RequestedResultResource($requestedResult->load(['childTest.unit']));
    }

    public function generateLabThermalReceiptPdf(Request $request, DoctorVisit $visit)
    {
        // Permission Check: e.g., can('print lab_receipt', $visit)
        // if (!Auth::user()->can('print lab_receipt', $visit)) { /* ... */ }

        $labRequestsToPrint = $visit->patientLabRequests;

        if ($labRequestsToPrint->isEmpty()) {
            return response()->json(['message' => 'لا توجد طلبات مختبر مدفوعة لهذه الزيارة لإنشاء إيصال.'], 404);
        }

        // Load necessary relationships
        $visit->load([
            'patient:id,name,phone,company_id,insurance_no,guarantor,visit_number',
            'patient.company:id,name',
            'patient.subcompany:id,name',
            'patient.companyRelation:id,name',
            'patientLabRequests.mainTest:id,main_test_name',
            'patientLabRequests.depositUser:id,name',
            'user:id,name',
            'doctor:id,name'
        ]);

        // Convert collection to array for the PDF class
        $labRequestsArray = $labRequestsToPrint->map(function ($lr) {
            return [
                'id' => $lr->id,
                'count' => $lr->count,
                'price' => $lr->price,
                'discount_per' => $lr->discount_per,
                'endurance' => $lr->endurance,
                'amount_paid' => $lr->amount_paid,
                'main_test' => [
                    'main_test_name' => $lr->mainTest?->main_test_name
                ],
                'deposit_user' => [
                    'name' => $lr->depositUser?->name
                ]
            ];
        })->toArray();

        // Generate PDF using the dedicated class
        $pdf = new \App\Services\Pdf\LabThermalReceipt($visit, $labRequestsArray);
        $pdfContent = $pdf->generate();

        // Output PDF
        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $visit->patient->name);
        $pdfFileName = 'LabReceipt_Visit_' . $visit->id . '_' . $patientNameSanitized . '.pdf';

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
        //start transaction
        DB::beginTransaction(); 
        try {
            $deleted = $labrequest->delete();
            if ($deleted) {
                $labrequest->results()->delete();
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to delete lab request: " . $e->getMessage());
            return response()->json(['message' => 'فشل حذف الطلب.' . $e->getMessage()], 500);
        }
        return response()->json(null, 204);
    }

    /**
     * Update discount for a lab request.
     */
    public function updateDiscount(Request $request, LabRequest $labrequest)
    {
        $validated = $request->validate([
            'discount_per' => 'required|integer|min:0|max:100',
        ]);

        $labrequest->update([
            'discount_per' => $validated['discount_per']
        ]);

        return new LabRequestResource($labrequest->load(['mainTest', 'requestingUser']));
    }

    /**
     * Pay all lab requests for a visit.
     */
    public function payAllLabRequests(Request $request, DoctorVisit $visit)
    {
        // Get all unpaid lab requests for this visit
        $unpaidRequests = $visit->patientLabRequests()
            ->where('is_paid', false)
            ->get();

        if ($unpaidRequests->isEmpty()) {
            return response()->json(['message' => 'جميع طلبات المختبر لهذه الزيارة مدفوعة بالفعل.'], 400);
        }

        DB::beginTransaction();
        try {
            foreach ($unpaidRequests as $labrequest) {
                // Calculate net payable for this item
          
                $doctorvisitResource = new DoctorVisitResource($visit);
                $labCalculation = $doctorvisitResource->calculateLabRequestFinancials($labrequest, $visit->patient->company_id ? true : false);
                $netPayableByPatient = $labCalculation['net_payable'];
                // Mark as fully paid
                $labrequest->amount_paid = $netPayableByPatient;
                $labrequest->is_paid = true;
                $labrequest->is_bankak = false; // Default to cash payment
                $labrequest->user_deposited = Auth::id();
                $labrequest->save();
            }

            DB::commit();

            return new DoctorVisitResource($visit->fresh()->load([
                'patientLabRequests.mainTest',
                'patientLabRequests.requestingUser:id,name',
                'patientLabRequests.depositUser:id,name'
            ]));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Pay all lab requests failed for Visit ID {$visit->id}: " . $e->getMessage());
            return response()->json(['message' => 'فشل دفع جميع طلبات المختبر.', 'error' => 'خطأ داخلي.'], 500);
        }
    }

    /**
     * Cancel payment for a lab request.
     */
    public function cancelPayment(Request $request, LabRequest $labrequest)
    {
        if (!$labrequest->is_paid) {
            return response()->json(['message' => 'هذا الطلب غير مدفوع.'], 400);
        }

        $labrequest->is_paid = false;
        $labrequest->amount_paid = 0;
        $labrequest->user_deposited = null;
        $labrequest->save();

        return new LabRequestResource($labrequest->load(['mainTest', 'requestingUser', 'depositUser']));
    }

    /**
     * Toggle is_bankak field for a lab request.
     */
    public function toggleBankak(Request $request, LabRequest $labrequest)
    {
        $validated = $request->validate([
            'is_bankak' => 'required|boolean',
        ]);

        $labrequest->update([
            'is_bankak' => $validated['is_bankak']
        ]);

        return new LabRequestResource($labrequest->load(['mainTest', 'requestingUser']));
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
            $itemSubTotal = $price; // Each lab request represents one test
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
            return response()->json(['message' => 'فشل تسجيل الدفعة.', 'error' => 'خطأ داخلي.', 'error_message' => $e->getMessage()], 500);
        }
    }


    /**
     * Update all lab requests' is_bankak to 1 for a specific visit.
     */
    public function updateAllLabRequestsBankak(Request $request, DoctorVisit $visit)
    {
        // Permission check: can user update lab request payment methods?
        // if (!Auth::user()->can('update lab_request_payment_methods', $visit)) {
        //     return response()->json(['message' => 'Unauthorized to update payment methods.'], 403);
        // }

        $validated = $request->validate([
            'is_bankak' => 'required|boolean',
        ]);

        $isBankak = (bool) $validated['is_bankak'];

        DB::beginTransaction();
        try {
            // Update all lab requests for this visit
            $updatedCount = $visit->patientLabRequests()
                ->update(['is_bankak' => $isBankak]);

            DB::commit();

            // Return the updated visit with all its lab requests
            return new DoctorVisitResource($visit->fresh()->load([
                'patientLabRequests.mainTest',
                'patientLabRequests.requestingUser:id,name',
                'patientLabRequests.depositUser:id,name'
            ]));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Update all lab requests bankak failed for Visit ID {$visit->id}: " . $e->getMessage());
            return response()->json(['message' => 'فشل تحديث طريقة الدفع لجميع طلبات المختبر.', 'error' => 'خطأ داخلي.', 'error_details' => $e->getMessage()], 500);
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

    /**
     * Update the comment for a lab request
     */
    public function updateComment(Request $request, LabRequest $labrequest)
    {
        $validated = $request->validate([
            'comment' => 'required|string|max:1000',
        ]);

        $labrequest->update([
            'comment' => $validated['comment']
        ]);

        return new LabRequestResource($labrequest->load(['mainTest', 'requestingUser']));
    }
}