<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Models\Doctorvisit;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PatientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
   public function store(StorePatientRequest $request)
    {
        $validatedData = $request->validated();

        // --- Determine current active shift ---
        // This logic depends on how you manage shifts.
        // Placeholder: Fetch the latest open shift or a specific one.
        // $currentShift = Shift::where('is_closed', false)->orderBy('created_at', 'desc')->first();
        // if (!$currentShift) {
        //     return response()->json(['message' => 'لا يوجد وردية عمل مفتوحة حالياً.'], 400);
        // }
        // For testing, let's assume shift_id=1 until proper logic is in place
        $currentShiftId = $request->input('shift_id', 1); // Or get from authenticated user's current shift context

        DB::beginTransaction();
        try {
            // 1. Create Patient
            $patientData = [
                'name' => $validatedData['name'],
                'phone' => $validatedData['phone'],
                'gender' => $validatedData['gender'],
                'age_year' => $validatedData['age_year'],
                'age_month' => $validatedData['age_month'],
                'age_day' => $validatedData['age_day'],
                'address' => $validatedData['address'],
                'company_id' => $validatedData['company_id'],
                // Link the patient record to the user who registered them and the current shift
                'user_id' => Auth::id(),
                'shift_id' => $currentShiftId,
                // 'doctor_id' => $validatedData['doctor_id'], // The 'patients' table also has a doctor_id. Clarify its purpose.
                                                            // Is it the patient's primary doctor, or the doctor for *this* registration context?
                                                            // For now, I'm assuming the doctor_id on the form is for the *visit*.
                'present_complains' => $validatedData['notes'] ?? '', // Map form notes to present_complains

                // ---- Defaulting NOT NULL fields from patients table ----
                // These should ideally be nullable if not always provided, or have proper defaults in model/migration
                'visit_number' => 1, // Assuming first visit
                'result_auth' => false,
                'auth_date' => Carbon::now(), // Or null if not applicable at registration
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
            ];

            $patient = Patient::create($patientData);

            // 2. Create an initial DoctorVisit for this patient
            $doctorVisit = Doctorvisit::create([
                'patient_id' => $patient->id,
                'doctor_id' => $validatedData['doctor_id'],
                'user_id' => Auth::id(), // User creating the visit (receptionist)
                'shift_id' => $currentShiftId,
                'visit_date' => Carbon::today(),
                'status' => 'waiting', // Initial status
                'notes' => $validatedData['notes'] ?? null, // Or a specific field for visit notes
                // 'visit_type' => $validatedData['visit_type'] ?? 'New', // If you add visit_type
            ]);

            DB::commit();

            // Load relationships for the resource if needed by frontend immediately
            $patient->load(['doctor', 'company', 'user', 'shift', 'doctorVisits']);
            return new PatientResource($patient);

        } catch (\Exception $e) {
            DB::rollBack();
            // Log the error: Log::error('Patient registration failed: ' . $e->getMessage());
            return response()->json(['message' => 'حدث خطأ أثناء تسجيل المريض.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Patient $patient)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Patient $patient)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Patient $patient)
    {
        //
    }
}
