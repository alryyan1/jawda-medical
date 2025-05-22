<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorShift;
use App\Models\Doctor;
use App\Models\Shift; // General clinic shift
use Illuminate\Http\Request;
use App\Http\Resources\DoctorShiftResource;
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
        // Permission check (example)
        // if (!Auth::user()->can('view active_doctor_shifts')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Determine the current general clinic shift if your system uses one
        // $currentClinicShift = Shift::where('is_closed', false)->orderBy('start_datetime', 'desc')->first();
        // For now, let's assume we don't filter by a general clinic shift unless passed

        $query = DoctorShift::with('doctor') // Eager load only necessary doctor fields
            ->activeToday(); // Use the scope defined in the model

        // if ($request->has('clinic_shift_id')) {
        //     $query->where('shift_id', $request->clinic_shift_id);
        // }

        // You might want to order them by doctor name or by when their shift started
        // $activeDoctorShifts = $query->join('doctors', 'doctor_shifts.doctor_id', '=', 'doctors.id')
        //     ->orderBy('doctors.name')
        //     ->select('doctor_shifts.*') // Avoid ambiguity
        //     ->get();

        $activeDoctorShifts = $query->get();

        return DoctorShiftResource::collection($activeDoctorShifts);
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

        if (!$doctorShift->status) { // If already closed
            return response()->json(['message' => 'وردية عمل هذا الطبيب مغلقة بالفعل.'], 400);
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

    /**
     * Display a listing of the resource. (Standard CRUD index)
     */
    public function index(Request $request) // This will serve as the report endpoint
    {
        // Permission check: e.g., can('view doctor_shift_reports')
        // if (!Auth::user()->can('view doctor_shift_reports')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $query = DoctorShift::with([
            'doctor',
            'user', // User who opened/managed the DoctorShift record
            'generalShift', // The main clinic shift
            // Optional: Eager load counts or sums if needed directly for report
            // 'doctorVisitsCount' => fn($q) => $q->selectRaw('count(*) as aggregate'),
        ])
            ->latest('start_time'); // Default order

        // Filtering
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }
        if ($request->has('status') && $request->status !== '') { // '0' for closed, '1' for open
            $query->where('status', (bool)$request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('start_time', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->whereDate('start_time', '<=', Carbon::parse($request->date_to)->endOfDay());
        }
        if ($request->filled('shift_id')) { // Filter by general clinic shift ID
            $query->where('shift_id', $request->shift_id);
        }

        $doctorShifts = $query->paginate($request->get('per_page', 20));

        // Optionally, you could add totals/summaries to the pagination meta if needed for the report
        // For example, total hours worked, total patients seen across the fetched shifts.
        // This would require more complex queries or calculations.

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

        $summary = [
            'doctor_shift_id' => $doctorShift->id,
            'doctor_name' => $doctorShift->doctor->name,
            'start_time' => $doctorShift->start_time?->toIso8601String(),
            'end_time' => $doctorShift->end_time?->toIso8601String(),
            'status' => $doctorShift->status ? 'Open' : 'Closed',
            'total_patients' => $doctorShift->doctorVisits->count(),
            'doctor_fixed_share_for_shift' => 0, // This needs clarification: is static_wage per shift, per day, per month?
            // For this example, let's assume static_wage is per SHIFT if this DoctorShift is closed.
            // If the shift is open, fixed share might not apply yet or is pro-rated.
            'doctor_cash_share_total' => 0,
            'doctor_insurance_share_total' => 0,
            'patients_breakdown' => [],
        ];

        // Determine doctor's entitlement percentages
        $cashPercentage = $doctorShift->doctor->cash_percentage / 100; // Convert to decimal
        $companyPercentage = $doctorShift->doctor->company_percentage / 100;
        // $labPercentage = $doctorShift->doctor->lab_percentage / 100; // If lab services contribute differently

        // For simplicity, let's assume static_wage applies if the DoctorShift record is marked as 'closed'
        // Your business logic for static wage might be different (e.g., per day, per number of hours)
        if (!$doctorShift->status && $doctorShift->doctor->static_wage > 0) {
            // This is a simplification. Real static wage might be per day worked,
            // or if a DoctorShift represents a full scheduled work session.
            // If a doctor works multiple short DoctorShift sessions in a day, how static_wage is applied needs definition.
            // For this example, assume it's a one-time wage for this completed DoctorShift.
            $summary['doctor_fixed_share_for_shift'] = (float) $doctorShift->doctor->static_wage;
        }


        foreach ($doctorShift->doctorVisits as $visit) {
            $visitTotalPaid = 0;
            $visitDoctorEntitlement = 0;

            foreach ($visit->requestedServices as $rs) {
                // We sum amount_paid for each requested service in this visit
                $visitTotalPaid += (float) $rs->amount_paid;

                // Calculate doctor's share from this service's payment
                // This logic assumes percentages apply to the amount_paid for the service.
                // It could also apply to the price of the service before company endurance.
                // This needs to match your exact business rule.
                $amountForDoctorCalculation = (float) $rs->amount_paid; // Or $rs->price if calculation is on price before endurance

                if ($visit->patient->company_id) { // Insurance patient
                    $visitDoctorEntitlement += $amountForDoctorCalculation * $companyPercentage;
                } else { // Cash patient
                    $visitDoctorEntitlement += $amountForDoctorCalculation * $cashPercentage;
                }
                // Consider lab_percentage if some services are lab and have different entitlements
            }

            $summary['patients_breakdown'][] = [
                'patient_id' => $visit->patient->id,
                'patient_name' => $visit->patient->name,
                'visit_id' => $visit->id,
                'total_paid_for_visit' => $visitTotalPaid,
                'doctor_share_from_visit' => $visitDoctorEntitlement,
                'is_insurance_patient' => !!$visit->patient->company_id,
            ];

            if ($visit->patient->company_id) {
                $summary['doctor_insurance_share_total'] += $visitDoctorEntitlement;
            } else {
                $summary['doctor_cash_share_total'] += $visitDoctorEntitlement;
            }
        }

        $summary['total_doctor_share'] = $summary['doctor_fixed_share_for_shift'] +
            $summary['doctor_cash_share_total'] +
            $summary['doctor_insurance_share_total'];

        return response()->json(['data' => $summary]);
    }
    public function getDoctorsWithShiftStatus(Request $request)
    {
        // Permission check if needed
        // if (!Auth::user()->can('manage doctor_shifts')) { /* ... */ }

        $query = Doctor::query()->with(['user:id,name,username', 'specialist:id,name']); // Eager load what's needed for display

        // Get all currently open doctor shifts (status = true, or start_time set and end_time null)
        // This logic depends on how you define an "open" DoctorShift
        $openDoctorShiftIds = DoctorShift::activeToday() // Use your scope for active shifts
            ->pluck('doctor_id', 'id') // Get doctor_id keyed by DoctorShift id
            ->all(); // ['doctor_shift_id' => 'doctor_id'] -> this might be inverted for easier lookup
        // Let's rather get it as: ['doctor_id' => 'doctor_shift_id']

        $openDoctorShifts = DoctorShift::activeToday()
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
    // You might add show, update, destroy for full CRUD management of DoctorShift records if needed.
    // For 'update', you might allow changing financial proof flags, notes, etc.
}
