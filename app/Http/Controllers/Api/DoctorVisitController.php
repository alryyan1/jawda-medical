<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorVisit;
use App\Models\Patient;
use App\Models\Doctor;
use App\Models\Shift;
use Illuminate\Http\Request;
use App\Http\Resources\DoctorVisitResource;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Validation\Rule;

class DoctorVisitController extends Controller
{
    public function __construct()
    {
        // Define permissions like 'list visits', 'create visits', 'edit visit_status', etc.
        // $this->middleware('can:list doctor_visits')->only('index');
        // $this->middleware('can:view doctor_visits')->only('show');
        // $this->middleware('can:create doctor_visits')->only('store');
        // $this->middleware('can:update doctor_visits')->only('update'); // For general updates
        // $this->middleware('can:update visit_status')->only('updateStatus');
    }

    /**
     * Display a listing of doctor visits.
     * Useful for an admin overview page, with filters.
     */
    public function index(Request $request)
    {
        $query = DoctorVisit::with(['patient:id,name,phone', 'doctor:id,name', 'createdByUser:id,name'])
                            ->latest(); // Default order

        if ($request->filled('patient_id')) {
            $query->where('patient_id', $request->patient_id);
        }
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('visit_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('visit_date', '<=', $request->date_to);
        }
        // Add more filters as needed (shift_id, visit_type, etc.)

        $visits = $query->paginate($request->get('per_page', 15));
        return DoctorVisitResource::collection($visits);
    }

    /**
     * Store a newly created doctor visit.
     * This might be called directly or as part of Patient Registration.
     * If called from PatientController@store, that method might handle most of this.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'doctor_id' => 'required|exists:doctors,id',
            'shift_id' => 'required|exists:shifts,id', // Current general clinic shift
            'doctor_shift_id' => 'nullable|exists:doctor_shifts,id',
            // 'appointment_id' => 'nullable|exists:appointments,id|unique:doctor_visits,appointment_id',
            'file_id' => 'nullable|exists:files,id',
            'visit_date' => 'required|date_format:Y-m-d',
            'visit_time' => 'nullable|date_format:H:i:s',
            'status' => ['nullable', 'string', Rule::in(['waiting', 'with_doctor', 'lab_pending', 'imaging_pending', 'payment_pending', 'completed', 'cancelled', 'no_show'])],
            'visit_type' => 'nullable|string|max:100',
            'queue_number' => 'nullable|integer',
            'reason_for_visit' => 'nullable|string|max:1000',
            'visit_notes' => 'nullable|string',
            'is_new' => 'sometimes|boolean',
            'number' => 'sometimes|integer', // Original 'number' column
            'only_lab' => 'sometimes|boolean',
        ]);

        // Determine current open shift if not provided and required
        // $currentClinicShift = Shift::open()->latest()->first();
        // if (!$currentClinicShift && empty($validatedData['shift_id'])) {
        //     return response()->json(['message' => 'No open clinic shift available.'], 400);
        // }

        // Calculate the visit number before creating the visit
        $visitNumber = $validatedData['number'] ?? (
            isset($validatedData['patient_id'])
                ? (DoctorVisit::where('patient_id', $validatedData['patient_id'])->count() + 1)
                : 1
        );

        $visit = DoctorVisit::create([
            'patient_id' => $validatedData['patient_id'],
            'doctor_id' => $validatedData['doctor_id'],
            'user_id' => Auth::id(),
            'shift_id' => $validatedData['shift_id'], //?? $currentClinicShift->id,
            'doctor_shift_id' => $validatedData['doctor_shift_id'] ?? null,
            // 'appointment_id' => $validatedData['appointment_id'] ?? null,
            'file_id' => $validatedData['file_id'] ?? null,
            'visit_date' => $validatedData['visit_date'] ?? Carbon::today(),
            'visit_time' => $validatedData['visit_time'] ?? Carbon::now()->format('H:i:s'),
            'status' => $validatedData['status'] ?? 'waiting',
            'visit_type' => $validatedData['visit_type'] ?? 'New',
            'queue_number' => $validatedData['queue_number'] ?? null, // Implement queue logic if needed
            'reason_for_visit' => $validatedData['reason_for_visit'] ?? null,
            'visit_notes' => $validatedData['visit_notes'] ?? null,
            'is_new' => $validatedData['is_new'] ?? true,
            'number' => $visitNumber, // Example for 'number'
            'only_lab' => $validatedData['only_lab'] ?? false,
        ]);

        return new DoctorVisitResource($visit->load(['patient', 'doctor', 'createdByUser']));
    }

    /**
     * Display the specified doctor visit.
     */
    public function show(DoctorVisit $doctorVisit) // Route model binding
    {
        // Load all relevant data for displaying a single visit's details
        $doctorVisit->load(['patient', 'doctor', 'createdByUser', 'generalShift', 'doctorShift', 'requestedServices.service.serviceGroup']);
        return new DoctorVisitResource($doctorVisit);
    }

    /**
     * Update the specified doctor visit in storage.
     * (e.g., updating notes, status, clinical details related to the visit)
     */
    public function update(Request $request, DoctorVisit $doctorVisit)
    {
        $validatedData = $request->validate([
            // Allow updating specific fields of a visit
            'doctor_id' => 'sometimes|required|exists:doctors,id',
            'status' => ['sometimes','required', 'string', Rule::in(['waiting', 'with_doctor', 'lab_pending', 'imaging_pending', 'payment_pending', 'completed', 'cancelled', 'no_show'])],
            'visit_type' => 'nullable|string|max:100',
            'reason_for_visit' => 'nullable|string|max:1000',
            'visit_notes' => 'nullable|string',
            // Add other updatable fields like vitals if stored here, or clinical examination findings
        ]);

        $doctorVisit->update($validatedData);
        return new DoctorVisitResource($doctorVisit->load(['patient', 'doctor']));
    }

    /**
     * Update only the status of a doctor visit.
     * Useful for quick status changes from the clinic workspace.
     */
    public function updateStatus(Request $request, DoctorVisit $doctorVisit)
    {
        $validatedData = $request->validate([
            'status' => ['required', 'string', Rule::in(['waiting', 'with_doctor', 'lab_pending', 'imaging_pending', 'payment_pending', 'completed', 'cancelled', 'no_show'])],
        ]);

        $doctorVisit->update(['status' => $validatedData['status']]);

        // TODO: Trigger events if needed (e.g., VisitStatusUpdated for WebSockets)
        // event(new VisitStatusUpdated($doctorVisit));

        return new DoctorVisitResource($doctorVisit->load(['patient', 'doctor']));
    }


    /**
     * Remove the specified doctor visit from storage.
     * Use with caution. Usually, visits are 'cancelled' rather than deleted.
     */
    public function destroy(DoctorVisit $doctorVisit)
    {
        // Add checks: e.g., cannot delete if services are requested or payments made.
        if ($doctorVisit->requestedServices()->exists()) { // || $doctorVisit->payments()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف الزيارة لارتباطها بخدمات مطلوبة أو مدفوعات.'], 403);
        }
        $doctorVisit->delete();
        return response()->json(null, 204);
    }
}