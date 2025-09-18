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
use App\Models\Shift;
use App\Models\Patient;

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
            
            'search' => 'nullable|string|max:100',
            'page' => 'nullable|integer|min:1',
           
        ]);


        $query = DoctorVisit::query()
            ->select(
                'doctorvisits.id as visit_id',
                'doctorvisits.created_at as visit_creation_time',
                'patients.id as patient_id',
                'patients.name as patient_name',
                'patients.phone as patient_phone', // For potential WhatsApp formatting
                'patients.result_is_locked', // To pass to PatientLabRequestItem
                'patients.age_year',
                'patients.age_month',
                'patients.age_day',
                'doctors.name as doctor_name'
            )
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
            ->leftJoin('doctors', 'doctorvisits.doctor_id', '=', 'doctors.id')
           
            // Count of lab requests that need a sample for this visit
            ->withCount(['patientLabRequests as test_count']);
       
                $shift = Shift::max('id');
            $query->where('doctorvisits.shift_id', $shift);
        

        if ($request->filled('search')) {
            // ... (search logic similar to LabRequestController@getLabPendingQueue) ...
            $searchTerm = $request->search;
            $query->where(function ($q_search) use ($searchTerm) {
                $q_search->where('patients.name', 'LIKE', "%{$searchTerm}%")
                         ->orWhere('doctorvisits.id', $searchTerm);
            });
        }
        
        $query->having('test_count', '>', 0); // Ensure visit still has samples to be collected

        $pendingSampleVisits = $query->orderBy('doctorvisits.id', 'desc')->get();
        
      

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

    /**
     * Mark the visit's patient as sample collected and set collection time.
     */
    public function markPatientSampleCollectedForVisit(Request $request, DoctorVisit $visit)
    {
        $patient = $visit->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient not found for this visit'], 404);
        }

        $patient->sample_collected = true;
        $patient->sample_collect_time = now();
        $patient->save();

        return response()->json([
            'message' => 'Patient sample marked as collected successfully',
            'patient_id' => $patient->id,
            'sample_collected' => (bool) $patient->sample_collected,
            'sample_collect_time' => $patient->sample_collect_time,
        ]);
    }
}