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

        $query = DoctorShift::with('doctor:id,name') // Eager load only necessary doctor fields
            ->activeToday(); // Use the scope defined in the model

        if ($request->has('clinic_shift_id')) {
            $query->where('shift_id', $request->clinic_shift_id);
        }

        // You might want to order them by doctor name or by when their shift started
        $activeDoctorShifts = $query->join('doctors', 'doctor_shifts.doctor_id', '=', 'doctors.id')
            ->orderBy('doctors.name')
            ->select('doctor_shifts.*') // Avoid ambiguity
            ->get();

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
    public function index(Request $request)
    {
        // For an admin page to manage all doctor shifts
        $query = DoctorShift::with(['doctor:id,name', 'user:id,name', 'generalShift:id,name']) // Example generalShift relation name
            ->latest(); // Order by most recent first

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }
        if ($request->has('status') && $request->status !== '') { // 0 or 1
            $query->where('status', (bool)$request->status);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('start_time', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('start_time', '<=', $request->date_to);
        }

        $doctorShifts = $query->paginate($request->get('per_page', 15));
        return DoctorShiftResource::collection($doctorShifts);
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
