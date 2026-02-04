<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Bed;
use App\Models\ShortStayBed;
use App\Models\Service;
use App\Models\ServiceGroup;
use App\Models\AdmissionRequestedService;
use App\Models\AdmissionTransaction;
use Illuminate\Http\Request;
use App\Http\Resources\AdmissionResource;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class AdmissionController extends Controller
{
    /**
     * Display a listing of the admissions.
     */
    public function index(Request $request)
    {
        $query = Admission::with(['patient', 'ward', 'room', 'bed', 'shortStayBed', 'doctor', 'specialistDoctor', 'user']);

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
            ->orderBy('admission_time', 'desc')
            ->paginate($request->get('per_page', 15));

        return AdmissionResource::collection($admissions);
    }

    /**
     * Store a newly created admission in storage.
     */
    public function store(Request $request)
    {
        // First, validate basic fields
        $validatedData = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'ward_id' => 'nullable|exists:wards,id',
            'room_id' => 'nullable|exists:rooms,id',
            'bed_id' => 'nullable|exists:beds,id',
            'booking_type' => 'required|in:bed,room',
            'admission_date' => 'required|date',
            'admission_time' => 'nullable|date_format:H:i:s',
            'admission_type' => 'nullable|string|max:255',
            'admission_reason' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'doctor_id' => 'nullable|exists:doctors,id',
            'specialist_doctor_id' => 'nullable|exists:doctors,id',
            'notes' => 'nullable|string',
            'provisional_diagnosis' => 'nullable|string',
            'operations' => 'nullable|string',
            'medical_history' => 'nullable|string',
            'current_medications' => 'nullable|string',
            'referral_source' => 'nullable|string|max:255',
            'expected_discharge_date' => 'nullable|date',
            'next_of_kin_name' => 'nullable|string|max:255',
            'next_of_kin_relation' => 'nullable|string|max:255',
            'next_of_kin_phone' => 'nullable|string|max:255',
            'short_stay_bed_id' => 'nullable|exists:short_stay_beds,id',
            'short_stay_duration' => 'nullable|in:12h,24h',
        ]);

        // Check if this is a short stay admission
        $isShortStay = !empty($validatedData['short_stay_bed_id']) || 
                       (!empty($validatedData['admission_type']) && $validatedData['admission_type'] === 'اقامه قصيره');

        if ($isShortStay) {
            // For short stay, short_stay_bed_id and short_stay_duration are required
            if (empty($validatedData['short_stay_bed_id'])) {
                return response()->json([
                    'message' => 'يرجى اختيار سرير الإقامة القصيرة.',
                    'errors' => [
                        'short_stay_bed_id' => ['سرير الإقامة القصيرة مطلوب عند اختيار نوع الإقامة "إقامة قصيرة".']
                    ]
                ], 400);
            }
            if (empty($validatedData['short_stay_duration'])) {
                return response()->json([
                    'message' => 'يرجى تحديد مدة الإقامة القصيرة (12 ساعة أو 24 ساعة).',
                    'errors' => [
                        'short_stay_duration' => ['مدة الإقامة القصيرة مطلوبة (12h أو 24h).']
                    ]
                ], 400);
            }
            // ward_id, room_id, bed_id are optional for short stay
        } else {
            // For regular admission, ward_id and room_id are required
            if (empty($validatedData['ward_id'])) {
                return response()->json(['message' => 'يرجى اختيار القسم.'], 400);
            }
            if (empty($validatedData['room_id'])) {
                return response()->json(['message' => 'يرجى اختيار الغرفة.'], 400);
            }
            // Validate bed_id is required when booking_type is 'bed'
            if ($validatedData['booking_type'] === 'bed' && empty($validatedData['bed_id'])) {
                return response()->json(['message' => 'السرير مطلوب عند اختيار نوع الحجز "سرير".'], 400);
            }
        }

        // Debug: Log validated data
        \Log::info('Admission Store - Validated Data:', $validatedData);

        // Check if bed is available (only if booking_type is 'bed' and not short stay)
        if (!$isShortStay && isset($validatedData['booking_type']) && $validatedData['booking_type'] === 'bed') {
            $bed = Bed::findOrFail($validatedData['bed_id']);
            if (!$bed->isAvailable()) {
                return response()->json(['message' => 'السرير غير متاح حالياً.'], 400);
            }

            // Verify bed belongs to room and room belongs to ward
            if ($bed->room_id != $validatedData['room_id']) {
                return response()->json(['message' => 'السرير لا ينتمي للغرفة المحددة.'], 400);
            }
            if ($bed->room->ward_id != $validatedData['ward_id']) {
                return response()->json(['message' => 'الغرفة لا تنتمي للقسم المحدد.'], 400);
            }
        }

        DB::transaction(function () use (&$admission, $validatedData, $isShortStay) {
            // Set user_id to current authenticated user
            $validatedData['user_id'] = Auth::id();

            // Set admission time if not provided
            if (empty($validatedData['admission_time'])) {
                $validatedData['admission_time'] = Carbon::now()->format('H:i:s');
            }

            // Set default booking_type if not provided
            if (empty($validatedData['booking_type'])) {
                $validatedData['booking_type'] = 'bed';
            }

            // For short stay admissions, ensure location fields are null
            if ($isShortStay) {
                $validatedData['ward_id'] = null;
                $validatedData['room_id'] = null;
                $validatedData['bed_id'] = null;
            }

            // Create admission
            $admission = Admission::create($validatedData);

            // Update bed status to occupied (only if booking_type is 'bed' and not short stay)
            if (!$isShortStay && $validatedData['booking_type'] === 'bed' && isset($validatedData['bed_id'])) {
                $bed = Bed::find($validatedData['bed_id']);
                if ($bed) {
                    $bed->update(['status' => 'occupied']);
                }
            }

            // Auto-add short stay transaction if this is a short stay admission
            if ($isShortStay && isset($validatedData['short_stay_bed_id']) && isset($validatedData['short_stay_duration'])) {
                $shortStayBed = ShortStayBed::find($validatedData['short_stay_bed_id']);
                if ($shortStayBed) {
                    $price = $shortStayBed->getPriceForDuration($validatedData['short_stay_duration']);
                    if ($price > 0) {
                        AdmissionTransaction::create([
                            'admission_id' => $admission->id,
                            'type' => 'debit',
                            'amount' => $price,
                            'description' => 'رسوم إقامة قصيرة (' . ($validatedData['short_stay_duration'] === '12h' ? '12 ساعة' : '24 ساعة') . ')',
                            'reference_type' => 'short_stay',
                            'user_id' => Auth::id(),
                        ]);
                    }
                }
            }

            // Auto-add file opening fee service removed


            // Sync specialist_doctor_id to patient if provided
            if (isset($validatedData['specialist_doctor_id']) && $validatedData['specialist_doctor_id']) {
                $admission->patient->update(['specialist_doctor_id' => $validatedData['specialist_doctor_id']]);
            }
        });

        return new AdmissionResource($admission->load(['patient', 'ward', 'room', 'bed', 'shortStayBed', 'doctor', 'specialistDoctor', 'user']));
    }

    /**
     * Display the specified admission.
     */
    public function show(Admission $admission)
    {
        return new AdmissionResource($admission->load(['patient', 'ward', 'room', 'bed', 'shortStayBed', 'doctor', 'specialistDoctor', 'user']));
    }

    /**
     * Update the specified admission in storage.
     */
    public function update(Request $request, Admission $admission)
    {
        $validatedData = $request->validate([
            'ward_id' => 'nullable|exists:wards,id',
            'room_id' => 'nullable|exists:rooms,id',
            'bed_id' => 'nullable|exists:beds,id',
            'booking_type' => 'nullable|in:bed,room',
            'admission_reason' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'doctor_id' => 'nullable|exists:doctors,id',
            'specialist_doctor_id' => 'nullable|exists:doctors,id',
            'notes' => 'nullable|string',
            'provisional_diagnosis' => 'nullable|string',
            'operations' => 'nullable|string',
            'medical_history' => 'nullable|string',
            'current_medications' => 'nullable|string',
            'referral_source' => 'nullable|string|max:255',
            'expected_discharge_date' => 'nullable|date',
            'next_of_kin_name' => 'nullable|string|max:255',
            'next_of_kin_relation' => 'nullable|string|max:255',
            'next_of_kin_phone' => 'nullable|string|max:255',
        ]);

        // Validate bed_id is required when booking_type is 'bed'
        if (isset($validatedData['booking_type']) && $validatedData['booking_type'] === 'bed' && empty($validatedData['bed_id'])) {
            return response()->json(['message' => 'السرير مطلوب عند اختيار نوع الحجز "سرير".'], 400);
        }

        $admission->update($validatedData);

        return new AdmissionResource($admission->load(['patient', 'ward', 'room', 'bed', 'shortStayBed', 'doctor', 'specialistDoctor', 'user']));
    }

    /**
     * Discharge a patient.
     */
    public function discharge(Request $request, Admission $admission)
    {
        if ($admission->status !== 'admitted') {
            return response()->json(['message' => 'المريض غير مقيم حالياً.'], 400);
        }

        // Check if balance is zero (credits - debits = 0)
        $totalCredits = (float) $admission->transactions()->where('type', 'credit')->sum('amount');
        $totalDebits = (float) $admission->transactions()->where('type', 'debit')->sum('amount');
        $balance = $totalCredits - $totalDebits;

        if (abs($balance) > 0.01) { // Allow small floating point differences
            return response()->json([
                'message' => 'لا يمكن إخراج المريض. الرصيد يجب أن يكون صفراً.',
                'balance' => $balance
            ], 400);
        }

        $validatedData = $request->validate([
            'discharge_date' => 'nullable|date',
            'discharge_time' => 'nullable|date_format:H:i:s',
            'notes' => 'nullable|string',
        ]);

        DB::transaction(function () use ($admission, $validatedData) {
            // Set discharge date/time if not provided
            if (empty($validatedData['discharge_date'])) {
                $validatedData['discharge_date'] = Carbon::today();
            }
            if (empty($validatedData['discharge_time'])) {
                $validatedData['discharge_time'] = Carbon::now()->format('H:i:s');
            }

            // Update admission
            $admission->update([
                'status' => 'discharged',
                'discharge_date' => $validatedData['discharge_date'],
                'discharge_time' => $validatedData['discharge_time'],
                'notes' => $validatedData['notes'] ?? $admission->notes,
            ]);

            // Update bed status to available (only if bed exists)
            if ($admission->bed_id && $admission->bed) {
                $admission->bed->update(['status' => 'available']);
            }
        });

        return new AdmissionResource($admission->load(['patient', 'ward', 'room', 'bed', 'shortStayBed', 'doctor', 'specialistDoctor', 'user']));
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
            'ward_id' => 'required|exists:wards,id',
            'room_id' => 'required|exists:rooms,id',
            'bed_id' => 'required|exists:beds,id',
            'notes' => 'nullable|string',
        ]);

        // Check if new bed is available
        $newBed = Bed::findOrFail($validatedData['bed_id']);
        if (!$newBed->isAvailable()) {
            return response()->json(['message' => 'السرير الجديد غير متاح حالياً.'], 400);
        }

        // Verify bed belongs to room and room belongs to ward
        if ($newBed->room_id != $validatedData['room_id']) {
            return response()->json(['message' => 'السرير لا ينتمي للغرفة المحددة.'], 400);
        }
        if ($newBed->room->ward_id != $validatedData['ward_id']) {
            return response()->json(['message' => 'الغرفة لا تنتمي للقسم المحدد.'], 400);
        }

        DB::transaction(function () use ($admission, $validatedData, $newBed) {
            // Free old bed
            $admission->bed->update(['status' => 'available']);

            // Update admission
            $admission->update([
                'ward_id' => $validatedData['ward_id'],
                'room_id' => $validatedData['room_id'],
                'bed_id' => $validatedData['bed_id'],
                'status' => 'transferred',
                'notes' => $validatedData['notes'] ?? $admission->notes,
            ]);

            // Create new admission record for the transfer
            $newAdmission = Admission::create([
                'patient_id' => $admission->patient_id,
                'ward_id' => $validatedData['ward_id'],
                'room_id' => $validatedData['room_id'],
                'bed_id' => $validatedData['bed_id'],
                'admission_date' => Carbon::today(),
                'admission_time' => Carbon::now()->format('H:i:s'),
                'admission_type' => 'transfer',
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

        return new AdmissionResource($admission->fresh()->load(['patient', 'ward', 'room', 'bed', 'doctor', 'user']));
    }

    /**
     * Get active admissions.
     */
    public function getActive(Request $request)
    {
        $query = Admission::with(['patient', 'ward', 'room', 'bed', 'shortStayBed', 'doctor'])
            ->where('status', 'admitted');

        // Ward filter
        if ($request->has('ward_id') && $request->ward_id) {
            $query->where('ward_id', $request->ward_id);
        }

        $admissions = $query->orderBy('admission_date', 'desc')
            ->orderBy('admission_time', 'desc')
            ->get();

        return AdmissionResource::collection($admissions);
    }
}
