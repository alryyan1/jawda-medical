<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\DoctorVisit;
use App\Models\Shift; // To get current shift for visit
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\PatientResource;
// use App\Http\Resources\PatientCollection; // If you have custom pagination
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PatientController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:list patients')->only('index');
        // $this->middleware('can:view patients')->only('show');
        // $this->middleware('can:create patients')->only('store');
        // $this->middleware('can:edit patients')->only('update');
        // $this->middleware('can:delete patients')->only('destroy');
    }

    /**
     * Display a listing of the patients.
     */
    public function index(Request $request)
    {
        $query = Patient::with(['company', 'primaryDoctor:id,name']); // Eager load common relations

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('id', $searchTerm); // Or patient file number if you have one
            });
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        // Add other filters as needed (e.g., gender)

        $patients = $query->latest()->paginate($request->get('per_page', 15));
        
        // return new PatientCollection($patients);
        return PatientResource::collection($patients);
    }

    /**
     * Store a newly created patient in storage.
     * This also creates an initial DoctorVisit for the clinic workflow.
     */
    public function store(StorePatientRequest $request)
    {
        $validatedPatientData = $request->safe()->except(['doctor_id', 'notes']); // doctor_id and notes are for visit
        $visitDoctorId = $request->input('doctor_id');
        $visitNotes = $request->input('notes'); // This was 'present_complains' effectively


        // --- Determine current active general clinic shift ---
        // This logic MUST be robust for production.
        // For now, assuming it's passed or we find the latest open one.
        $currentGeneralShift = Shift::open()->latest('created_at')->first();
        if (!$currentGeneralShift) {
            // If frontend is expected to know the shift and pass it:
            // $currentGeneralShiftId = $request->input('current_clinic_shift_id');
            // if (!$currentGeneralShiftId || !Shift::find($currentGeneralShiftId)?->isOpen()) {
            //      return response()->json(['message' => 'لا توجد وردية عمل مفتوحة حالياً أو الوردية المحددة غير صالحة.'], 400);
            // }
            // For now, simple error if no open shift found automatically
            return response()->json(['message' => 'لا توجد وردية عيادة مفتوحة حالياً لبدء زيارة.'], 400);
        }

        // --- Determine DoctorShift if `activeDoctorShift` was passed from frontend ---
        // The frontend selected an active doctor_shift from DoctorsTabs
        // We need doctor_shift_id to link the DoctorVisit correctly if your DoctorVisit has this FK
        // If `activeDoctorShift` from frontend is the `doctor_shifts.id`:
        $activeDoctorShiftId = $request->input('active_doctor_shift_id', null); // Frontend should send this


        DB::beginTransaction();
        try {
            $patient = Patient::create(array_merge($validatedPatientData, [
                'user_id' => Auth::id(), // User who registered the patient
                'shift_id' => $currentGeneralShift->id, // General shift of registration
                'present_complains' => $visitNotes ?? '', // Map form notes to present_complains
                // Defaulting NOT NULL fields for Patient model
                'visit_number' => 1, // This should be calculated based on previous visits if > 1
                'result_auth' => false,
                'auth_date' => now(),
                // ... set other patient defaults as in PatientFactory or model booted method ...
                'history_of_present_illness' => '', 'procedures' => '', 'provisional_diagnosis' => '',
                'bp' => '', 'temp' => 0, 'weight' => 0, 'height' => 0, 'discount' => 0,
                'drug_history' => '', 'family_history' => '', 'rbs' => '', 'doctor_finish' => false,
                'care_plan' => '', 'doctor_lab_request_confirm' => false, 'doctor_lab_urgent_confirm' => false,
                'general_examination_notes' => '', 'patient_medical_history' => '', 'social_history' => '',
                'allergies' => '', 'general' => '', 'skin' => '', 'head' => '', 'eyes' => '', 'ear' => '',
                'nose' => '', 'mouth' => '', 'throat' => '', 'neck' => '', 'respiratory_system' => '',
                'cardio_system' => '', 'git_system' => '', 'genitourinary_system' => '', 'nervous_system' => '',
                'musculoskeletal_system' => '', 'neuropsychiatric_system' => '', 'endocrine_system' => '',
                'peripheral_vascular_system' => '', 'referred' => '', 'discount_comment' => '',
            ]));

            // Create an initial DoctorVisit
            $doctorVisit = $patient->doctorVisits()->create([
                'doctor_id' => $visitDoctorId,
                'user_id' => Auth::id(), // User creating the visit
                'shift_id' => $currentGeneralShift->id, // General clinic shift
                'doctor_shift_id' => $request->get('doctor_shift_id'), // Link to specific doctor's work session
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'waiting', // Initial status for new registration
                'reason_for_visit' => $visitNotes, // Or a more specific field
                'is_new' => true, // Assuming this is for new patient registration context
                'number' => $patient->doctorVisits()->whereDate('visit_date', Carbon::today())->count(), // Simple daily queue number
            ]);

            DB::commit();

            // Return the patient resource, potentially with the initial visit loaded
            return new PatientResource($patient->loadMissing(['company', 'primaryDoctor', 'doctorVisits.doctor']));

        } catch (\Exception $e) {
            DB::rollBack();
            // Log::error("Patient registration and initial visit creation failed: " . $e->getMessage());
            return response()->json(['message' => 'فشل تسجيل المريض وإنشاء الزيارة الأولية.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified patient.
     */
    public function show(Patient $patient)
    {
        // Load necessary relations for a detailed patient view
        $patient->load([
            'company', 
            'subcompany', 
            'companyRelation', 
            'primaryDoctor:id,name', 
            'user:id,name', // User who registered
            'doctorVisits' => function ($query) { // Load last few visits for history preview
                $query->with('doctor:id,name')->latest()->limit(5);
            }
        ]);
        return new PatientResource($patient);
    }

    /**
     * Update the specified patient in storage.
     */
    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        $validatedData = $request->validated();
        // Exclude fields that are managed by other processes or shouldn't be mass updated here
        // For example, financial or specific clinical flags related to visits.
        
        $patient->update($validatedData);
        return new PatientResource($patient->loadMissing(['company', 'primaryDoctor']));
    }

    /**
     * Remove the specified patient from storage.
     */
    public function destroy(Patient $patient)
    {
        // Add extensive checks before deleting a patient. Soft deletes are highly recommended.
        // e.g., if patient has visits, lab requests, payments, etc.
        if ($patient->doctorVisits()->exists() /* || $patient->payments()->exists() || ... */) {
            return response()->json(['message' => 'لا يمكن حذف المريض لارتباطه بزيارات أو بيانات أخرى.'], 403);
        }
        // DB::transaction(function() use ($patient) {
            // Handle related data if necessary
            $patient->delete();
        // });
        return response()->json(null, 204);
    }
}