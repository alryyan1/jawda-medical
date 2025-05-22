<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorSchedule;
use App\Models\Doctor;
use Illuminate\Http\Request;
use App\Http\Resources\DoctorScheduleResource; // Create this
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;

class DoctorScheduleController extends Controller
{
    public function __construct()
    {
        // Define permissions: e.g., 'manage doctor_schedules' for admin/doctor, 'view doctor_schedules' for receptionist
        // $this->middleware('can:view doctor_schedules')->only('index', 'getDoctorSchedule');
        // $this->middleware('can:manage doctor_schedules')->only('store', 'update', 'destroy');
    }

    /**
     * Get all schedules, possibly filtered by doctor.
     */
    public function index(Request $request)
    {
        $query = DoctorSchedule::with('doctor:id,name')->orderBy('doctor_id')->orderBy('day_of_week');

        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        }
        
        // For appointment booking, you might just fetch all for UI display logic
        $schedules = $query->get(); 
        return DoctorScheduleResource::collection($schedules);
    }

    /**
     * Get schedules for a specific doctor.
     */
    public function getDoctorSchedule(Doctor $doctor)
    {
        $schedules = $doctor->schedules()->orderBy('day_of_week')->orderBy('time_slot')->get();
        return DoctorScheduleResource::collection($schedules);
    }


    /**
     * Store a new schedule slot or update existing ones for a doctor.
     * Input: doctor_id, and an array of schedules: [{ day_of_week: 0, time_slot: 'morning'}, ...]
     */
    public function storeOrUpdateForDoctor(Request $request, Doctor $doctor)
    {
        // Authenticated user should be the doctor themselves or an admin with permission
        // if (Auth::id() !== $doctor->user_id && !Auth::user()->can('manage doctor_schedules_all')) {
        //     if (Auth::id() === $doctor->user_id && !Auth::user()->can('manage own_doctor_schedule'))
        //        return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $validated = $request->validate([
            'schedules' => 'required|array',
            'schedules.*.day_of_week' => ['required', 'integer', 'min:0', 'max:6'], // 0=Sunday, 6=Saturday
            'schedules.*.time_slot' => ['required', Rule::in(['morning', 'afternoon', 'evening', 'full_day'])],
            // You might add specific start/end times per slot if your schedule is more granular
            // 'schedules.*.start_time' => 'nullable|date_format:H:i',
            // 'schedules.*.end_time' => 'nullable|date_format:H:i|after:schedules.*.start_time',
        ]);

        // Simpler: Delete existing schedules for this doctor and create new ones
        // More complex: Upsert or diff and update
        DB::transaction(function () use ($doctor, $validated) {
            $doctor->schedules()->delete(); // Delete old schedule entries for this doctor
            $newSchedules = [];
            foreach ($validated['schedules'] as $scheduleData) {
                $newSchedules[] = [
                    'doctor_id' => $doctor->id,
                    'day_of_week' => $scheduleData['day_of_week'],
                    'time_slot' => $scheduleData['time_slot'],
                    // 'start_time' => $scheduleData['start_time'] ?? null,
                    // 'end_time' => $scheduleData['end_time'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            if (!empty($newSchedules)) {
                DoctorSchedule::insert($newSchedules);
            }
        });
        
        return DoctorScheduleResource::collection($doctor->schedules()->orderBy('day_of_week')->get());
    }

    // A simple store for a single schedule entry (less common if managing a whole week)
    // public function store(Request $request) { ... }


    // Destroy a specific schedule slot (might not be needed if using storeOrUpdateForDoctor)
    // public function destroy(DoctorSchedule $doctorSchedule) { ... }
}