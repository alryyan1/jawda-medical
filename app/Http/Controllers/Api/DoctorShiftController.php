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

class DoctorShiftController extends Controller
{
    /**
     * Get a list of currently active doctor shifts.
     * Used by the DoctorsTabs on the ClinicPage.
     */
    public function getActiveShifts(Request $request)
    {
        $query = DoctorShift::with([
            'doctor', // Get specialist_id
            'doctor.specialist', // Eager load specialist name from doctor
        ]);
            // ->activeToday(); // Your scope for active shifts today
            
        // Additional withCount examples:
        // Count doctor visits with specific conditions:
        // ])->withCount([
        //     'doctorVisits as total_visits_count',
        //     'doctorVisits as waiting_visits_count' => function ($query) {
        //         $query->where('status', 'waiting');
        //     },
        //     'doctorVisits as completed_visits_count' => function ($query) {
        //         $query->where('status', 'completed');
        //     }
        // ]);

        if ($request->has('clinic_shift_id')) {
            $query->where('shift_id', $request->clinic_shift_id);
        }
        $query->where('status',1);

        $activeDoctorShifts = $query->get()->map(function ($doctorShift) {
            // Determine current patient status for the doctor
            // This is a simplified example. You might have more complex logic or a dedicated 'current_patient_visit_id' on DoctorShift
            $currentVisit = DoctorVisit::
                where('doctor_shift_id', $doctorShift->id) // Link to this specific doctor shift session
                ->where('status', 'with_doctor') // Check if any patient is currently 'with_doctor'
                ->orderBy('updated_at', 'desc') // Get the most recent one
                ->first();

            // Count patients assigned to this doctor in this shift (e.g., 'waiting' or 'with_doctor')
            $patientCount = DoctorVisit::
                where('doctor_shift_id', $doctorShift->id)
                // ->whereIn('status', ['waiting', 'with_doctor']) // Count relevant statuses
                ->count();

            // Add these computed properties to the DoctorShift object before it goes to the resource
            // $doctorShift->current_patient_visit_id = $currentVisit?->id;
            // $doctorShift->is_examining = !!$currentVisit;
            $doctorShift->patients_count = $patientCount;

            return $doctorShift;
        });

        // Order after mapping custom attributes if sorting by them
        // For now, let's assume Doctor model has user_id linking to a user, and we sort by user's name for Doctor.
        // Or simply order by doctor's name directly from the 'doctors' table.
        // The join and select('doctor_shifts.*') from previous version was good for direct DB ordering.
        // If ordering after map:
        // $activeDoctorShifts = $activeDoctorShifts->sortBy(function($ds) {
        //     return $ds->doctor->name ?? '';
        // });


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
            return response()->json(['message' => 'هذا الطبيب لديه وردية عمل مفتوحة بالفعل.'], 409); // 409 Conflict
        }
        $latestShift = Shift::latest('id')->first();
        if (!$latestShift || !$latestShift->id) {
            return response()->json(['message' => 'يجب فتح وردية مالية أولاً.'], 400);
        }
        $doctorShift = DoctorShift::create([
            'doctor_id' => $validated['doctor_id'],
            'shift_id' => Shift::latest('id')->first()?->id,
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
            return response()->json(['message' => 'لا يمكنك إغلاق وردية هذا الطبيب لأنك لست المسؤول عنها.'], 403);
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
        // Permission check: e.g., can('list all_doctor_shifts') or 'view doctor_shift_reports'
        // if (!Auth::user()->can('list all_doctor_shifts')) { // Example
        //     if (!Auth::user()->can('list_own_doctor_shifts')) {
        //          return response()->json(['message' => 'Unauthorized'], 403);
        //     }
        //     // If user can only list their own (as opener), add this condition by default
        //     $request->merge(['user_id_opened' => Auth::id()]);
        // }


        $request->validate([
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:5|max:100',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'doctor_name_search' => 'nullable|string|max:255',    // NEW
            'user_id_opened' => 'nullable|integer|exists:users,id', // NEW (user_id on doctor_shifts table)
            'status' => 'nullable|string|in:0,1,all',
            'shift_id' => 'nullable|integer|exists:shifts,id',    // General clinic shift ID
            'sort_by' => 'nullable|string|in:start_time,end_time,doctor_name,user_name,status,total_entitlement', // Added total_entitlement
            'sort_direction' => 'nullable|string|in:asc,desc',
        ]);

        $query = DoctorShift::with([
            'doctor:id,name,specialist_id,static_wage,cash_percentage,company_percentage,lab_percentage', // Crucial for entitlement calculation
            'doctor.specialist:id,name',
            'user:id,name,username', // User who opened/managed the DoctorShift (aliased as 'user' in model)
            'generalShift:id,created_at,closed_at', // The main clinic shift
            // Eager load relations needed for entitlement calculations if done in PHP/Resource
            // These are needed by the doctor_credit_cash/company methods in DoctorShift model
            'visits.patient.company', // company_id for patient is enough for isCompany check
            'visits.requestedServices.service',
            'visits.patientLabRequests.mainTest',
                 ]);

        // Filtering
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }
        if ($request->filled('doctor_name_search')) { // NEW
            $searchTerm = $request->doctor_name_search;
            $query->whereHas('doctor', function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%");
            });
        }
        if ($request->filled('user_id_opened')) { // NEW (filters by the user_id on doctor_shifts table)
            $query->where('user_id', $request->user_id_opened);
        }
        if ($request->has('status') && $request->status !== 'all' && $request->status !== '') {
            $query->where('status', (bool)$request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }
        if ($request->filled('shift_id')) { // Filter by general clinic shift ID
            $query->where('shift_id', $request->shift_id);
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'start_time');
        $sortDirection = $request->input('sort_direction', 'desc');

        if ($sortBy === 'doctor_name') {
            $query->join('doctors', 'doctor_shifts.doctor_id', '=', 'doctors.id')
                  ->orderBy('doctors.name', $sortDirection)
                  ->select('doctor_shifts.*');
        } elseif ($sortBy === 'user_name') { // User who opened the shift
            $query->join('users', 'doctor_shifts.user_id', '=', 'users.id')
                  ->orderBy('users.name', $sortDirection)
                  ->select('doctor_shifts.*');
        } elseif ($sortBy === 'total_entitlement') {
            // Sorting by calculated field requires either calculating in DB (complex)
            // or fetching all then sorting in PHP (inefficient for large datasets).
            // For pagination, it's best if the DB can handle it.
            // This is a placeholder; a raw expression or a stored generated column would be better for performance.
            // For now, this will sort by ID as a fallback if 'total_entitlement' is not a direct column.
            // $query->orderBy($sortBy, $sortDirection); // This will fail if not a DB column
            // To make this work, you'd typically sort on the collection AFTER pagination,
            // or add a raw select for the calculated entitlement and sort by that alias.
            // For simplicity of this response, we'll rely on the Resource to calculate it for display.
            // Sorting will be on DB columns for now. If you need to sort by calculated,
            // you'll need a more complex query or sort the collection after fetching.
            // $query->orderBy('id', $sortDirection); // Fallback sort
        }
        else {
            $query->orderBy($sortBy, $sortDirection);
        }
        // Add secondary sort for consistency if primary sort isn't unique enough
        if ($sortBy !== 'start_time' && $sortBy !== 'id') {
            $query->orderBy('start_time', 'desc');
        }
        $query->orderBy('doctor_shifts.id', 'desc'); // Final tie-breaker


        $perPage = $request->input('per_page', 15);
        $doctorShifts = $query->paginate($perPage);

        // The DoctorShiftResource will handle calculating the entitlement values
        return DoctorShiftResource::collection($doctorShifts);
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

        return response()->json($doctorsWithStatus);
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

        $doctorShift->update($validated);
        return new DoctorShiftResource($doctorShift->load(['doctor', 'user', 'generalShift']));
    }
    // You might add show, update, destroy for full CRUD management of DoctorShift records if needed.
    // For 'update', you might allow changing financial proof flags, notes, etc.
}
