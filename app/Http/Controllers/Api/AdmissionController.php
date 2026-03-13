<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\AdmissionRequestedService;
use App\Models\AdmissionTransaction;
use App\Services\Pdf\AdmissionsListReport;
use Illuminate\Http\Request;
use App\Http\Resources\AdmissionResource;
use App\Models\Patient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class AdmissionController extends Controller
{
    /**
     * Display a listing of the admissions.
     */
    public function index(Request $request)
    {
        $query = Admission::with(['patient', 'ward', 'bed.room', 'bed', 'doctor', 'specialistDoctor', 'user']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->whereHas('patient', function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Status filter
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Ward filter
        if ($request->has('ward_id') && $request->ward_id) {
            $query->where('ward_id', $request->ward_id);
        }

        // Room filter (via bed.room_id; admissions table has no room_id column)
        $roomId = $request->has('room_id') && $request->room_id
            ? (is_array($request->room_id) ? ($request->room_id[0] ?? null) : $request->room_id)
            : null;
        if ($roomId) {
            $query->whereHas('bed', function ($q) use ($roomId) {
                $q->where('beds.room_id', $roomId);
            });
        }

        // Patient filter
        if ($request->has('patient_id') && $request->patient_id) {
            $query->where('patient_id', $request->patient_id);
        }

        // Date range filter
        if ($request->has('date_from')) {
            $query->whereDate('admission_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('admission_date', '<=', $request->date_to);
        }

        $admissions = $query->orderBy('admission_date', 'desc')
            ->paginate($request->get('per_page', 15));

        return AdmissionResource::collection($admissions);
    }

    /**
     * Get the active admission for a given patient (or null if none).
     */
    public function getPatientActiveAdmission(Patient $patient)
    {
        $admission = $patient->admission()
            ->with(['ward', 'bed.room', 'bed', 'doctor', 'specialistDoctor', 'user'])
            ->orderByDesc('admission_date')
            ->first();

        if (! $admission) {
            return response()->json(['data' => null]);
        }

        return new AdmissionResource($admission);
    }

    /**
     * Export admissions list as PDF (same filters as index, same columns as list table).
     */
    public function exportListPdf(Request $request)
    {
        $query = Admission::with(['patient', 'ward', 'bed.room', 'bed', 'doctor', 'specialistDoctor', 'user']);

        if ($request->has('search') && $request->search !== '') {
            $searchTerm = $request->search;
            $query->whereHas('patient', function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
            });
        }

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('ward_id') && $request->ward_id) {
            $query->where('ward_id', $request->ward_id);
        }

        $roomIdPdf = $request->has('room_id') && $request->room_id
            ? (is_array($request->room_id) ? ($request->room_id[0] ?? null) : $request->room_id)
            : null;
        if ($roomIdPdf) {
            $query->whereHas('bed', function ($q) use ($roomIdPdf) {
                $q->where('beds.room_id', $roomIdPdf);
            });
        }

        if ($request->has('patient_id') && $request->patient_id) {
            $query->where('patient_id', $request->patient_id);
        }

        if ($request->has('date_from')) {
            $query->whereDate('admission_date', '>=', $request->date_from);
        }
        if ($request->has('date_to')) {
            $query->whereDate('admission_date', '<=', $request->date_to);
        }

        $admissions = $query->orderBy('admission_date', 'desc')
            ->limit(1000)
            ->get();

        $admissionIds = $admissions->pluck('id')->toArray();
        $debits = [];
        $credits = [];
        if (! empty($admissionIds)) {
            $debits = AdmissionTransaction::whereIn('admission_id', $admissionIds)
                ->where('type', 'debit')
                ->selectRaw('admission_id, COALESCE(SUM(amount), 0) as total')
                ->groupBy('admission_id')
                ->pluck('total', 'admission_id')
                ->toArray();
            $credits = AdmissionTransaction::whereIn('admission_id', $admissionIds)
                ->where('type', 'credit')
                ->selectRaw('admission_id, COALESCE(SUM(amount), 0) as total')
                ->groupBy('admission_id')
                ->pluck('total', 'admission_id')
                ->toArray();
        }

        $balances = [];
        foreach ($admissions as $a) {
            $totalDebits = (float) ($debits[$a->id] ?? 0);
            $totalCredits = (float) ($credits[$a->id] ?? 0);
            $balances[$a->id] = [
                'total_debits' => $totalDebits,
                'total_credits' => $totalCredits,
                'balance' => $totalDebits - $totalCredits,
            ];
        }

        $report = new AdmissionsListReport($admissions, $balances);
        $pdfContent = $report->generate();
        $filename = 'admissions-list-' . now()->format('Y-m-d-His') . '.pdf';

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="' . $filename . '"');
    }

    /**
     * Store a newly created admission in storage.
     */
    public function store(Request $request)
    {
        $admission = null;
        // First, validate basic fields
        $validatedData = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'bed_id' => 'nullable|exists:beds,id',
            'admission_date' => 'nullable|date',
            'admission_days' => 'nullable|integer|min:1',
            'admission_purpose' => 'nullable|string|max:50|in:surgery,follow_up,intermediate_care,intensive_care',
            'admission_reason' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'doctor_id' => 'nullable|exists:doctors,id',
            'specialist_doctor_id' => 'nullable|exists:doctors,id',
            'notes' => 'nullable|string',
            'provisional_diagnosis' => 'nullable|string',

            'medical_history' => 'nullable|string',
            'current_medications' => 'nullable|string',
            'referral_source' => 'nullable|string|max:255',
            'expected_discharge_date' => 'nullable|date',
            'next_of_kin_name' => 'nullable|string|max:255',
            'next_of_kin_relation' => 'nullable|string|max:255',
            'next_of_kin_phone' => 'nullable|string|max:255',
        ]);

        $bed = isset($validatedData['bed_id']) && $validatedData['bed_id']
            ? Bed::with('room')->find($validatedData['bed_id'])
            : null;

        if ($bed) {
            $isBedOccupied = Admission::where('bed_id', $validatedData['bed_id'])
                ->where('status', 'admitted')
                ->exists();

            if ($isBedOccupied) {
                return response()->json(['message' => 'هذا السرير مشغول حالياً'], 422);
            }
        }

        DB::transaction(function () use (&$admission, $validatedData, $bed) {
            // Set user_id to current authenticated user
            $validatedData['user_id'] = Auth::id();

            // Set admission date to now if not provided
            if (empty($validatedData['admission_date'])) {
                $validatedData['admission_date'] = Carbon::now();
            }

            // Set ward_id from bed's room when bed is selected (room_id is not stored on admission)
            if ($bed) {
                $validatedData['ward_id'] = $bed->room->ward_id;
            } else {
                $validatedData['ward_id'] = null;
                $validatedData['bed_id'] = null;
            }

            // Create admission
            $admission = Admission::create($validatedData);

            // Update bed status to occupied when a bed was selected
            if ($bed) {
                $bed->update(['status' => 'occupied']);
            }


            // Auto-add file opening fee service removed


            // Sync specialist_doctor_id to patient if provided
            if (isset($validatedData['specialist_doctor_id']) && $validatedData['specialist_doctor_id']) {
                $admission->patient->update(['specialist_doctor_id' => $validatedData['specialist_doctor_id']]);
            }
        });

        return new AdmissionResource($admission->load(['patient', 'ward', 'bed.room', 'bed', 'doctor', 'specialistDoctor', 'user']));
    }

    /**
     * Display the specified admission.
     */
    public function show(Admission $admission)
    {
        return new AdmissionResource($admission->load(['patient', 'ward', 'bed.room', 'bed', 'doctor', 'specialistDoctor', 'user']));
    }

    /**
     * Update the specified admission in storage.
     */
    public function update(Request $request, Admission $admission)
    {
        $validatedData = $request->validate([
            'bed_id' => 'nullable|exists:beds,id',
            'admission_days' => 'nullable|integer|min:1',
            'admission_purpose' => 'nullable|string|max:50|in:surgery,follow_up,intermediate_care,intensive_care',
            'admission_reason' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'doctor_id' => 'nullable|exists:doctors,id',
            'specialist_doctor_id' => 'nullable|exists:doctors,id',
            'notes' => 'nullable|string',
            'provisional_diagnosis' => 'nullable|string',

            'medical_history' => 'nullable|string',
            'current_medications' => 'nullable|string',
            'referral_source' => 'nullable|string|max:255',
            'expected_discharge_date' => 'nullable|date',
            'next_of_kin_name' => 'nullable|string|max:255',
            'next_of_kin_relation' => 'nullable|string|max:255',
            'next_of_kin_phone' => 'nullable|string|max:255',
        ]);

        $oldBedId = $admission->bed_id;
        if (isset($validatedData['bed_id'])) {
            $bed = Bed::with('room')->findOrFail($validatedData['bed_id']);
            if (Admission::where('bed_id', $bed->id)->where('status', 'admitted')->where('id', '!=', $admission->id)->exists()) {
                return response()->json(['message' => 'هذا السرير مشغول حالياً'], 422);
            }
            $validatedData['ward_id'] = $bed->room->ward_id;
        }

        DB::transaction(function () use ($admission, $validatedData, $oldBedId) {
            $admission->update($validatedData);
            $newBedId = $admission->bed_id;
            // Free old bed when moving to another bed
            if ($oldBedId && $oldBedId !== $newBedId) {
                Bed::where('id', $oldBedId)->update(['status' => 'available']);
            }
            // Mark new bed as occupied when assigning
            if ($newBedId) {
                Bed::where('id', $newBedId)->update(['status' => 'occupied']);
            }
        });

        return new AdmissionResource($admission->load(['patient', 'ward', 'bed.room', 'bed', 'doctor', 'specialistDoctor', 'user']));
    }

    /**
     * Discharge a patient.
     */
    public function discharge(Request $request, Admission $admission)
    {
        if ($admission->status !== 'admitted') {
            return response()->json(['message' => 'المريض غير مقيم حالياً.'], 400);
        }


        $validatedData = $request->validate([
            'discharge_date' => 'nullable|date',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($admission, $validatedData) {
            if (empty($validatedData['discharge_date'])) {
                $validatedData['discharge_date'] = Carbon::now();
            }

            // Update admission
            $admission->update([
                'status' => 'discharged',
                'discharge_date' => $validatedData['discharge_date'],
                'notes' => $validatedData['notes'] ?? $admission->notes,
            ]);

            // Update bed status to available (only if bed exists)
            if ($admission->bed_id && $admission->bed) {
                $admission->bed->update(['status' => 'available']);
            }
            $admission->update(['bed_id' => null,'ward_id'=>null]);
        });

        return new AdmissionResource($admission->load(['patient', 'ward', 'bed.room', 'bed', 'doctor', 'specialistDoctor', 'user']));
    }

    /**
     * Vacate the bed only (clear bed_id) without discharging the patient.
     * Patient stays admitted in "قيد الإجراء" status.
     */
    public function vacateBed(Admission $admission)
    {
        if ($admission->status !== 'admitted') {
            return response()->json(['message' => 'المريض غير مقيم حالياً.'], 400);
        }

        if (!$admission->bed_id) {
            return response()->json(['message' => 'المريض غير مرتبط بسرير.'], 400);
        }

        DB::transaction(function () use ($admission) {
            $oldBedId = $admission->bed_id;
            if ($oldBedId && $admission->bed) {
                $admission->bed->update(['status' => 'available']);
            }
            $admission->update(['bed_id' => null, 'ward_id' => null]);
        });

        return new AdmissionResource($admission->load(['patient', 'ward', 'bed.room', 'bed', 'doctor', 'specialistDoctor', 'user']));
    }

    /**
     * Transfer a patient to a different bed/room/ward.
     */
    public function transfer(Request $request, Admission $admission)
    {
        if ($admission->status !== 'admitted') {
            return response()->json(['message' => 'المريض غير مقيم حالياً.'], 400);
        }

        $validatedData = $request->validate([
            'bed_id' => 'required|exists:beds,id',
            'notes' => 'nullable|string',
        ]);

        $newBed = Bed::with('room')->findOrFail($validatedData['bed_id']);
        if (!$newBed->isAvailable()) {
            return response()->json(['message' => 'السرير الجديد غير متاح حالياً.'], 400);
        }

        $wardId = $newBed->room->ward_id;

        DB::transaction(function () use ($admission, $validatedData, $newBed, $wardId) {
            // Free old bed
            $admission->bed->update(['status' => 'available']);

            // Update admission (ward_id from bed's room; no room_id on admissions)
            $admission->update([
                'ward_id' => $wardId,
                'bed_id' => $validatedData['bed_id'],
                'status' => 'transferred',
                'notes' => $validatedData['notes'] ?? $admission->notes,
            ]);

            // Create new admission record for the transfer
            $newAdmission = Admission::create([
                'patient_id' => $admission->patient_id,
                'ward_id' => $wardId,
                'bed_id' => $validatedData['bed_id'],
                'admission_date' => Carbon::now(),
                'admission_reason' => 'نقل من ' . $admission->ward->name,
                'diagnosis' => $admission->diagnosis,
                'status' => 'admitted',
                'doctor_id' => $admission->doctor_id,
                'user_id' => Auth::id(),
                'notes' => $validatedData['notes'] ?? null,
            ]);

            // Update new bed status to occupied
            $newBed->update(['status' => 'occupied']);
        });

        return new AdmissionResource($admission->fresh()->load(['patient', 'ward', 'bed.room', 'bed', 'doctor', 'user']));
    }

    /**
     * Get active admissions.
     */
    public function getActive(Request $request)
    {
        $query = Admission::with(['patient', 'ward', 'bed.room', 'bed', 'doctor'])
            ->where('status', 'admitted');

        // Ward filter
        if ($request->has('ward_id') && $request->ward_id) {
            $query->where('ward_id', $request->ward_id);
        }

        $admissions = $query->orderBy('admission_date', 'desc')
            ->get();

        return AdmissionResource::collection($admissions);
    }
}
