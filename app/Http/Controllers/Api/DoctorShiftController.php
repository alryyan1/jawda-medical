<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorShift;
use App\Models\Doctor;
use App\Models\Shift; // General clinic shift
use Illuminate\Http\Request;
use App\Http\Resources\DoctorShiftResource;
use App\Http\Resources\DoctorVisitResource;
use App\Models\DoctorVisit;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Models\User;
use Illuminate\Support\Facades\Http as HttpClient;
use Illuminate\Support\Facades\Log;

class DoctorShiftController extends Controller
{
    /**
     * Check if the current shift is closed and prevent doctor shift creation if so
     */
    private function checkShiftIsOpen()
    {
        $currentGeneralShift = Shift::orderBy('id', 'desc')->first();

        if (!$currentGeneralShift) {
            return response()->json(['message' => 'لا توجد وردية   لبدء .'], 400);
        }

        // Check if shift is closed by either is_closed flag or closed_at timestamp
        if ($currentGeneralShift->is_closed || $currentGeneralShift->closed_at !== null) {
            return response()->json(['message' => 'لا يمكن فتح وردية طبيب جديد. الوردية مغلقة حالياً.'], 400);
        }

        return $currentGeneralShift;
    }

    /**
     * Get a list of currently active doctor shifts.
     * Used by the DoctorsTabs on the ClinicPage.
     */
    public function getActiveShifts(Request $request)
    {
        $query = DoctorShift::with([
            'doctor',
            'doctor.specialist:id,name,firestore_id',
        ])
            ->withCount('doctorVisits as patients_count') // Use withCount instead of N+1 queries
            ->where('status', 1);

        if ($request->has('clinic_shift_id')) {
            $query->where('shift_id', $request->clinic_shift_id);
        }

        $activeDoctorShifts = $query->get();

        return DoctorShiftResource::collection($activeDoctorShifts);
    }
    public function moneyCash(Request $request, DoctorShift $doctorShift)
    {
        return $doctorShift->doctor_credit_cash();
    }


    /**
     * Start a new shift session for a doctor.
     * This might be called by an admin or the doctor themselves.
     */
    public function startShift(Request $request)
    {
        // Permission check
        // if (!Auth::user()->can('start doctor_shift')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        // if (!Auth::user()->can('start doctor_shifts')) {
        //     return response()->json(['message' => 'لا يمكنك فتح وردية هذا الطبيب لأنك ليس لديك صلاحية للقيام بذلك.'], 403);
        // }

        // Check if shift is open before proceeding
        $shiftCheck = $this->checkShiftIsOpen();
        if ($shiftCheck instanceof \Illuminate\Http\JsonResponse) {
            return $shiftCheck; // Return error response if shift is closed
        }
        $currentGeneralShift = $shiftCheck;

        $validated = $request->validate([
            'doctor_id' => 'required|exists:doctors,id',
            // 'shift_id' => 'required|exists:shifts,id', // If a doctor can only have one session per general shift
            // 'start_time' => 'nullable|date_format:Y-m-d H:i:s', // Optional, defaults to now
        ]);

        // Check if this doctor already has an active shift for this general shift
        $existingActiveShift = DoctorShift::where('doctor_id', $validated['doctor_id'])
            // ->where('shift_id', $validated['shift_id']) // If a doctor can only have one session per general shift
            ->where('status', true) // Active
            ->first();

        if ($existingActiveShift) {
            //close all other shifts
            DoctorShift::where('doctor_id', $validated['doctor_id'])
                ->where('status', true)
                ->update(['status' => false]);
            // return response()->json(['message' => 'هذا الطبيب لديه وردية عمل مفتوحة بالفعل.'], 409); // 409 Conflict
        }

        $doctorShift = DoctorShift::create([
            'doctor_id' => $validated['doctor_id'],
            'shift_id' => $currentGeneralShift->id,
            'user_id' => Auth::id(), // User initiating this action
            'start_time' => $request->input('start_time', Carbon::now()),
            'status' => true, // Mark as active
        ]);

        return new DoctorShiftResource($doctorShift->load('doctor'));
    }

