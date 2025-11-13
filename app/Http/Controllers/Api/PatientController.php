<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\DoctorVisit;
use App\Models\Shift; // To get current shift for visit
use App\Http\Requests\StorePatientRequest;
use App\Http\Requests\UpdatePatientRequest;
use App\Http\Resources\DoctorVisitResource;
use App\Http\Resources\PatientLabQueueItemResource;
use App\Http\Resources\PatientResource;
use App\Http\Resources\PatientSearchResultResource;
use App\Http\Resources\PatientStrippedResource;
use App\Http\Resources\RecentDoctorVisitSearchResource;
use App\Models\Doctor;
use App\Models\MainTest;
use App\Models\LabRequest;
// use App\Http\Resources\PatientCollection; // If you have custom pagination
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\DoctorShift;
use App\Models\File;
use App\Models\UserDocSelection;
use App\Models\RequestedService;
use App\Models\RequestedServiceCost;
use App\Zebra;
use App\Models\RequestedResult;
use App\Models\Service;
use App\Models\Company;
use App\Services\RequestedServiceHelper;
use Illuminate\Support\Facades\Http as HttpClient;
use App\Jobs\EmitPatientRegisteredJob;
use App\Jobs\SendWelcomeSmsJob;
use App\Models\Setting;
use App\Services\UltramsgService;
use App\Jobs\SendAuthWhatsappMessage;
use App\Models\CompanyService;
use App\Models\Mindray;

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
        $query = Patient::with(['company', 'primaryDoctor:id,name', 'doctor','doctorVisit']); // Eager load common relations

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
    /**
     * Store a NEW patient record and an initial DoctorVisit.
     * A new File record is created and linked to the DoctorVisit.
     */
    /**
     * Check if the current shift is closed and prevent patient creation if so
     */
    private function checkShiftIsOpen()
    {
        $currentGeneralShift = Shift::orderBy('id', 'desc')->first();
        
     
        // Check if shift is closed by either is_closed flag or closed_at timestamp
        if ($currentGeneralShift->is_closed || $currentGeneralShift->closed_at !== null) {
            return response()->json(['message' => 'لا يمكن تسجيل مريض جديد. الوردية مغلقة حالياً.'], 400);
        }

        return $currentGeneralShift;
    }

    /**
     * Ensure the latest clinic shift belongs to today's date.
     * Returns 400 response if no shift exists or if the latest shift was created on a different day.
     */
    private function checkLatestShiftDateIsToday()
    {
        $latestShift = Shift::orderBy('id', 'desc')->first();
        if (!$latestShift) {
            return response()->json(['message' => 'لا توجد وردية عيادة متاحة اليوم.'], 400);
        }

        $latestShiftDate = optional($latestShift->created_at)->toDateString();
        $today = Carbon::today()->toDateString();

        if ($latestShiftDate !== $today) {
            return response()->json(['message' => 'لا يمكن تسجيل مريض جديد. آخر وردية ليست بتاريخ اليوم.'], 400);
        }

        return $latestShift;
    }

    public function store(StorePatientRequest $request)
    {

        
        $validatedPatientData = $request->validated(); // Use validated() directly

        $visitDoctorId = $validatedPatientData['doctor_id'];
        // Remove fields that are not part of the Patient model directly or handled separately
        $patientSpecificData = collect($validatedPatientData)->except(['doctor_id', 'notes', 'active_doctor_shift_id'])->toArray();

        // Check if shift is open before proceeding
        $shiftCheck = $this->checkShiftIsOpen();
        if ($shiftCheck instanceof \Illuminate\Http\JsonResponse) {
            return $shiftCheck; // Return error response if shift is closed
        }
        $dateCheck = $this->checkLatestShiftDateIsToday();
        if ($dateCheck instanceof \Illuminate\Http\JsonResponse) {
            return $dateCheck; // Return error response if latest shift is not today
        }
        $currentGeneralShift = $shiftCheck;
        $activeDoctorShiftId = $request->input('doctor_shift_id');
        $user = Auth::user();
        if ($request->filled('company_id')) {
            if(!$user->user_type == 'تامين') {
                // return response()->json(['message' => '   المستخدم ليس من نوع تامين .'], 400);
            }else{
                // if(!$user->hasRole('admin')) return response()->json(['message' => '  المستخدم من نوع نقدي لا يمكنه تسجيل مريض من نوع تامين .'], 400);
            }
            // $this->authorize('register insurance_patient');
        } else {
            if(!$user->can('تسجيل مريض كاش')) return response()->json(['message' => '  المستخدم ليس لديه صلاحية تسجيل مريض كاش .'], 400);
        }
        DB::beginTransaction();
        try {
            // Check for existing patient with same phone number or identical name
            $existingPatient = null;
            $fileToUseId = null;

            if (!empty($patientSpecificData['phone']) || !empty($patientSpecificData['name'])) {
                $existingPatient = Patient::where(function ($query) use ($patientSpecificData) {
                    if (!empty($patientSpecificData['phone'])) {
                        $query->where('phone', $patientSpecificData['phone']);
                    }
                    if (!empty($patientSpecificData['name'])) {
                        $query->orWhere('name', $patientSpecificData['name']);
                    }
                })->latest()->first();

                if ($existingPatient) {
                    $latestVisit = $existingPatient->doctorVisit()->latest()->first();
                    if ($latestVisit && $latestVisit->file_id) {
                        $fileToUseId = $latestVisit->file_id;
                    }
                }
            }

            // Create a new File record only if we didn't find an existing one to reuse
            if (!$fileToUseId) {
                $file = File::create();
                $fileToUseId = $file->id;
            }

            //the visit number is the number of the in the general shift
            $visitLabNumber = DoctorVisit::where('shift_id', $currentGeneralShift->id)->count() + 1;
            $queueNumber = DoctorVisit::where('doctor_shift_id', $activeDoctorShiftId)->count() + 1;
            // 2. Create the new Patient record for this encounter
            $patient = Patient::create(array_merge($patientSpecificData, [
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'visit_number' => $visitLabNumber,
                'doctor_id' => $visitDoctorId,
                'result_auth' => false,
                'referred' => 'no',
                'discount_comment' => '',
                'auth_date' => null,
            ]));

            // 3. Create the DoctorVisit record linked to this new Patient record and new File
            $doctorVisit = $patient->doctorVisit()->create([
                'doctor_id' => $visitDoctorId,
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'doctor_shift_id' => $activeDoctorShiftId,
                'file_id' => $fileToUseId,
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'waiting',
                'reason_for_visit' => '',
                'is_new' => 1,
                'number' => $queueNumber,
                'queue_number' => $queueNumber,
            ]);

            // 4. Auto-attach favorite service if user has a selection for this doctor
            $userId = Auth::id();
            $fav = UserDocSelection::where('user_id', $userId)
                ->where('doc_id', $visitDoctorId)
                ->where('active', 1)
                ->first();

            if ($fav && $fav->fav_service) {
                $service = Service::with('serviceCosts.subServiceCost')->find($fav->fav_service);
                // $company_service = CompanyService::where('company_id', $patient->company_id)->where('service_id', $fav->fav_service)->first();
                // Log::info('company_service', ['company_service' => $company_service]);
                // if ($company_service && $company_service?->price != 0) {
                    RequestedServiceHelper::createFromFavoriteService(
                        $service,
                        $doctorVisit,
                        $patient,
                        $userId,
                        1 // count
                    );
                // }
            }

            DB::commit();

            // Queue non-blocking actions after successful commit
            \DB::afterCommit(function () use ($patient) {
                $settings = Setting::first();
            
                $hasPhone = is_string($patient->phone) ? trim($patient->phone) !== '' : !empty($patient->phone);
                $welcomeOn = (bool) ($settings?->send_welcome_message ?? false);
            
                if ($hasPhone && $welcomeOn) {
                    SendWelcomeSmsJob::dispatch($patient->id, (string)$patient->phone, (string)$patient->name);
                }
                Log::info($settings);
                Log::info(sprintf(
                    'Welcome SMS check -> hasPhone: %s, welcomeOn: %s, phone: "%s"',
                    $hasPhone ? 'true' : 'false',
                    $welcomeOn ? 'true' : 'false',
                    (string) $patient->phone
                ));
            });

            return new PatientResource($patient->loadMissing(['company', 'primaryDoctor', 'doctorVisit.doctor', 'doctorVisit.file']));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("New patient registration failed: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل تسجيل المريض.', 'error' => 'خطأ داخلي.' . $e->getMessage()], 500);
        }
    }

    /**
     * Toggle the result lock status for a patient.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Patient  $patient
     * @return \App\Http\Resources\PatientResource|\Illuminate\Http\JsonResponse
     */
    public function toggleResultLock(Request $request, Patient $patient)
    {
        // Add permission check: e.g., can('lock lab_results', $patient)
        // if (!Auth::user()->can('manage_result_lock', $patient)) { // Example permission
        //     return response()->json(['message' => 'Unauthorized to change result lock status.'], 403);
        // }

        $request->validate([
            'lock' => 'required|boolean', // Expecting true to lock, false to unlock
        ]);

        if ($patient->result_is_locked === $request->boolean('lock')) {
            $status = $request->boolean('lock') ? 'locked' : 'unlocked';
            return response()->json([
                'message' => "Results are already {$status}.",
                'data' => new PatientResource($patient->fresh()) // Return current state
            ], 200); // Or 409 Conflict if preferred
        }

        $patient->result_is_locked = $request->boolean('lock');
        
        // If locking, you might want to log who locked it and when,
        // potentially in an audit trail or separate fields on the patient model.
        // if ($request->boolean('lock')) {
        //     $patient->result_locked_by = Auth::id();
        //     $patient->result_locked_at = now();
        // } else {
        //     $patient->result_locked_by = null;
        //     $patient->result_locked_at = null;
        // }
        
        $patient->save();

        $action = $request->boolean('lock') ? 'locked' : 'unlocked';
        return response()->json([
            'message' => "Patient results have been successfully {$action}.",
            'data' => new PatientResource($patient->fresh()->load(['company', 'primaryDoctor'])) // Reload relations for consistency
        ]);
    }

    /**
     * Authenticate patient results
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Patient  $patient
     * @return \App\Http\Resources\PatientResource|\Illuminate\Http\JsonResponse
     */
    public function authenticateResults(Request $request, Patient $patient)
    {
        // Add permission check: e.g., can('authenticate lab_results', $patient)
        // if (!Auth::user()->can('authenticate_results', $patient)) {
        //     return response()->json(['message' => 'Unauthorized to authenticate results.'], 403);
        // }

        if ($patient->result_auth) {
            // Get the doctor visit and load necessary relationships for PatientLabQueueItemResource
            $doctorVisit = $patient->doctorVisit;
            if ($doctorVisit) {
                $doctorVisit->load([
                    'patient',
                    'patientLabRequests',
                    'patientLabRequests.mainTest',
                    'patientLabRequests.results'
                ]);
                
                // Set test_count attribute (expected by PatientLabQueueItemResource)
                $doctorVisit->test_count = $doctorVisit->patientLabRequests->count();
                
                // Calculate oldest_request_time manually from loaded relationship
                if ($doctorVisit->patientLabRequests->isNotEmpty()) {
                    $oldestRequest = $doctorVisit->patientLabRequests->min('created_at');
                    $doctorVisit->oldest_request_time = $oldestRequest ? $oldestRequest : $doctorVisit->created_at;
                } else {
                    $doctorVisit->oldest_request_time = $doctorVisit->created_at;
                }
            }
            
            return response()->json([
                'message' => "Results are already authenticated.",
                'data' => $doctorVisit ? new PatientLabQueueItemResource($doctorVisit) : null
            ], 200);
        }

        $patient->result_auth = true;
        $patient->result_auth_user = Auth::id();
        $patient->auth_date = now();
        $patient->save();

        // Get the doctor visit and load necessary relationships for PatientLabQueueItemResource
        $doctorVisit = $patient->doctorVisit;
        if ($doctorVisit) {
            $doctorVisit->load([
                'patient',
                'patientLabRequests',
                'patientLabRequests.mainTest',
                'patientLabRequests.results'
            ]);
            
            // Set test_count attribute (expected by PatientLabQueueItemResource)
            $doctorVisit->test_count = $doctorVisit->patientLabRequests->count();
            
            // Calculate oldest_request_time manually from loaded relationship
            if ($doctorVisit->patientLabRequests->isNotEmpty()) {
                $oldestRequest = $doctorVisit->patientLabRequests->min('created_at');
                $doctorVisit->oldest_request_time = $oldestRequest ? $oldestRequest : $doctorVisit->created_at;
            } else {
                $doctorVisit->oldest_request_time = $doctorVisit->created_at;
            }
        }

        // Dispatch job to upload lab result to Firebase
        try {
            if ($doctorVisit) {
                \App\Jobs\UploadLabResultToFirebase::dispatch(
                    $patient->id,
                    $doctorVisit->id,
                    'alroomy-shaglaban' // You can get this from settings
                );
                
                Log::info("Firebase upload job dispatched for patient {$patient->id}, visit {$doctorVisit->id}");
            }
        } catch (\Exception $e) {
            Log::error('Error dispatching Firebase upload job: ' . $e->getMessage());
        }

        $queueItemResource = $doctorVisit ? new PatientLabQueueItemResource($doctorVisit) : null;
        
        // Emit realtime update event (fire-and-forget)
        if ($queueItemResource) {
            try {
                $payload = [
                    'queueItem' => $queueItemResource->resolve(),
                ];
                $url = config('services.realtime.url') . '/emit/lab-queue-item-updated';
                HttpClient::withHeaders(['x-internal-token' => config('services.realtime.token')])
                    ->post($url, $payload);
            } catch (\Throwable $e) {
                Log::warning('Failed to emit lab-queue-item-updated realtime event: ' . $e->getMessage());
            }
        }

        $responseData = [
            'message' => "Patient results have been successfully authenticated. Upload to cloud storage has been queued.",
            'data' => $queueItemResource
        ];

        // Queue WhatsApp message job (respects settings flag internally)
        SendAuthWhatsappMessage::dispatch($patient->id)->onQueue('notifications');

        return response()->json($responseData);
    }

    /**
     * Get the result URL for a patient
     *
     * @param  \App\Models\Patient  $patient
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResultUrl(Patient $patient)
    {
        return response()->json([
            'result_url' => $patient->result_url,
            'has_result_url' => !empty($patient->result_url)
        ]);
    }

    /**
     * Upload lab result to Firebase for a patient
     *
     * @param  \App\Models\Patient  $patient
     * @return \Illuminate\Http\JsonResponse
     */
    public function uploadToFirebase(Patient $patient)
    {
        try {
            // Get doctor visit
            $doctorVisit = $patient->doctorVisit;
            if (!$doctorVisit) {
                return response()->json([
                    'success' => false,
                    'message' => 'No doctor visit found for this patient'
                ], 400);
            }

            // Check if patient already has result_url (for logging purposes)
            $hadExistingUrl = !empty($patient->result_url);
            $oldResultUrl = $patient->result_url;

            // Always run the job to upload/overwrite the result
            $job = new \App\Jobs\UploadLabResultToFirebase(
                $patient->id,
                $doctorVisit->id,
                'alroomy-shaglaban' // You can get this from settings
            );
            
            $job->handle();
            
            // Refresh the patient to get the updated result_url
            $patient->refresh();
            
            \Log::info("Firebase upload completed for patient {$patient->id}, visit {$doctorVisit->id}", [
                'had_existing_url' => $hadExistingUrl,
                'old_url' => $oldResultUrl,
                'new_url' => $patient->result_url
            ]);

            $message = $hadExistingUrl 
                ? 'Lab result replaced in Firebase successfully' 
                : 'Lab result uploaded to Firebase successfully';

            return response()->json([
                'success' => true,
                'message' => $message,
                'lab_to_lab_object_id' => $patient->lab_to_lab_object_id,
                'result_url' => $patient->result_url,
                'patient_id' => $patient->id,
                'visit_id' => $doctorVisit->id,
                'was_updated' => $hadExistingUrl
            ]);

        } catch (\Exception $e) {
            \Log::error('Error uploading to Firebase: ' . $e->getMessage(), [
                'patient_id' => $patient->id,
                'exception' => $e
            ]);
            
            // Return detailed error message to help with debugging
            $errorMessage = 'Failed to upload to Firebase';
            if (strpos($e->getMessage(), 'service account file not found') !== false) {
                $errorMessage = 'Firebase service account not configured. Please contact system administrator.';
            } elseif (strpos($e->getMessage(), 'Unable to determine the Firebase Project ID') !== false) {
                $errorMessage = 'Firebase project configuration is missing. Please contact system administrator.';
            } elseif (strpos($e->getMessage(), 'Permission denied') !== false) {
                $errorMessage = 'Firebase storage permission denied. Please contact system administrator.';
            } else {
                $errorMessage = 'Firebase upload failed: ' . $e->getMessage();
            }
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
                'error_details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle authentication status for a patient (Admin only)
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Patient  $patient
     * @return \App\Http\Resources\PatientResource|\Illuminate\Http\JsonResponse
     */
    public function toggleAuthentication(Request $request, Patient $patient)
    {
        // Check if user has admin role
        if (!Auth::user()->hasRole('admin')) {
            return response()->json([
                'message' => 'Unauthorized. Admin role required.',
            ], 403);
        }

        // Toggle the authentication status
        $patient->result_auth = !$patient->result_auth;
        
        if ($patient->result_auth) {
            // If authenticating, set the auth user and date
            $patient->result_auth_user = Auth::id();
            $patient->auth_date = now();
            
            // Dispatch job to upload to Firebase
            try {
                $doctorVisit = $patient->doctorVisit;
                if ($doctorVisit) {
                    \App\Jobs\UploadLabResultToFirebase::dispatch(
                        $patient->id,
                        $doctorVisit->id,
                        'alroomy-shaglaban'
                    );
                    \Log::info("Firebase upload job dispatched for patient {$patient->id}, visit {$doctorVisit->id}");
                }
            } catch (\Exception $e) {
                \Log::error('Error dispatching Firebase upload job: ' . $e->getMessage());
            }
        } else {
            // If de-authenticating, clear the auth user and date
            $patient->result_auth_user = null;
            $patient->auth_date = null;
            // Optionally clear the result_url as well
            $patient->result_url = null;
        }
        
        $patient->save();

        // Get the doctor visit and load necessary relationships for PatientLabQueueItemResource
        $doctorVisit = $patient->doctorVisit;
        if ($doctorVisit) {
            $doctorVisit->load([
                'patient',
                'patientLabRequests',
                'patientLabRequests.mainTest',
                'patientLabRequests.results'
            ]);
            
            // Set test_count attribute (expected by PatientLabQueueItemResource)
            $doctorVisit->test_count = $doctorVisit->patientLabRequests->count();
            
            // Calculate oldest_request_time manually from loaded relationship
            if ($doctorVisit->patientLabRequests->isNotEmpty()) {
                $oldestRequest = $doctorVisit->patientLabRequests->min('created_at');
                $doctorVisit->oldest_request_time = $oldestRequest ? $oldestRequest : $doctorVisit->created_at;
            } else {
                $doctorVisit->oldest_request_time = $doctorVisit->created_at;
            }
        }

        $queueItemResource = $doctorVisit ? new PatientLabQueueItemResource($doctorVisit) : null;
        
        // Emit realtime update event (fire-and-forget)
        if ($queueItemResource) {
            try {
                $payload = [
                    'queueItem' => $queueItemResource->resolve(),
                ];
                $url = config('services.realtime.url') . '/emit/lab-queue-item-updated';
                HttpClient::withHeaders(['x-internal-token' => config('services.realtime.token')])
                    ->post($url, $payload);
            } catch (\Throwable $e) {
                Log::warning('Failed to emit lab-queue-item-updated realtime event: ' . $e->getMessage());
            }
        }

        return response()->json([
            'message' => $patient->result_auth ? "Patient results have been authenticated." : "Patient results authentication has been revoked.",
            'data' => $queueItemResource
        ]);
    }
    /**
     * Create a new Patient record by cloning data, and then create a new DoctorVisit.
     * A new File record is created and linked if no previous_visit_id is provided,
     * otherwise, the file_id from the previous visit is copied.
     */
    public function storeVisitFromHistory(Request $request, DoctorVisit $doctorVisit)
    {
        $validatedVisitData = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'active_doctor_shift_id' => 'nullable|integer|exists:doctor_shifts,id',
            'reason_for_visit' => 'nullable|string|max:1000',
            'previous_visit_id' => 'nullable|integer|exists:doctorvisits,id,patient_id,' . $doctorVisit->patient_id,
        ]);

        $currentGeneralShift = Shift::open()->latest('id')->first();
        if (!$currentGeneralShift) {
            return response()->json(['message' => 'لا توجد وردية عيادة مفتوحة حالياً.'], 400);
        }
        $dateCheck = $this->checkLatestShiftDateIsToday();
        if ($dateCheck instanceof \Illuminate\Http\JsonResponse) {
            return $dateCheck; // Ensure latest shift is from today
        }
        $user = Auth::user();
        //  'تسجيل مريض كاش',
        // 'تسجيل مريض تامين',
        // if($user->can('تسجيل مريض كاش')){
            // return response()->json(['message' => '  المستخدم من نوع تامين لا يمكنه تسجيل مريض من نوع نقدي .'], 400);
        // }
        // if($user->can('تسجيل مريض تامين')){
        //     return response()->json(['message' => '  المستخدم من نوع تامين لا يمكنه تسجيل مريض من نوع نقدي .'], 400);
        // }

        // $user = Auth::user();
        // if($doctorVisit->patient->company_id == null && $user->user_type == 'تامين') return response()->json(['message' => '  المستخدم من نوع تامين لا يمكنه تسجيل مريض من نوع نقدي .'], 400);
       

        $fileToUseId = null;
        $previousVisit = null;

        if (!empty($validatedVisitData['previous_visit_id'])) {
            $previousVisit = DoctorVisit::find($validatedVisitData['previous_visit_id']);
            $fileToUseId = $previousVisit?->file_id; // Copy existing file_id
        }

        // If no previous visit was specified to copy file_id from, OR if that visit had no file_id,
        // AND if the existingPatient's latest visit also doesn't provide a file_id, create a new file.
        // This logic assumes you want to reuse file_id if available from history.
        if (!$fileToUseId) {
            $latestVisitOfExistingPatient = $doctorVisit->patient->doctorVisit()->latest('created_at')->first();
            $fileToUseId = $latestVisitOfExistingPatient?->file_id;
        }


        DB::beginTransaction();
        try {
            // 1. Create a new File record ONLY if we couldn't find one to copy
            if (!$fileToUseId) {
                $file = File::create();
                $fileToUseId = $file->id;
            }
            //the visit number is the number of  the in the general shift
            $visitLabNumber = DoctorVisit::where('shift_id', $currentGeneralShift->id)->count() + 1;
            $queueNumber = DoctorVisit::where('doctor_shift_id', $validatedVisitData['active_doctor_shift_id'])->count() + 1;
            // 2. Clone patient data to create a NEW patient record
            $newPatientData = $doctorVisit->patient->replicate()->fill([
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'visit_number' => $visitLabNumber,
                'created_at' => now(), // New record, new timestamps
                'updated_at' => now(),
                'doctor_id' => $validatedVisitData['doctor_id'],
                'result_auth' => false,
                'auth_date' => null,
                // Reset visit-specific flags from the old patient snapshot
                'is_lab_paid' => false,
                'lab_paid' => 0,
                'result_is_locked' => false,
                'sample_collected' => false,
                'lab_to_lab_object_id'=>null,
                'last_visit_doctor_id'=>null,
                // Potentially update some demographics if the form was partially filled for this "new" visit using old data as base
                // 'phone' => $request->input('new_phone', $existingPatient->phone), // Example
            ])->toArray();
            // Ensure 'id' is not carried over from replication for create
            unset($newPatientData['id']);

            $newPatient = Patient::create($newPatientData);

            // 3. Create the new DoctorVisit for this NEW patient record
            $doctorVisit = $newPatient->doctorVisit()->create([
                'doctor_id' => $validatedVisitData['doctor_id'],
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'doctor_shift_id' => $validatedVisitData['active_doctor_shift_id'] ?? null,
                'file_id' => $fileToUseId, // Assign the determined file_id
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'waiting',
                'reason_for_visit' => $validatedVisitData['reason_for_visit'] ?? ($previousVisit?->reason_for_visit ?? 'متابعة'),
                'is_new' => false,
                'number' => $queueNumber, // Example: Using file_id as the visit/encounter number
                'queue_number' => $queueNumber, // Example: Using file_id as the visit/encounter number
            ]);
            DB::commit();

            return new DoctorVisitResource($doctorVisit->load(['patient.subcompany', 'patient.doctor', 'file']));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to store visit from history for original patient {$doctorVisit->patient->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل إنشاء الزيارة الجديدة من السجل.', 'error' => 'خطأ داخلي.' . $e->getMessage()], 500);
        }
    }
    public function searchExisting(Request $request)
    {
        $searchTerm = $request->validate([
            'term' => 'required|string|min:2',
        ])['term'];
    
        $visits = DoctorVisit::query()
            ->whereHas('patient', function ($query) use ($searchTerm) {
                $query->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
                });
            })
            ->with(['patient', 'doctor'])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();
    
        return PatientSearchResultResource::collection($visits);
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

        ]);
        return new PatientResource($patient);
    }

    /**
     * Update the specified patient in storage.
     */
    public function update(UpdatePatientRequest $request, Patient $patient)
    {
        $validatedData = $request->validated();
        if($patient->user_id != Auth::id()){
            return response()->json(['message' => 'لا يمكنك تحديث بيانات هذا المريض.'], 403);
        }
        Log::info('Updating patient:  ' . $patient->id . ' with data: ' . json_encode($request->all()));
        // Exclude fields that are managed by other processes or shouldn't be mass updated here
        // For example, financial or specific clinical flags related to visits.

        $patient->update($validatedData);
        // Emit realtime update event (fire-and-forget)
        try {
            $payload = [
                'patient' => (new PatientResource($patient->loadMissing(['company', 'primaryDoctor'])))->resolve(),
            ];
            $url = config('services.realtime.url') . '/emit/patient-updated';
            HttpClient::withHeaders(['x-internal-token' => config('services.realtime.token')])
                ->post($url, $payload);
        } catch (\Throwable $e) {
            Log::warning('Failed to emit patient-updated realtime event: ' . $e->getMessage());
        }

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
    // In PatientController.php
    public function visitHistory(Patient $patient)
    {
        // Get all doctor visits where the patient's phone number matches
        $patients = DoctorVisit::whereHas('patient', function ($query) use ($patient) {
            $query->where('phone', $patient->phone);
        })
            ->with(['doctor:id,name', 'requestedServices.service:id,name', 'patient', 'doctor'])
            ->orderBy('created_at', 'desc')
            ->get();

        return DoctorVisitResource::collection($patients);
    }
     /**
     * Store a new patient and create a lab-centric visit from the Lab Reception page.
     * This method does not require an active doctor shift but links to a general clinic shift.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \App\Http\Resources\PatientResource|\Illuminate\Http\JsonResponse
     */
        public function storeFromLab(Request $request)
    {
        // Add a specific permission check for this action
        // if (!Auth::user()->can('register lab_patient')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Validation tailored for lab registration
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'gender' => 'required|in:male,female',
            'age_year' => 'nullable|integer|min:0|max:120',
            'age_month' => 'nullable|integer|min:0|max:11',
            'age_day' => 'nullable|integer|min:0|max:30',
            'address' => 'nullable|string|max:1000',
            'doctor_id' => 'required|integer|exists:doctors,id', // Referring doctor is required
            // No company/insurance fields needed if lab is always cash, otherwise add them here.
        ]);

        $currentGeneralShift = Shift::open()->latest('created_at')->first();
        if (!$currentGeneralShift) {
            return response()->json(['message' => 'No open clinic shift available to create a visit.'], 400);
        }
        //the visit number is the number of  the in the general shift
        $visitLabNumber = DoctorVisit::where('shift_id', $currentGeneralShift->id)->count() + 1;


        // Find the latest active shift for the selected referring doctor.
        // This is important for correctly assigning any doctor-related credit later.
        $activeDoctorShift = DoctorShift::where('doctor_id', $validatedData['doctor_id'])
                                        ->latest('id')
                                        ->first();

                                        if($activeDoctorShift){
                                           
                                        }else{
                                            //create a new shift
                                            $activeDoctorShift = DoctorShift::create([
                                                'doctor_id' => $validatedData['doctor_id'],
                                                'start_time' => Carbon::now(),
                                                'end_time' => Carbon::now()->addHours(1),
                                                'status' => true,
                                            ]);
                                        }

        DB::beginTransaction();
        try {
            // Your logic for finding an existing patient file can be reused here
            // For simplicity, we'll create a new file for each new lab visit encounter
            $file = File::create();

            // Create the new Patient record for this encounter
            // A new patient record is created for each visit to capture their state at that time.
            $patient = Patient::create([
                'name' => $validatedData['name'],
                'phone' => $validatedData['phone'],
                'gender' => $validatedData['gender'] ?? 'male',
                'age_year' => $validatedData['age_year'] ?? 0   ,
                'age_month' => $validatedData['age_month'] ?? 0,
                'age_day' => $validatedData['age_day'] ?? 0,
                'address' => $validatedData['address'] ?? '',
                'doctor_id' => $validatedData['doctor_id'], // Store the referring doctor
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'auth_date' => null,
                'result_auth' => false,
                'result_is_locked' => false,
                'sample_collected' => false,
                'is_lab_paid' => false,
                'lab_paid' => 0,
                'referred' => Doctor::find($validatedData['doctor_id'])->name,
                'discount_comment' => '',
                'visit_number' => $visitLabNumber,
                

            ]);

            // Create the DoctorVisit record linked to this new Patient record
            $doctorVisit = $patient->doctorVisit()->create([
                'doctor_id' => $validatedData['doctor_id'], // Referring doctor
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'doctor_shift_id' => $activeDoctorShift?->id, // Can be null if doctor isn't on shift
                'file_id' => $file->id,
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'lab_pending', // A specific status for lab reception visits
                'is_new' => true,
                'only_lab' => true, // CRUCIAL FLAG for this workflow
            ]);

            DB::commit();

            // Queue welcome SMS after commit
            // \DB::afterCommit(function () use ($patient) {
            //     if (!empty($patient->phone)) {
            //         SendWelcomeSmsJob::dispatch($patient->id, $patient->phone, $patient->name);
            //     }
            // });

            // Return the patient resource, ensuring it includes the new doctorVisit relation
            // The PatientResource should be configured to conditionally include this.
            $patient->load('doctorVisit');
            
            return new PatientResource($patient);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Lab patient registration failed: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to register patient for lab.', 'error_details' => $e->getMessage()], 500);
        }
    }

    /**
     * Search for patients and return their recent visits for an Autocomplete component.
     */
    public function searchPatientVisitsForAutocomplete(Request $request)
    {
        $request->validate([
            'term' => 'required|string|min:2',
        ]);

        $searchTerm = $request->term;

        // Find recent visits for patients matching the search term
        $visits = DoctorVisit::with('patient:id,name,phone')
            ->whereHas('patient', function ($query) use ($searchTerm) {
                $query->where('name', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                      ->orWhere('id', $searchTerm);
            })
            ->select('id', 'patient_id', 'visit_date') // Select only what's needed
            ->latest('visit_date') // Order by most recent visits first
            ->limit(15) // Limit the number of results for performance
            ->get();

        // Transform the data into the format the frontend Autocomplete expects
        $formattedResults = $visits->map(function ($visit) {
            return [
                'visit_id' => $visit->id,
                'patient_id' => $visit->patient_id,
                // Create a user-friendly label for the dropdown
                'autocomplete_label' => "{$visit->patient->name} (#{$visit->patient->id}) - Visit on {$visit->visit_date->format('d-M-Y')}",
            ];
        });

        return response()->json(['data' => $formattedResults]);
    }
     /**
     * Create a new lab-only visit for an existing patient.
     */
    public function createLabVisitForExistingPatient(Request $request, DoctorVisit $doctorVisit)
    {
        $patient = $doctorVisit->patient;
        // Add permission check, e.g., can('create lab_visit')
        $validated = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id', // Referring doctor
            'company_id' => 'nullable|integer|exists:companies,id', // Company for insurance patients
            'reason_for_visit' => 'nullable|string|max:1000',
        ]);


        // return ['validated' => $validated['doctor_id']];
        // Check if shift is open before proceeding
        $shiftCheck = $this->checkShiftIsOpen();
        if ($shiftCheck instanceof \Illuminate\Http\JsonResponse) {
            return $shiftCheck; // Return error response if shift is closed
        }
        $dateCheck = $this->checkLatestShiftDateIsToday();
        if ($dateCheck instanceof \Illuminate\Http\JsonResponse) {
            return $dateCheck; // Ensure latest shift is from today
        }
        $user = Auth::user();
    
        // if(isset($validated['company_id']) && $validated['company_id'] != null){
        //     if(!$user->can('تسجيل مريض تامين')){
        //         return response()->json(['message' => 'المستخدم لا يمكنه تسجيل مريض تامين'], 400);
        //     }

        // }else{
        //     if(!$user->can('تسجيل مريض كاش')){
        //         return response()->json(['message' => 'المستخدم لا يمكنه تسجيل مريض كاش'], 400);
        //     }
        // }
      
        $currentGeneralShift = $shiftCheck;
        DB::beginTransaction();
        try {
            // Get file_id from the patient's current doctorVisit or create a new file
            $fileToUseId = $patient->doctorVisit?->file_id;
            
            // If for some reason no previous visit had a file, create a new one
            if (!$fileToUseId) {
                $file = File::create();
                $fileToUseId = $file->id;
            }

            $visitLabNumber = DoctorVisit::where('shift_id', $currentGeneralShift->id)->count() + 1;

            // Since Patient has only one doctorVisit, we need to create a new Patient record
            // and then create the doctorVisit for that new patient record
            $newPatient = Patient::create([
                'name' => $patient->name,
                'phone' => $patient->phone,
                'gender' => $patient->gender,
                'age_year' => $patient->age_year,
                'age_month' => $patient->age_month,
                'age_day' => $patient->age_day,
                'address' => $patient->address,
                'company_id' => $validated['company_id'] ?? $patient->company_id, // Use new company_id if provided, otherwise keep original
                'subcompany_id' => $patient->subcompany_id,
                'company_relation_id' => $patient->company_relation_id,
                'guarantor' => $patient->guarantor,
                'insurance_no' => $patient->insurance_no,
                'doctor_id' => $validated['doctor_id'],
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'visit_number' => $visitLabNumber,
                'result_auth' => false,
                'referred' => 'no',
                'discount_comment' => '',
            ]);

            // Create the DoctorVisit record for the new patient
            $doctorVisit = $newPatient->doctorVisit()->create([
                'doctor_id' => $validated['doctor_id'],
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'doctor_shift_id' => null, // Not tied to a doctor's active shift session in the clinic
                'file_id' => $fileToUseId,
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'lab_pending', // A status indicating it's waiting for lab work
                'reason_for_visit' => $validated['reason_for_visit'] ?? 'Lab Request',
                'is_new' => false, // It's a visit for an existing patient
                'number' => $visitLabNumber,
                'only_lab' => true, // CRUCIAL: This marks it as a direct lab visit
            ]);
            
            DB::commit();

            // Load the doctorVisit relationship for the new patient
            $newPatient->load('doctorVisit');

            return new \App\Http\Resources\PatientResource($newPatient);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create lab visit for existing patient {$patient->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to create lab visit.', 'error' => $e->getMessage()], 500);
        }
    }
    public function getRecentLabActivityPatients(Request $request)
    {
        // if (!Auth::user()->can('view lab_workstation_patient_dropdown')) { /* ... */ }

        $limit = $request->input('limit', 30);

        // Find patients who had lab requests, ordered by the most recent lab request's creation time or visit time.
        // This query can be complex to optimize for "most recent based on lab activity".
        // Simpler approach: Patients with most recent visits that included lab requests.
        $patients = DoctorVisit::whereHas('patientLabRequests', function ($query) {
            $query->where('valid', true);
        })->with(['patient'=>function($query){
            $query->select('id','name','phone');
        }])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
            
        // The resource needs to handle 'latest_visit_id' if it's not a direct attribute
        return PatientStrippedResource::collection($patients)->additional([
            'meta' => ['note' => 'latest_visit_id refers to the DoctorVisit ID']
        ]);
    }
      /**
     * Search for DoctorVisits by patient name for Autocomplete.
     * Returns recent visits matching the patient name.
     */
    public function searchRecentDoctorVisitsByPatientName(Request $request)
    {
        // if (!Auth::user()->can('search_doctor_visits')) { /* ... */ }

        $request->validate([
            'patient_name_search' => 'required|string|min:1',
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $searchTerm = $request->patient_name_search;
        $limit = $request->input('limit', 15); // Default limit for autocomplete

        // Check if search term is numeric (visit ID search)
        if (is_numeric($searchTerm)) {
            $visits = DoctorVisit::with([
                    'patient:id,name,phone', // Eager load basic patient info
                    'doctor:id,name'         // Eager load basic doctor info
                ])
                ->where('id', $searchTerm)
                ->take($limit)
                // ->orderBy('id', 'desc')
                ->get();
        } else {
            // Search by patient name or phone (minimum 2 characters for text search)
            if (strlen($searchTerm) < 2) {
                return RecentDoctorVisitSearchResource::collection(collect());
            }

            $visits = DoctorVisit::with([
                    'patient:id,name,phone', // Eager load basic patient info
                    'doctor:id,name'         // Eager load basic doctor info
                ])
                ->whereHas('patient', function ($query) use ($searchTerm) {
                    $query->where('name', 'LIKE', "%{$searchTerm}%")
                          ->orWhere('phone', 'LIKE', "%{$searchTerm}%"); // Optionally search by phone too
                })
                ->orderBy('id', 'asc')
                ->take($limit)
                ->get();
        }
            
        // Filter out visits that do not have lab requests (if strictly for lab workstation)
        // Or do this in the initial whereHas if more performant for your DB
        $visitsWithLabs = $visits->filter(function ($visit) {
            // This requires labRequests relation to be defined on DoctorVisit
            // If labRequests are linked via pid, this check is more complex here
            // For now, assuming a direct or indirect link is checkable.
            // If labRequests are linked via patient_id directly (pid), this could be:
            // return $visit->patient->labRequests()->whereDate('created_at', $visit->visit_date)->exists();
            // Or if LabRequest table has a visit_id (even if not an FK constraint, but populated):
            // return \App\Models\LabRequest::where('doctor_visit_id_placeholder', $visit->id)->exists();

            // Simpler: If you always create DoctorVisit even for lab, then just return.
            // If the visit itself implies lab work, then no extra check needed.
            // For now, let's assume any visit returned could be relevant.
            // Frontend can later check if it has lab requests when loading full details.
            return true; 
        });


        return RecentDoctorVisitSearchResource::collection($visitsWithLabs);
    }
  
    public function printBarcode(Request $request, Doctorvisit $doctorvisit)
    {

        $patient = $doctorvisit->patient;
        //        $patient->update(['sample_print_date'=>now()]);
        $ip_address = $_SERVER['REMOTE_ADDR'];
        $hostPrinter = "\\$ip_address\zebra";
        $speedPrinter = 3;
        $darknessPrint = 20;
        $labelSize = array(300, 10);
        $referencePoint = array(223, 30);
        $z = new Zebra($hostPrinter, $speedPrinter, $darknessPrint, $labelSize, $referencePoint);
        $containers = $patient->labrequests->map(function (LabRequest $req) {
            return $req->mainTest->container;
        });

        foreach ($containers as $container) {
            $tests_accoriding_to_container = $patient->labrequests->filter(function (LabRequest $labrequest) use ($container) {
                return $labrequest->mainTest->container->id == $container->id;
            })->map(function (LabRequest $labRequest) {
                return $labRequest->mainTest;
            });
            $tests = "";
            /** @var MainTest $maintest */
            foreach ($tests_accoriding_to_container as $maintest) {
                $main_test_name = $maintest->main_test_name;
                $tests .= $main_test_name;
            }
            //                $z->setBarcode(1, 270, 120, $patient->id); #1 -> cod128//barcode
            //                $z->writeLabel("------------",340,165,4);//patient id
            //                $z->writeLabel($patient->id,340,155,4);//patient id
            //                $z->writeLabel("$tests",330,10,1);
            //                //$z->writeLabel("-",200,20,1);
            //                $z->setLabelCopies(1);
            $z->setBarcode(1, 270, 110, $doctorvisit->id); #1 -> cod128//barcode
            // $z->writeLabel($patient->visit_number, 340, 155, 4); //patient id
            $z->writeLabelBig($patient->visit_number,335,155,4);//patient id

            $z->writeLabel("$tests", 330, 10, 1);
            //            $z->writeLabel("$package_name",210,150,1);

            //$z->writeLabel("-",200,20,1);
            $z->setLabelCopies(1);
        } //end of foreach

        $z->print2zebra();
        return ['status' => true];
    }

    
    /**
     * Print barcode labels for lab containers based on doctor visit
     * 
     * @param Request $request
     * @param Doctorvisit $doctorvisit
     * @return array
     */
    // public function printBarcode(Request $request, Doctorvisit $doctorvisit)
    // {
    //     try {
    //         // Validate doctor visit has patient and lab requests
    //         if (!$doctorvisit->patient) {
    //             return ['status' => false, 'message' => 'No patient found for this visit'];
    //         }

    //         $patient = $doctorvisit->patient;
            
    //         if (!$patient->labrequests || $patient->labrequests->isEmpty()) {
    //             return ['status' => false, 'message' => 'No lab requests found for this patient'];
    //         }

    //         // Get printer configuration
    //         $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    //         $hostPrinter = "\\\\$ip_address\\zebra";
    //         $speedPrinter = 3;
    //         $darknessPrint = 20;
    //         $labelSize = [300, 10];
    //         $referencePoint = [223, 30];

    //         // Initialize Zebra printer
    //         $zebra = new Zebra($hostPrinter, $speedPrinter, $darknessPrint, $labelSize, $referencePoint);

    //         // Get unique containers from lab requests
    //         $containers = $patient->labrequests
    //             ->map(function (LabRequest $req) {
    //                 return $req->mainTest->container;
    //             })
    //             ->unique('id');

    //         // Generate labels for each container
    //         foreach ($containers as $container) {
    //             $this->generateLabelForContainer($zebra, $patient, $doctorvisit, $container);
    //         }

    //         // Print all labels
    //         $zebra->print2zebra();
            
    //         return ['status' => true, 'message' => 'Barcode labels printed successfully'];
            
    //     } catch (\Exception $e) {
    //         Log::error('Barcode printing failed: ' . $e->getMessage(), [
    //             'doctor_visit_id' => $doctorvisit->id,
    //             'patient_id' => $doctorvisit->patient?->id,
    //             'error' => $e->getTraceAsString()
    //         ]);
            
    //         return ['status' => false, 'message' => 'Failed to print barcode labels: ' . $e->getMessage()];
    //     }
    // }

    /**
     * Generate label for a specific container
     * 
     * @param Zebra $zebra
     * @param Patient $patient
     * @param Doctorvisit $doctorvisit
     * @param object $container
     * @return void
     */
    private function generateLabelForContainer(Zebra $zebra, Patient $patient, Doctorvisit $doctorvisit, $container): void
    {
        // Get tests for this specific container
        $testsForContainer = $patient->labrequests
            ->filter(function (LabRequest $labrequest) use ($container) {
                return $labrequest->mainTest->container->id == $container->id;
            })
            ->map(function (LabRequest $labRequest) {
                return $labRequest->mainTest;
            });

        // Build test names string
        $testNames = $testsForContainer
            ->pluck('main_test_name')
            ->implode(' ');

        // Generate barcode and labels
        $zebra->setBarcode(1, 270, 110, $doctorvisit->id);
        $zebra->writeLabelBig($patient->visit_number, 335, 155, 4);
        $zebra->writeLabel($testNames, 330, 10, 1);
        $zebra->setLabelCopies(1);
    }

    /**
     * Create a new clinic visit for an existing patient from history table.
     * This method creates a new patient record and doctor visit for clinic workflow.
     */
    public function createClinicVisitFromHistory(Request $request, DoctorVisit $doctorVisit)
    {
        // Add permission check, e.g., can('create clinic_visit')
        $validated = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'doctor_shift_id' => 'required|integer|exists:doctor_shifts,id',
            'company_id' => 'nullable|integer|exists:companies,id',
            'reason_for_visit' => 'nullable|string|max:1000',
        ]);

        // Check if shift is open before proceeding
        $shiftCheck = $this->checkShiftIsOpen();
        if ($shiftCheck instanceof \Illuminate\Http\JsonResponse) {
            return $shiftCheck; // Return error response if shift is closed
        }
        $dateCheck = $this->checkLatestShiftDateIsToday();
        if ($dateCheck instanceof \Illuminate\Http\JsonResponse) {
            return $dateCheck; // Ensure latest shift is from today
        }
        $currentGeneralShift = $shiftCheck;

        $user = Auth::user();
    
        // if($user->can('تسجيل مريض كاش')){
            // return response()->json(['message' => '  المستخدم من نوع تامين لا يمكنه تسجيل مريض من نوع نقدي .'], 400);
        // }
        // if($user->can('تسجيل مريض تامين')){
        //     return response()->json(['message' => '  المستخدم من نوع تامين لا يمكنه تسجيل مريض من نوع نقدي .'], 400);
        // }
        // Verify the doctor shift is active and belongs to the specified doctor
        $doctorShift = DoctorShift::where('id', $validated['doctor_shift_id'])
            ->where('doctor_id', $validated['doctor_id'])
            ->where('status', true)
            ->first();

        if (!$doctorShift) {
            return response()->json(['message' => 'وردية الطبيب غير صحيحة أو غير نشطة.'], 400);
        }

        $patient = $doctorVisit->patient;
        $company_id =   $validated['company_id'] ?? $patient->company_id;
        if($company_id != null && !$user->can('تسجيل مريض تامين')){
            return response()->json(['message' => 'المستخدم ليس لديه صلاحية تسجيل مريض تامين.'], 400);
        }

        DB::beginTransaction();
        try {
            // Get file_id from the current doctorVisit or create a new file
            $fileToUseId = $doctorVisit->file_id;
            
            // If for some reason no previous visit had a file, create a new one
            if (!$fileToUseId) {
                $file = File::create();
                $fileToUseId = $file->id;
            }

            $visitLabNumber = DoctorVisit::where('shift_id', $currentGeneralShift->id)->count() + 1;
            $queueNumber = DoctorVisit::where('doctor_shift_id', $validated['doctor_shift_id'])->count() + 1;

            // Create a new Patient record for this clinic visit
            $newPatient = Patient::create([
                'name' => $patient->name,
                'phone' => $patient->phone,
                'gender' => $patient->gender,
                'age_year' => $patient->age_year,
                'age_month' => $patient->age_month,
                'age_day' => $patient->age_day,
                'address' => $patient->address,
                'company_id' => $validated['company_id'] ?? $patient->company_id,
                'subcompany_id' => $patient->subcompany_id,
                'company_relation_id' => $patient->company_relation_id,
                'guarantor' => $patient->guarantor,
                'insurance_no' => $patient->insurance_no,
                'doctor_id' => $validated['doctor_id'],
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'visit_number' => $visitLabNumber,
                'result_auth' => false,
                'referred' => 'no',
                'discount_comment' => '',
            ]);

            // Create the DoctorVisit record for the new patient
            $doctorVisit = $newPatient->doctorVisit()->create([
                'doctor_id' => $validated['doctor_id'],
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'doctor_shift_id' => $validated['doctor_shift_id'],
                'file_id' => $fileToUseId,
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'waiting',
                'reason_for_visit' => $validated['reason_for_visit'] ?? 'متابعة',
                'is_new' => false,
                'number' => $queueNumber,
                'queue_number' => $queueNumber,
                'only_lab' => false, // This is a clinic visit, not lab-only
            ]);

            // Auto-attach favorite service if user has a selection for this doctor
            $userId = Auth::id();
            $fav = UserDocSelection::where('user_id', $userId)
                ->where('doc_id', $validated['doctor_id'])
                ->where('active', 1)
                ->first();

            if ($fav && $fav->fav_service) {
                $service = Service::with('serviceCosts.subServiceCost')->find($fav->fav_service);
                if ($service) {
                    $company = $newPatient->company_id ? Company::find($newPatient->company_id) : null;

                    $price = (float) $service->price;
                    $companyEnduranceAmount = 0;
                    $contractApproval = true;

                    if ($company) {
                        $contract = $company->contractedServices()
                            ->where('services.id', $service->id)
                            ->first();

                        if ($contract && $contract->pivot) {
                            $pivot = $contract->pivot;
                            $price = (float) $pivot->price;
                            $contractApproval = (bool) $pivot->approval;
                            if ($pivot->use_static) {
                                $companyEnduranceAmount = (float) $pivot->static_endurance;
                            } else {
                                if ($pivot->percentage_endurance > 0) {
                                    $companyServiceEndurance = ($price * (float) ($pivot->percentage_endurance ?? 0)) / 100;
                                    $companyEnduranceAmount = $price - $companyServiceEndurance;
                                } else {
                                    $companyServiceEndurance = ($price * (float) ($company->service_endurance ?? 0)) / 100;
                                    $companyEnduranceAmount = $price - $companyServiceEndurance;
                                }
                            }
                        }
                    }

                    $requestedService = RequestedService::create([
                        'doctorvisits_id' => $doctorVisit->id,
                        'service_id' => $service->id,
                        'user_id' => $userId,
                        'doctor_id' => $newPatient->doctor_id,
                        'price' => $price,
                        'amount_paid' => 0,
                        'endurance' => $companyEnduranceAmount,
                        'is_paid' => false,
                        'discount' => 0,
                        'discount_per' => 0,
                        'bank' => false,
                        'count' => 1,
                        'approval' => $contractApproval,
                        'done' => false,
                    ]);

                    // Auto-create RequestedServiceCost breakdowns
                    if ($service->serviceCosts->isNotEmpty()) {
                        $costEntriesData = [];
                        $baseAmountForCostCalc = $price * 1; // count is 1

                        foreach ($service->serviceCosts as $serviceCostDefinition) {
                            $calculatedCostAmount = 0;
                            $currentBase = $baseAmountForCostCalc;

                            if ($serviceCostDefinition->cost_type === 'after cost') {
                                $alreadyCalculatedCostsSum = collect($costEntriesData)->sum('amount');
                                $currentBase = $baseAmountForCostCalc - $alreadyCalculatedCostsSum;
                            }

                            if ($serviceCostDefinition->fixed !== null && $serviceCostDefinition->fixed > 0) {
                                $calculatedCostAmount = (float) $serviceCostDefinition->fixed;
                            } elseif ($serviceCostDefinition->percentage !== null && $serviceCostDefinition->percentage > 0) {
                                $calculatedCostAmount = ($currentBase * (float) $serviceCostDefinition->percentage) / 100;
                            }

                            if ($calculatedCostAmount > 0) {
                                $costEntriesData[] = [
                                    'requested_service_id' => $requestedService->id,
                                    'sub_service_cost_id' => $serviceCostDefinition->sub_service_cost_id,
                                    'service_cost_id' => $serviceCostDefinition->id,
                                    'amount' => round($calculatedCostAmount, 2),
                                    'created_at' => now(),
                                    'updated_at' => now(),
                                ];
                            }
                        }
                        if (!empty($costEntriesData)) {
                            RequestedServiceCost::insert($costEntriesData);
                        }
                    }
                }
            }
            
            DB::commit();

            // Queue non-blocking actions after successful commit
            \DB::afterCommit(function () use ($newPatient) {
                EmitPatientRegisteredJob::dispatch($newPatient->id);
                if (!empty($newPatient->phone)) {
                    SendWelcomeSmsJob::dispatch($newPatient->id, $newPatient->phone, $newPatient->name);
                }
            });

            // Load the doctorVisit relationship for the new patient
            $newPatient->load(['company', 'primaryDoctor', 'doctorVisit.doctor', 'doctorVisit.file']);

            return new PatientResource($newPatient);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to create clinic visit from history for doctor visit {$doctorVisit->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'فشل إنشاء زيارة العيادة من السجل.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get lab history for patients with the same phone number
     * Returns all patients with the same phone number who have lab requests
     */
    public function getLabHistory(Request $request, Patient $patient)
    {
        $validated = $request->validate([
            'phone' => 'required|string',
            'limit' => 'nullable|integer|min:1|max:100',
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date',
        ]);

        $phone = $validated['phone'];
        $limit = $validated['limit'] ?? null;
        $dateFrom = $validated['date_from'] ?? null;
        $dateTo = $validated['date_to'] ?? null;

        // Find all patients with the same phone number who have lab requests
        // PERFORMANCE: avoid loading every LabRequest and MainTest; we only need the count
        $patientsQuery = Patient::query()
            ->where('phone', $phone)
            ->whereHas('doctorVisit.labRequests')
            ->with([
                'doctorVisit' => function ($query) use ($dateFrom, $dateTo) {
                    $query
                        ->select(['id', 'patient_id', 'visit_date', 'created_at'])
                        ->when($dateFrom, function ($q) use ($dateFrom) {
                            $q->whereDate('visit_date', '>=', $dateFrom);
                        })
                        ->when($dateTo, function ($q) use ($dateTo) {
                            $q->whereDate('visit_date', '<=', $dateTo);
                        })
                        ->withCount([
                            'labRequests as lab_request_count' => function ($labQuery) {
                                $labQuery->where('valid', true);
                            }
                        ])
                        ->orderBy('created_at', 'desc');
                },
                'company:id,name',
            ])
            ->select(['id', 'name', 'phone', 'company_id', 'created_at'])
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $patientsQuery->limit($limit);
        }

        $patientsWithLabHistory = $patientsQuery->get();

        // Format the data for the frontend autocomplete
        $formattedResults = $patientsWithLabHistory->map(function ($patient) {
            $latestVisit = $patient->doctorVisit;
            $labRequestCount = $latestVisit ? ($latestVisit->lab_request_count ?? 0) : 0;
            
            return [
                'patient_id' => $patient->id,
                'visit_id' => $latestVisit ? $latestVisit->id : null,
                'patient_name' => $patient->name,
                'phone' => $patient->phone,
                'visit_date' => $latestVisit ? $latestVisit->visit_date : null,
                'lab_request_count' => $labRequestCount,
                'company_name' => $patient->company ? $patient->company->name : null,
                'autocomplete_label' => $patient->name . 
                    ($latestVisit && $latestVisit->visit_date ? " - " . $latestVisit->visit_date->format('d/M/Y') : '') . 
                    " (" . $labRequestCount . " tests)",
            ];
        });

        return response()->json([
            'data' => $formattedResults,
            'meta' => [
                'total_patients' => $patientsWithLabHistory->count(),
                'phone_searched' => $phone
            ]
        ]);
    }

    /**
     * Save a patient from online lab system to local system
     */
    public function saveFromOnlineLab(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'lab_requests' => 'required|array',
            'lab_requests.*.name' => 'required|string|max:255',
            'lab_requests.*.price' => 'required|numeric|min:0',
            'lab_requests.*.testId' => 'required|string',
            'lab_requests.*.container_id' => 'nullable|integer',
            'external_lab_id' => 'required|string',
            'external_patient_id' => 'required|string',
            'created_at' => 'nullable',
            'labId' => 'required|string',
        ]);

        $currentGeneralShift = Shift::open()->latest('created_at')->first();
        if (!$currentGeneralShift) {
            return response()->json(['message' => 'لا توجد وردية مفتوحة حالياً.'], 400);
        }

        $company = Company::where('lab2lab_firestore_id', $validated['labId'])->first();
        if (!$company) {
            return response()->json(['message' => 'العقد غير مرتبط مع الشركات يجب ربط العقد مع الشركه'], 400);
        }

        $patient = Patient::where('lab_to_lab_object_id', $validated['external_patient_id'])->first();
        if ($patient) {
            return response()->json(['message' => 'المريض موجود بالفعل في النظام.'], 400);
        }

        DB::beginTransaction();
        try {
            // Create a new file
            $file = File::create();
            
            // Get the next visit number
            $visitLabNumber = DoctorVisit::where('shift_id', $currentGeneralShift->id)->count() + 1;

            // Create the patient record
            $patient = Patient::create([
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? 0,
                'company_id' => $company->id,
                'gender' => 'male', // Default gender, could be made configurable
                'age_year' => 0, // Default age, could be made configurable
                'age_month' => 0,
                'age_day' => 0,
                'address' => '',
                'doctor_id' => 1, // Default doctor, should be configurable
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'visit_number' => $visitLabNumber,
                'lab_to_lab_id' => $validated['labId'],
                'result_auth' => false,
                'referred' => 'من مختبر خارجي',
                'labId' => $validated['labId'],
                'discount_comment' => 'مختبر خارجي: ' . $validated['external_lab_id'] . ' - مريض: ' . $validated['external_patient_id'],
                'lab_to_lab_object_id' => $validated['external_patient_id'], // Store the Firestore document ID
            ]);

            // Create the doctor visit
            $doctorVisit = $patient->doctorVisit()->create([
                'doctor_id' => 1, // Default doctor
                'user_id' => Auth::id(),
                'shift_id' => $currentGeneralShift->id,
                'doctor_shift_id' => null,
                'file_id' => $file->id,
                'visit_date' => Carbon::today(),
                'visit_time' => Carbon::now()->format('H:i:s'),
                'status' => 'lab_pending',
                'reason_for_visit' => 'طلب من مختبر خارجي',
                'is_new' => true,
                'only_lab' => true,
                'number' => $visitLabNumber, // Add the missing number field
            ]);

            // Create lab requests for each test
            foreach ($validated['lab_requests'] as $labRequestData) {
                // Find or create a main test based on the test name
          
                // Create the lab request
              $labRequest = LabRequest::create([
                    'main_test_id' => $labRequestData['testId'],
                    'pid' => $patient->id,
                    'doctor_visit_id' => $doctorVisit->id,
                    'hidden' => 0,
                    'is_lab2lab' => true, // Mark as lab-to-lab request
                    'valid' => true,
                    'no_sample' => false,
                    'price' => $labRequestData['price'],
                    'amount_paid' => 0,
                    'discount_per' => 0,
                    'is_bankak' => false,
                    'comment' => 'لاب تو' . $labRequestData['testId'],
                    'user_requested' => Auth::id(),
                    'approve' => 0,
                    'endurance' => 0,
                    'is_paid' => false,
                ]);
                if ($labRequest->mainTest->childTests->isNotEmpty()) {
                    $requestedResultsData = [];
                    foreach ($labRequest->mainTest->childTests as $childTest) {
                        $requestedResultsData[] = [
                            'lab_request_id' => $labRequest->id,
                            'patient_id' => $patient->id,
                            'main_test_id' => $labRequest->main_test_id,
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
                    
                    // Insert the requested results data
                    RequestedResult::insert($requestedResultsData);
                }
            }

            DB::commit();

            // Load the patient with relationships
            $patient->load(['doctorVisit', 'company', 'primaryDoctor']);

            // Update Firestore document to mark as delivered (non-blocking best-effort)
            try {
                $externalPatientId = $validated['external_patient_id'] ?? null;
                if ($externalPatientId) {
                    \App\Services\FirebaseService::updateFirestoreDocument(
                        'labToLap/global/patients',
                        $externalPatientId,
                        [
                            'sample_delivered' => true,
                            'delivered_at' => now()->toISOString(),
                            'delivered_by' => 'jawda-medical-system'
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to update Firestore document', [
                    'error' => $e->getMessage(),
                    'external_patient_id' => $validated['external_patient_id'] ?? null,
                ]);
            }

            // Send FCM topic notification to lab on success (non-blocking best-effort)
            try {
                $labNameForTopic = (string)($validated['labId'] ?? '');
                $safeTopic = preg_replace('/[^A-Za-z0-9\-_]/u', '_', trim($labNameForTopic));
                $testsNames = collect($validated['lab_requests'] ?? [])->pluck('name')->filter()->values()->all();
                $testsList = implode(' و ', $testsNames);
                $title = 'تم توصيل العينات الي المختبر';
                $body = 'تم توصيل العينات للمريض ' . $validated['name'] . ' صاحب التحاليل التاليه ' . $testsList;
                
                \App\Services\FirebaseService::sendTopicMessage($safeTopic, $title, $body);
            } catch (\Throwable $e) {
                Log::warning('Failed to send lab topic notification', [
                    'error' => $e->getMessage(),
                ]);
            }

            return response()->json([
                'message' => 'تم حفظ المريض بنجاح',
                'data' => new PatientResource($patient)
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to save online lab patient: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'message' => 'فشل في حفظ بيانات المريض',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    public function populatePatientChemistryData(Request $request, Doctorvisit $doctorvisit)
    {
        $main_test_id = $request->get('main_test_id');
        $chemistry =   Mindray::where('doctorvisit_id', '=', $doctorvisit->id)->first();
        if ($chemistry == null) {
            return  ['status' => false, 'message' => 'no data found'];
        }
        $bindings =   \App\Models\ChemistryBinder::all();
        /** @var \App\Models\ChemistryBinder $binding */
        $object = null;
        foreach ($bindings as $binding) {
            $object[$binding->name_in_mindray_table] = [
                'child_id' => [$binding->child_id_array],
                'result' => $chemistry[$binding->name_in_mindray_table]
            ];
            $child_array =  explode(',', $binding->child_id_array);
            foreach ($child_array as $child_id) {
                $requested_result = RequestedResult::whereChildTestId($child_id)->where('main_test_id', '=', $main_test_id)->where('patient_id', '=', $doctorvisit->patient->id)->first();
                if ($requested_result != null) {

                    $requested_result->update(['result' => $chemistry[$binding->name_in_mindray_table]]);
                }
            }
        }

        return ['status' => true, 'data' => new DoctorVisitResource($doctorvisit->load(['patient.subcompany', 'patient.doctor'])), 'chemistryObj' => $object];
    }

}
