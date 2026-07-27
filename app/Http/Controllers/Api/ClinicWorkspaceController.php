<?php

// app/Http/Controllers/Api/ClinicWorkspaceController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DoctorVisitListItemResource;
use App\Models\DoctorVisit;
use Illuminate\Http\Request;

class ClinicWorkspaceController extends Controller
{
    public function getActivePatients(Request $request)
    {
        $request->validate([
            'doctor_shift_id' => 'nullable|integer|exists:doctor_shifts,id',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'date' => 'nullable|date_format:Y-m-d',
            'search' => 'nullable|string|max:100',
        ]);

        $query = DoctorVisit::with([
            'patient.company',   // needed for company styling + filter
            'requestedServices', // needed only for balance_due calculation
            'patientLabRequests', // needed only for balance_due calculation
        ])
            ->withCount(['requestedServices', 'patientLabRequests'])
            ->addSelect([
                // How many visits (including this one) share this visit's File — drives
                // the "multiple visits on this file" hint icon on the queue card. The
                // inner query is aliased so its "file_id" doesn't collide with the
                // outer query's own doctorvisits.file_id — without the alias both
                // sides resolve to the same table and it self-compares (x = x),
                // returning the row count for the whole table on every row instead
                // of a per-file count. A null file_id never matches itself here
                // (NULL = NULL is false in SQL), so unfiled visits come back as 0.
                'file_visits_count' => DoctorVisit::query()
                    ->from('doctorvisits as file_siblings')
                    ->selectRaw('count(*)')
                    ->whereColumn('file_siblings.file_id', 'doctorvisits.file_id'),
            ]);

        // Filter by general clinic shift (if your system has overarching shifts)
        if ($request->filled('clinic_shift_id')) {
            $query->where('shift_id', $request->clinic_shift_id);
        }

        if ($request->filled('doctor_shift_id')) {
            // A single doctor-shift session (e.g. the DoctorTabs / shift-details flows).
            $query->where('doctor_shift_id', $request->doctor_shift_id);
        } elseif ($request->filled('doctor_id')) {
            // All of this doctor's visits on the given day (default: today), across
            // every doctor-shift session they may have opened/closed/reopened that
            // day (Doctor Portal).
            $date = $request->input('date') ?: today()->toDateString();
            $query->whereHas('doctorShift', function ($q) use ($request) {
                $q->where('doctor_id', $request->doctor_id);
            })
                ->whereDate('created_at', $date);
        } else {
            // Neither scope given — never fall through to an unbounded system-wide
            // query; the caller must identify a shift or a doctor.
            return DoctorVisitListItemResource::collection(collect());
        }

        if ($request->filled('search') && $request->search !== '') {
            $searchTerm = $request->search;
            $query->whereHas('patient', function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('id', $searchTerm); // Allow search by patient ID
            });
        }

        // Order by status (e.g., 'with_doctor' first), then by queue number or creation time
        $query->orderByRaw("CASE status WHEN 'with_doctor' THEN 1 WHEN 'waiting' THEN 2 ELSE 3 END")
            ->orderBy('id', 'desc'); // Or by appointment_time / queue_number

        $activeVisits = $query->get();

        return DoctorVisitListItemResource::collection($activeVisits);
    }
}
