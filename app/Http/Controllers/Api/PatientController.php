<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Doctorvisit;
use App\Models\File;
use App\Models\Patient;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $currentUser = Auth::user();

        // Determine current active general clinic shift (replace with your actual logic)
        $currentGeneralShift = Shift::open()->latest('created_at')->first();
        if (!$currentGeneralShift) {
            return response()->json(['message' => 'لا يوجد وردية عمل عامة مفتوحة حالياً للعيادة.'], 400);
        }
        $currentGeneralShiftId = $currentGeneralShift->id;
        
        // The doctor_id for the visit, coming from the form payload
        $visitDoctorId = $validatedData['doctor_id'];


        DB::beginTransaction();
        try {
            $patientFile = null;
            $isNewPatientFile = false;

            // 1. Check for existing patient by phone number
            // Normalize phone number if needed (e.g., remove spaces, leading zeros, add country code)
            $normalizedPhone = preg_replace('/[^0-9]/', '', $validatedData['phone']); // Basic normalization
            
            // Find the LATEST patient record with this phone number to get their file_id
            // This assumes a patient's file_id doesn't change.
            $existingPatientWithFile = Patient::where('phone', $normalizedPhone)
                                              ->whereNotNull('file_id') // Ensure they have a file assigned
                                              ->orderBy('created_at', 'desc') // Get the most recent record if multiple match phone
                                              ->first();

            if ($existingPatientWithFile) {
                $patientFile = $existingPatientWithFile->file; // Get the existing MedicalFile model
                // $this->command->info("Existing patient found by phone. Using File ID: {$patientFile->id}");

                // Optional: Further verification if name is also provided and somewhat matches.
                // This can be complex due to name variations. For an API, phone might be enough.
                // if (strtolower($existingPatientWithFile->name) !== strtolower($validatedData['name'])) {
                //     // Log a warning or return a specific response if names significantly differ
                //     Log::warning("Potential patient mismatch: Phone {$normalizedPhone} exists with name '{$existingPatientWithFile->name}', new registration for '{$validatedData['name']}'.");
                // }

            } else {
                // No existing patient with this phone OR existing ones don't have a file_id (data inconsistency).
                // Create a new file record.
                $patientFile = File::create(); // Creates a new row in 'files' table
                $isNewPatientFile = true;
                // $this->command->info("New patient or no existing file. Created new File ID: {$patientFile->id}");
            }

            if (!$patientFile) { // Should not happen if logic above is correct
                throw new \Exception('Failed to retrieve or create a patient file.');
            }

            // 2. Create the new Patient record, linking to the file_id
            $patientData = [
                'file_id' => $patientFile->id,
                'name' => $validatedData['name'],
                'phone' => $normalizedPhone, // Store normalized phone
                'gender' => $validatedData['gender'],
                'age_year' => $validatedData['age_year'] ?? null,
                'age_month' => $validatedData['age_month'] ?? null,
                'age_day' => $validatedData['age_day'] ?? null,
                'address' => $validatedData['address'] ?? null,
                'company_id' => $validatedData['company_id'] ?? null,
                'user_id' => $currentUser->id, // User who registered this patient entry
                'shift_id' => $currentGeneralShiftId, // General clinic shift of registration
                // 'doctor_id' => null, // Patient's primary doctor (if different from visit doctor)
                'present_complains' => $validatedData['notes'] ?? '', // Assuming 'notes' from form maps here

                // Defaulting NOT NULL fields from patients table (as in previous version)
                'visit_number' => $isNewPatientFile ? 1 : ($existingPatientWithFile ? $existingPatientWithFile->visit_number + 1 : 1), // Increment visit_number
                'result_auth' => false, 'auth_date' => now(), 'history_of_present_illness' => '', 
                'procedures' => '', 'provisional_diagnosis' => '', 'bp' => '', 'temp' => 0, 'weight' => 0, 
                'height' => 0, 'discount' => 0, 'drug_history' => '', 'family_history' => '', 'rbs' => '', 
                'doctor_finish' => false, 'care_plan' => '', 'doctor_lab_request_confirm' => false, 
                'doctor_lab_urgent_confirm' => false, 'general_examination_notes' => '', 
                'patient_medical_history' => '', 'social_history' => '', 'allergies' => '', 'general' => '', 
                'skin' => '', 'head' => '', 'eyes' => '', 'ear' => '', 'nose' => '', 'mouth' => '', 
                'throat' => '', 'neck' => '', 'respiratory_system' => '', 'cardio_system' => '', 
                'git_system' => '', 'genitourinary_system' => '', 'nervous_system' => '', 
                'musculoskeletal_system' => '', 'neuropsychiatric_system' => '', 'endocrine_system' => '', 
                'peripheral_vascular_system' => '', 'referred' => '', 'discount_comment' => '',
                // Ensure all required fields for Patient model are present
            ];
            $patient = Patient::create($patientData);

            // 3. Create an initial DoctorVisit for this patient
            // The doctor_id for the visit comes from the form ($visitDoctorId)
            $doctorVisitData = [
                'patient_id' => $patient->id,
                'doctor_id' => $visitDoctorId,
                'user_id' => $currentUser->id,
                'shift_id' => $currentGeneralShiftId,
                'doctor_shift_id' => $request->input('doctor_shift_id', null), // Pass this from frontend if relevant
                'visit_date' => Carbon::today(),
                'status' => 'waiting',
                'visit_notes' => $validatedData['notes'] ?? null, // Or specific visit notes
                'is_new' => $isNewPatientFile, // If it's a new file, it's effectively a new patient to the system file-wise
                'number' => $patient->visit_number, // Use the patient's visit_number
            ];
            $doctorVisit = DoctorVisit::create($doctorVisitData);

            DB::commit();

            $patient->load(['file', 'company', 'primaryDoctor', 'doctorVisits.doctor']); // Load relevant data for response
            return new PatientResource($patient);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Patient registration with file assignment failed: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return response()->json(['message' => 'حدث خطأ أثناء تسجيل المريض وتعيين الملف.', 'error' => $e->getMessage()], 500);
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