    /**
     * End an active shift session for a doctor.
     */
    public function endShift(Request $request, DoctorShift $doctorShift) // Route model binding
    {
        // Permission check
        // if (!Auth::user()->can('end doctor_shift')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }
        // if (!Auth::user()->can('end doctor_shifts')) {
        //     return response()->json(['message' => 'لا يمكنك إغلاق وردية هذا الطبيب لأنك ليس لديك صلاحية للقيام بذلك.'], 403);
        // }

        if (!$doctorShift->status) { // If already closed
            return response()->json(['message' => 'وردية عمل هذا الطبيب مغلقة بالفعل.'], 400);
        }

        if ($doctorShift->user_id !== Auth::id() && !Auth::user()->hasRole('admin')) {
            $userwhoOpenedShift = User::find($doctorShift->user_id);
            $name = $userwhoOpenedShift->name;
            return response()->json(['message' => "فقط الموظف  $name يمكنه إغلاق وردية هذا الطبيب."], 403);
        }


        $validated = $request->validate([
            // 'end_time' => 'nullable|date_format:Y-m-d H:i:s|after_or_equal:start_time',
            // You might add fields for cash reconciliation here if done at shift end
        ]);

        $doctorShift->update([
            'status' => false, // Mark as closed
            'end_time' => $request->input('end_time', Carbon::now()),
            // Potentially update other fields like reconciled amounts by Auth::id()
        ]);

        return new DoctorShiftResource($doctorShift->load('doctor'));
    }

    public function moneyInsu(Request $request, DoctorShift $doctorShift)
    {
        return $doctorShift->doctor_credit_company;
    }
    public function totalMoney(Request $request, DoctorShift $doctorShift)
    {
        return $doctorShift->doctor_credit_cash() + $doctorShift->doctor_credit_company + $doctorShift->doctor->static_wage;
    }

