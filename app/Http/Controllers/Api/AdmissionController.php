<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\Bed;
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
        $query = Admission::with(['patient', 'ward', 'room', 'bed', 'doctor', 'specialistDoctor', 'user']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $searchTerm = $request->search;
            $query->whereHas('patient', function($q) use ($searchTerm) {
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
        $validatedData = $request->validate([
            'patient_id' => 'required|exists:patients,id',
            'ward_id' => 'required|exists:wards,id',
            'room_id' => 'required|exists:rooms,id',
            'bed_id' => 'required|exists:beds,id',
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
        ]);

        // Check if bed is available
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

        DB::transaction(function () use (&$admission, $validatedData, $bed) {
            // Set user_id to current authenticated user
            $validatedData['user_id'] = Auth::id();
            
            // Set admission time if not provided
            if (empty($validatedData['admission_time'])) {
                $validatedData['admission_time'] = Carbon::now()->format('H:i:s');
            }

            // Create admission
            $admission = Admission::create($validatedData);

            // Update bed status to occupied
            $bed->update(['status' => 'occupied']);

            // Auto-add file opening fee service
            $fileOpeningServiceName = 'رسوم فتح الملف';
            $fileOpeningService = Service::where('name', $fileOpeningServiceName)->first();
            
            if (!$fileOpeningService) {
                // Get first service group or create a default one
                $serviceGroup = ServiceGroup::first();
                if (!$serviceGroup) {
                    // Create a default service group if none exists
                    $serviceGroup = ServiceGroup::create([
                        'name' => 'رسوم عامة',
                    ]);
                }
                
                // Create the file opening fee service
                $fileOpeningService = Service::create([
                    'name' => $fileOpeningServiceName,
                    'service_group_id' => $serviceGroup->id,
                    'price' => 0, // Default price, can be configured later
                    'activate' => true,
                    'variable' => false,
                ]);
            }

            // Add the service to the admission
            if ($fileOpeningService && $fileOpeningService->price > 0) {
                $requestedService = AdmissionRequestedService::create([
                    'admission_id' => $admission->id,
                    'service_id' => $fileOpeningService->id,
                    'user_id' => Auth::id(),
                    'doctor_id' => $admission->doctor_id,
                    'price' => (float) $fileOpeningService->price,
                    'endurance' => 0,
                    'discount' => 0,
                    'discount_per' => 0,
                    'count' => 1,
                    'approval' => false,
                    'done' => false,
                ]);

                // Create debit transaction for the service
                AdmissionTransaction::create([
                    'admission_id' => $admission->id,
                    'type' => 'debit',
                    'amount' => (float) $fileOpeningService->price,
                    'description' => $fileOpeningServiceName,
                    'reference_type' => 'service',
                    'reference_id' => $requestedService->id,
                    'is_bank' => false,
                    'user_id' => Auth::id(),
                ]);
            }
            
            // Sync specialist_doctor_id to patient if provided
            if (isset($validatedData['specialist_doctor_id']) && $validatedData['specialist_doctor_id']) {
                $admission->patient->update(['specialist_doctor_id' => $validatedData['specialist_doctor_id']]);
            }
        });

        return new AdmissionResource($admission->load(['patient', 'ward', 'room', 'bed', 'doctor', 'specialistDoctor', 'user']));
    }

    /**
     * Display the specified admission.
     */
    public function show(Admission $admission)
    {
        return new AdmissionResource($admission->load(['patient', 'ward', 'room', 'bed', 'doctor', 'specialistDoctor', 'user']));
    }

    /**
     * Update the specified admission in storage.
     */
    public function update(Request $request, Admission $admission)
    {
        $validatedData = $request->validate([
            'admission_reason' => 'nullable|string',
            'diagnosis' => 'nullable|string',
            'doctor_id' => 'nullable|exists:doctors,id',
            'specialist_doctor_id' => 'nullable|exists:doctors,id',
            'notes' => 'nullable|string',
            'provisional_diagnosis' => 'nullable|string',
            'operations' => 'nullable|string',
        ]);

        $admission->update($validatedData);

        return new AdmissionResource($admission->load(['patient', 'ward', 'room', 'bed', 'doctor', 'specialistDoctor', 'user']));
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

            // Update bed status to available
            $admission->bed->update(['status' => 'available']);
        });

        return new AdmissionResource($admission->load(['patient', 'ward', 'room', 'bed', 'doctor', 'specialistDoctor', 'user']));
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
        $query = Admission::with(['patient', 'ward', 'room', 'bed', 'doctor'])
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
