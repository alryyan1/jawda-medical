<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorVisit;
use App\Models\LabRequest;
use App\Models\Setting; // If needed for generating sample IDs or other settings
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Http\Resources\PatientLabQueueItemResource; // We can reuse this for the queue
use App\Http\Resources\LabRequestResource; // For returning updated lab requests
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;

class SampleCollectionController extends Controller
{
    public function __construct()
    {
        // Add permissions as needed, e.g.,
        // $this->middleware('can:view sample_collection_queue')->only('getQueue');
        // $this->middleware('can:mark_sample_collected')->only(['markSampleCollected', 'markAllSamplesCollectedForVisit']);
        // $this->middleware('can:generate_sample_id')->only('generateSampleIdForRequest');
    }

    /**
     * Get the queue of patients/visits pending sample collection.
     */
    public function getQueue(Request $request)
    {
        $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $perPage = $request->input('per_page', 30);

        $query = DoctorVisit::query()
            ->select(
                'doctorvisits.id as visit_id',
                'doctorvisits.created_at as visit_creation_time',
                'patients.id as patient_id',
                'patients.name as patient_name',
                'patients.phone as patient_phone', // For potential WhatsApp formatting
                'patients.result_is_locked' // To pass to PatientLabRequestItem
            )
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
            ->whereHas('patientLabRequests', function ($q_lab) {
                $q_lab
                      
                      ->whereNull('sample_collected_at'); // Sample not yet collected
            })
            // Count of lab requests that need a sample for this visit
            ->withCount(['patientLabRequests as test_count' => function ($q_lab_count) {
                $q_lab_count
                          
                            ->whereNull('sample_collected_at');
            }])
         
            // Eager load to determine if all requests for the visit are paid (for badge color)
            ->with(['patientLabRequests' => function($q_lr_details) {
                $q_lr_details->select(['labrequests.id', 'pid', 'sample_id', 'is_paid', 'no_sample', 'sample_collected_at'])
                            
                             ->where('no_sample', false); // Only those needing samples for payment status context
            }]);


        if ($request->filled('shift_id')) {
            $query->where('doctorvisits.shift_id', $request->shift_id);
        } elseif ($request->filled('date_from') && $request->filled('date_to')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            $query->whereBetween('doctorvisits.created_at', [$dateFrom->toDateString(), $dateTo->toDateString()]);
        } else {
            $today = Carbon::today();
            $query->whereDate('doctorvisits.created_at', $today->toDateString());
        }

        if ($request->filled('search')) {
            // ... (search logic similar to LabRequestController@getLabPendingQueue) ...
            $searchTerm = $request->search;
            $query->where(function ($q_search) use ($searchTerm) {
                $q_search->where('patients.name', 'LIKE', "%{$searchTerm}%")
                         ->orWhere('doctorvisits.id', $searchTerm);
            });
        }
        
        $query->having('test_count', '>', 0); // Ensure visit still has samples to be collected

        $pendingSampleVisits = $query->get();
        
      

        return PatientLabQueueItemResource::collection($pendingSampleVisits);
    }

    /**
     * Mark a specific lab request's sample as collected.
     */
    public function markSampleCollected(Request $request, LabRequest $labrequest)
    {
        // if (!Auth::user()->can('mark_sample_collected', $labrequest)) { /* ... */ }

        if ($labrequest->no_sample) {
            return response()->json(['message' => 'This test does not require a sample.'], 400);
        }
        if ($labrequest->sample_collected_at) {
            return response()->json(['message' => 'Sample for this test has already been marked as collected.'], 409);
        }

        // Generate Sample ID if not present
        if (!$labrequest->sample_id) {
            $labrequest->sample_id = LabRequest::generateSampleId($labrequest->doctorVisit);
        }

        $labrequest->sample_collected_at = now();
        $labrequest->sample_collected_by_user_id = Auth::id();
        // Optionally update result_status
        if ($labrequest->result_status === 'pending_sample') {
            $labrequest->result_status = 'sample_received'; // Or 'pending_entry'
        }
        $labrequest->save();

        return new LabRequestResource($labrequest->load(['mainTest', 'sampleCollectedBy']));
    }

    /**
     * Mark all pending samples for a given visit as collected.
     */
    public function markAllSamplesCollectedForVisit(Request $request, DoctorVisit $visit)
    {
        // if (!Auth::user()->can('mark_sample_collected', $visit)) { /* ... */ }

        $updatedCount = 0;
        $labRequestsToUpdate = $visit->labRequests()
            ->where('valid', true)
            ->where('no_sample', false)
            ->whereNull('sample_collected_at')
            ->get();

        if ($labRequestsToUpdate->isEmpty()) {
            return response()->json(['message' => 'No pending samples to mark as collected for this visit.'], 404);
        }

        $userId = Auth::id();
        $now = now();

        DB::beginTransaction();
        try {
            foreach ($labRequestsToUpdate as $labrequest) {
                if (!$labrequest->sample_id) {
                    $labrequest->sample_id = LabRequest::generateSampleId($visit);
                }
                $labrequest->sample_collected_at = $now;
                $labrequest->sample_collected_by_user_id = $userId;
                if ($labrequest->result_status === 'pending_sample') {
                    $labrequest->result_status = 'sample_received';
                }
                $labrequest->save();
                $updatedCount++;
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Error marking all samples collected for visit {$visit->id}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to mark all samples as collected.'], 500);
        }

        return response()->json([
            'message' => "Successfully marked {$updatedCount} sample(s) as collected.",
            'updated_count' => $updatedCount,
            // Optionally return the updated visit or lab requests
            // 'visit' => new DoctorVisitResource($visit->fresh()->load('labRequests...'))
        ]);
    }

    /**
     * Generate and assign a Sample ID to a LabRequest if it doesn't have one.
     */
    public function generateSampleIdForRequest(Request $request, LabRequest $labrequest)
    {
        // if (!Auth::user()->can('generate_sample_id', $labrequest)) { /* ... */ }

        if ($labrequest->sample_id) {
            return response()->json(['message' => 'Sample ID already exists for this request.'], 409);
        }
        if ($labrequest->no_sample) {
            return response()->json(['message' => 'This test does not require a sample ID.'], 400);
        }

        $labrequest->sample_id = LabRequest::generateSampleId($labrequest->doctorVisit);
        $labrequest->save();

        return new LabRequestResource($labrequest);
    }
}