    /**
     * Display a listing of the DoctorShift resources.
     * This serves as the data source for reports like the "Doctor Shifts Report".
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    // public function index(Request $request)
    // {
    //     // Permission check: e.g., can('list all_doctor_shifts') or 'view doctor_shift_reports'
    //     // if (!Auth::user()->can('list all_doctor_shifts')) {
    //     //     return response()->json(['message' => 'Unauthorized'], 403);
    //     // }

    //     $request->validate([
    //         'page' => 'nullable|integer|min:1',
    //         'per_page' => 'nullable|integer|min:5|max:100', // Control items per page
    //         'date_from' => 'nullable|date_format:Y-m-d',
    //         'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
    //         'doctor_id' => 'nullable|integer|exists:doctors,id',
    //         'doctor_name_search' => 'nullable|string|max:255', // For searching by doctor name
    //         'user_id_opened' => 'nullable|integer|exists:users,id', // User who started the DoctorShift
    //         'status' => 'nullable|string|in:0,1,all', // '0' for closed, '1' for open, 'all' for both
    //         'shift_id' => 'nullable|integer|exists:shifts,id', // Filter by general clinic shift ID
    //         'sort_by' => 'nullable|string|in:start_time,end_time,doctor_name,user_name,status', // Allowed sort fields
    //         'sort_direction' => 'nullable|string|in:asc,desc',
    //     ]);

    //     $query = DoctorShift::with([
    //         'doctor:id,name,specialist_id', // Eager load doctor with specialist_id
    //         'doctor.specialist:id,name',    // Eager load specialist details
    //         'user:id,name,username',        // User who opened/managed the DoctorShift record
    //         'generalShift:id,created_at,closed_at', // The main clinic shift
    //         // Optional: Eager load counts for quick display if needed, but can make query heavier
    //         // 'doctorVisitsCount' => fn($q) => $q->selectRaw('count(*) as aggregate'),
    //     ]);

    //     // Filtering
    //     if ($request->filled('doctor_id')) {
    //         $query->where('doctor_id', $request->doctor_id);
    //     }
    //     if ($request->filled('doctor_name_search')) {
    //         $searchTerm = $request->doctor_name_search;
    //         $query->whereHas('doctor', function ($q) use ($searchTerm) {
    //             $q->where('name', 'LIKE', "%{$searchTerm}%");
    //         });
    //     }
    //     if ($request->filled('user_id_opened')) {
    //         $query->where('user_id', $request->user_id_opened);
    //     }
    //     if ($request->has('status') && $request->status !== 'all' && $request->status !== '') {
    //         $query->where('status', (bool)$request->status);
    //     }
    //     if ($request->filled('date_from')) {
    //         $query->whereDate('start_time', '>=', Carbon::parse($request->date_from)->startOfDay());
    //     }
    //     if ($request->filled('date_to')) {
    //         // If filtering by end_time, need to be careful if end_time can be null for open shifts
    //         // For start_time based filtering:
    //         $query->whereDate('start_time', '<=', Carbon::parse($request->date_to)->endOfDay());
    //         // Or if you want to include shifts that *ended* within the range:
    //         // $query->where(function ($q) use ($request) {
    //         //     $q->whereBetween('start_time', [Carbon::parse($request->date_from)->startOfDay(), Carbon::parse($request->date_to)->endOfDay()])
    //         //       ->orWhereBetween('end_time', [Carbon::parse($request->date_from)->startOfDay(), Carbon::parse($request->date_to)->endOfDay()]);
    //         // });
    //     }
    //     if ($request->filled('shift_id')) {
    //         $query->where('shift_id', $request->shift_id);
    //     }

    //     // Sorting
    //     $sortBy = $request->input('sort_by', 'start_time');
    //     $sortDirection = $request->input('sort_direction', 'desc');

    //     if ($sortBy === 'doctor_name') {
    //         // Sort by related table requires join or more complex subquery for optimal performance
    //         // For simplicity with eager loading, you might sort on collection after fetching,
    //         // or use a join. Let's try with a join for DB-level sorting.
    //         $query->join('doctors', 'doctor_shifts.doctor_id', '=', 'doctors.id')
    //               ->orderBy('doctors.name', $sortDirection)
    //               ->select('doctor_shifts.*'); // Important to select all columns from doctor_shifts
    //     } elseif ($sortBy === 'user_name') {
    //         $query->join('users', 'doctor_shifts.user_id', '=', 'users.id')
    //               ->orderBy('users.name', $sortDirection)
    //               ->select('doctor_shifts.*');
    //     } else {
    //         $query->orderBy($sortBy, $sortDirection);
    //     }
    //      // Add secondary sort for consistency
    //     if ($sortBy !== 'start_time') {
    //         $query->orderBy('start_time', 'desc');
    //     }


    //     $perPage = $request->input('per_page', 15); // Default items per page
    //     $doctorShifts = $query->paginate($perPage);

    //     return DoctorShiftResource::collection($doctorShifts);
    // }
    /**
     * Display a listing of the DoctorShift resources for reporting.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'doctor_name_search' => 'nullable|string|max:255',
            'user_id_opened' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|string|in:0,1,all',
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'sort_by' => 'nullable|string|in:start_time,end_time,doctor_name,user_name,status,total_entitlement,id',
            'sort_direction' => 'nullable|string|in:asc,desc',
            // include_financials is handled by $request->boolean() - no strict validation needed
        ]);

        // Base eager loading - lightweight relations only
        $eagerLoad = [
            'doctor',
            'doctor.specialist:id,name',
            'user:id,name,username',
            'generalShift:id,created_at,closed_at',
        ];

        // Only load heavy relations when financials are explicitly requested
        if ($request->boolean('include_financials')) {
            $eagerLoad = array_merge($eagerLoad, [
                'visits.patient.company',
                'visits.requestedServices.service',
                'visits.patientLabRequests.mainTest',
            ]);
        }

        $query = DoctorShift::with($eagerLoad)
            ->withCount('doctorVisits as patients_count');

        // Filtering
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }
        if ($request->filled('doctor_name_search')) {
            $searchTerm = $request->doctor_name_search;
            $query->whereHas('doctor', function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%");
            });
        }
        if ($request->filled('user_id_opened')) {
            $query->where('user_id', $request->user_id_opened);
        }
        if ($request->has('status') && $request->status !== 'all' && $request->status !== '') {
            $query->where('status', (bool)$request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('today')) {
            $query->whereDate('created_at', '>=', Carbon::today()->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }
        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'start_time');
        $sortDirection = $request->input('sort_direction', 'desc');

        if ($sortBy === 'doctor_name') {
            $query->join('doctors', 'doctor_shifts.doctor_id', '=', 'doctors.id')
                ->orderBy('doctors.name', $sortDirection)
                ->select('doctor_shifts.*');
        } elseif ($sortBy === 'user_name') {
            $query->join('users', 'doctor_shifts.user_id', '=', 'users.id')
                ->orderBy('users.name', $sortDirection)
                ->select('doctor_shifts.*');
        } elseif ($sortBy !== 'total_entitlement') {
            $query->orderBy($sortBy, $sortDirection);
        }

        // Add secondary sort for consistency
        if ($sortBy !== 'start_time' && $sortBy !== 'id') {
            $query->orderBy('start_time', 'desc');
        }
        $query->orderBy('doctor_shifts.id', 'desc');

        $perPage = $request->input('per_page', 15);
        $doctorShifts = $query->paginate($perPage);

        return DoctorShiftResource::collection($doctorShifts);
    }
    /**
     * Display the specified doctor shift.
     *
     * @param  \App\Models\DoctorShift  $doctorShift
     * @return \App\Http\Resources\DoctorShiftResource
     */
    public function show(DoctorShift $doctorShift)
    {
        // Permission check if needed
        // if (!Auth::user()->can('view doctor_shift')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $doctorShift->load(['doctor', 'user', 'generalShift']);
        return new DoctorShiftResource($doctorShift);
    }

