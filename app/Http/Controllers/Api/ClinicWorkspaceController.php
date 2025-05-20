<?php
// app/Http/Controllers/Api/ClinicWorkspaceController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DoctorVisit;
use App\Http\Resources\DoctorVisitResource; // Or a dedicated ActivePatientListItemResource
use Illuminate\Http\Request;
use Carbon\Carbon;

class ClinicWorkspaceController extends Controller
{
    public function getActivePatients(Request $request)
    {
        $request->validate([
            'doctor_shift_id' => 'nullable|integer|exists:doctor_shifts,id',
            'doctor_id' => 'nullable|integer|exists:doctors,id', // Alternative if filtering by doctor directly
            'search' => 'nullable|string|max:100',
            'clinic_shift_id' => 'nullable|integer|exists:shifts,id', // General clinic shift
            'page' => 'nullable|integer|min:1',
        ]);

        $query = DoctorVisit::with(['patient', 'doctor:id,name']) // Eager load necessary relations
                            ->whereDate('visit_date', Carbon::today()) // Example: visits for today
                            ->whereNotIn('status', ['completed', 'cancelled']); // Example: not completed or cancelled

        // Filter by general clinic shift (if your system has overarching shifts)
        if ($request->filled('clinic_shift_id')) {
            $query->where('shift_id', $request->clinic_shift_id);
        }
        
        // Filter by specific doctor (from DoctorTabs selection)
        // This depends on whether DoctorTabs gives you a doctor_id or a doctor_shift_id
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
        } elseif ($request->filled('doctor_shift_id')) {
            // If filtering by doctor_shift_id, you might need to join or use whereHas
            // This assumes doctor_shift_id is directly on the DoctorVisit model
             $query->where('doctor_shift_id', $request->doctor_shift_id);
        }


        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->whereHas('patient', function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                  ->orWhere('id', $searchTerm); // Allow search by patient ID
            });
        }

        // Order by status (e.g., 'with_doctor' first), then by queue number or creation time
        $query->orderByRaw("CASE status WHEN 'with_doctor' THEN 1 WHEN 'waiting' THEN 2 ELSE 3 END")
              ->orderBy('created_at', 'asc'); // Or by appointment_time / queue_number

        $activeVisits = $query->paginate($request->get('per_page', 20));

        // You might want a specific resource that flattens Patient + Visit info for the list
        // For now, DoctorVisitResource which loads patient and doctor should work.
        return DoctorVisitResource::collection($activeVisits);
    }
}