    /**
     * Return shift service costs (مصروف الخدمات) for this doctor shift.
     * Same data as in the PDF report: cost name and total amount per sub-service cost type.
     */
    public function shiftServiceCosts(DoctorShift $doctorShift)
    {
        $doctorShift->load([
            'visits' => function ($query) {
                $query->with(['requestedServices.service.service_costs.subServiceCost']);
            },
        ]);
        $costs = $doctorShift->shift_service_costs();
        return response()->json(['data' => $costs]);
    }

    public function showFinancialSummary(Request $request, DoctorShift $doctorShift)
    {
        // Permission check: e.g., can('view doctor_shift_financial_summary')
        // if (!Auth::user()->can('view doctor_shift_financial_summary')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Eager load necessary data
        $doctorShift->load([
            'doctor', // For doctor's percentages and static wage (if applicable per shift)
            'generalShift', // For context
            'doctorVisits' => function ($query) {
                $query->with([
                    'patient:id,name,company_id', // Include company_id to differentiate insurance
                    'requestedServices' => function ($rsQuery) {
                        $rsQuery->with('service'); // For service details if needed
                    }
                ])->orderBy('created_at');
            }
        ]);

        if (!$doctorShift->doctor) {
            return response()->json(['message' => 'Doctor details not found for this shift.'], 404);
        }

        $doctor_cash_share_total = $doctorShift->doctor_credit_cash();
        $doctor_insurance_share_total = $doctorShift->doctor_credit_company();
        $total_doctor_share = $doctor_cash_share_total + $doctor_insurance_share_total;
        $summary = [
            'doctor_shift_id' => $doctorShift->id,
            'doctor_name' => $doctorShift->doctor->name,
            'start_time' => $doctorShift->start_time?->toIso8601String(),
            'end_time' => $doctorShift->end_time?->toIso8601String(),
            'status' => $doctorShift->status ? 'Open' : 'Closed',
            'total_patients' => $doctorShift->doctorVisits->count(),
            'doctor_fixed_share_for_shift' => $doctorShift->doctor->static_wage, // This needs clarification: is static_wage per shift, per day, per month?
            // For this example, let's assume static_wage is per SHIFT if this DoctorShift is closed.
            // If the shift is open, fixed share might not apply yet or is pro-rated.
            'doctor_cash_share_total' => $doctor_cash_share_total,
            'total_doctor_share' => $total_doctor_share,
            'doctor_insurance_share_total' => $doctor_insurance_share_total,
            'patients_breakdown' => [],
        ];





        return response()->json(['data' => $summary]);
    }
    public function getDoctorsWithShiftStatus(Request $request)
    {
        // Permission check if needed
        // if (!Auth::user()->can('manage doctor_shifts')) { /* ... */ }

        $query = Doctor::query()->with(['user:id,name,username', 'specialist:id,name']); // Eager load what's needed for display

        // Get all currently open doctor shifts (status = true, or start_time set and end_time null)
        // This logic depends on how you define an "open" DoctorShift
        $openDoctorShiftIds = DoctorShift::latestGeneralShift() // Use your scope for active shifts
            ->pluck('doctor_id', 'id') // Get doctor_id keyed by DoctorShift id
            ->all(); // ['doctor_shift_id' => 'doctor_id'] -> this might be inverted for easier lookup
        // Let's rather get it as: ['doctor_id' => 'doctor_shift_id']

        $openDoctorShifts = DoctorShift::latestGeneralShift()
            ->get()
            ->keyBy('doctor_id'); // Key by doctor_id for easy lookup

        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhereHas('specialist', function ($sq) use ($searchTerm) {
                        $sq->where('name', 'LIKE', "%{$searchTerm}%");
                    });
            });
        }

        $doctors = $query->orderBy('name')->get();

        $doctorsWithStatus = $doctors->map(function ($doctor) use ($openDoctorShifts) {
            $activeShift = $openDoctorShifts->get($doctor->id);
            return [
                'id' => $doctor->id,
                'name' => $doctor->name,
                'specialist_name' => $doctor->specialist->name ?? null,
                'is_on_shift' => !!$activeShift, // True if an active shift exists for this doctor
                'current_doctor_shift_id' => $activeShift->id ?? null, // The ID of their current DoctorShift record
            ];
        });

        return response()->json($doctorsWithStatus->values());
    }
    // In DoctorVisitController.php or a new VisitActionController.php
    public function reassignToShift(Request $request, DoctorVisit $visit)
    {
        // $this->authorize('reassign', $visit);
        $validated = $request->validate([
            'target_doctor_shift_id' => 'required|integer|exists:doctor_shifts,id',
        ]);

        $targetDoctorShift = DoctorShift::findOrFail($validated['target_doctor_shift_id']);

        // Business logic:
        // 1. Check if targetDoctorShift is active and belongs to the same doctor or if user has permission.
        // 2. Ensure it's not the same shift.
        if ($visit->doctor_shift_id == $targetDoctorShift->id) {
            return response()->json(['message' => 'الزيارة موجودة بالفعل في هذه المناوبة.'], 409);
        }
        if (!$targetDoctorShift->status) { // Target shift not active
            return response()->json(['message' => 'لا يمكن نقل الزيارة إلى مناوبة مغلقة.'], 400);
        }
        // Add more rules like: can only copy to same doctor's shift unless admin.
        // if ($visit->doctor_id !== $targetDoctorShift->doctor_id && !Auth::user()->can('reassign_visit_any_doctor')) { ... }


        // Option A: Move (update existing visit)
        $visit->update([
            'doctor_shift_id' => $targetDoctorShift->id,
            'doctor_id' => $targetDoctorShift->doctor_id, // Update doctor if target shift is for different doc
            'queue_number' => DoctorVisit::where('doctor_shift_id', $targetDoctorShift->id)->count() + 1, // Recalculate queue
            // Reset status to 'waiting' for the new shift? Or keep current status?
            'status' => 'waiting',
        ]);
        // Option B: Copy (create new visit, mark old as 'transferred' or similar) - More complex
        // ...

        return new DoctorVisitResource($visit->fresh()->load(['patient.subcompany', 'doctor']));
    }
    // app/Http/Controllers/Api/DoctorShiftController.php
    public function updateProofingFlags(Request $request, DoctorShift $doctorShift)
    {
        // $this->authorize('update_proofing_flags', $doctorShift); // Permission check

        $validated = $request->validate([
            'is_cash_revenue_prooved' => 'sometimes|boolean',
            'is_cash_reclaim_prooved' => 'sometimes|boolean',
            'is_company_revenue_prooved' => 'sometimes|boolean',
            'is_company_reclaim_prooved' => 'sometimes|boolean',
        ]);

        // Check if is_cash_revenue_prooved is being set to true and close the shift
        $shouldCloseShift = isset($validated['is_cash_reclaim_prooved']);
        // return ['shouldCloseShift' => $shouldCloseShift];
        if (isset($validated['is_cash_reclaim_prooved'])) {
            // return response()->json(['message' => 'لا يمكن تغيير الحالة الى مغلقة لان المناوبة مفتوحة.'], 400);
            // Close the doctor shift
            $doctorShift->update([
                ...$validated,
                'status' => false,
                'end_time' => Carbon::now(),
            ]);

            // Emit realtime update event (fire-and-forget)
            try {
                $url = config('services.realtime.url') . '/emit/doctor-shift-closed';
                HttpClient::withHeaders(['x-internal-token' => config('services.realtime.token')])
                    ->post($url, [
                        'doctor_shift' => (new DoctorShiftResource($doctorShift->load(['doctor', 'user', 'generalShift'])))->resolve(),
                    ]);
            } catch (\Exception $e) {
                Log::warning('Failed to emit doctor-shift-closed realtime event: ' . $e->getMessage());
            }
        } else {
            $doctorShift->update($validated);
        }

        return new DoctorShiftResource($doctorShift->load(['doctor', 'user', 'generalShift']));
    }
    // You might add show, update, destroy for full CRUD management of DoctorShift records if needed.
    // For 'update', you might allow changing financial proof flags, notes, etc.
}
