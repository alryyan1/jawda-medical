<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionVitalSign;
use App\Http\Resources\AdmissionVitalSignResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AdmissionVitalSignController extends Controller
{
    /**
     * Display a listing of vital signs for an admission.
     */
    public function index(Request $request, Admission $admission)
    {
        $query = $admission->vitalSigns()->with('user')->orderBy('reading_date', 'desc')->orderBy('reading_time', 'desc');

        // Filter by date range
        if ($request->has('start_date')) {
            $query->where('reading_date', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->where('reading_date', '<=', $request->end_date);
        }

        $vitalSigns = $query->get();

        return AdmissionVitalSignResource::collection($vitalSigns);
    }

    /**
     * Store a newly created vital sign in storage.
     */
    public function store(Request $request, Admission $admission)
    {
        $validatedData = $request->validate([
            'reading_date' => 'required|date',
            'reading_time' => 'required|date_format:H:i:s',
            'temperature' => 'nullable|numeric|min:0|max:50',
            'blood_pressure_systolic' => 'nullable|integer|min:0|max:300',
            'blood_pressure_diastolic' => 'nullable|integer|min:0|max:300',
            'oxygen_saturation' => 'nullable|numeric|min:0|max:100',
            'oxygen_flow' => 'nullable|numeric|min:0|max:100',
            'pulse_rate' => 'nullable|integer|min:0|max:300',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Ensure at least one vital sign is provided
        $hasVitalSign = !empty($validatedData['temperature']) ||
            !empty($validatedData['blood_pressure_systolic']) ||
            !empty($validatedData['blood_pressure_diastolic']) ||
            !empty($validatedData['oxygen_saturation']) ||
            !empty($validatedData['oxygen_flow']) ||
            !empty($validatedData['pulse_rate']);

        if (!$hasVitalSign) {
            throw ValidationException::withMessages([
                'vital_signs' => ['يجب إدخال علامة حيوية واحدة على الأقل.'],
            ]);
        }

        $validatedData['admission_id'] = $admission->id;
        $validatedData['user_id'] = Auth::id();

        $vitalSign = AdmissionVitalSign::create($validatedData);

        return new AdmissionVitalSignResource($vitalSign->load('user'));
    }

    /**
     * Display the specified vital sign.
     */
    public function show(AdmissionVitalSign $admissionVitalSign)
    {
        return new AdmissionVitalSignResource($admissionVitalSign->load('user'));
    }

    /**
     * Update the specified vital sign in storage.
     */
    public function update(Request $request, AdmissionVitalSign $admissionVitalSign)
    {
        $validatedData = $request->validate([
            'reading_date' => 'sometimes|required|date',
            'reading_time' => 'sometimes|required|date_format:H:i:s',
            'temperature' => 'nullable|numeric|min:0|max:50',
            'blood_pressure_systolic' => 'nullable|integer|min:0|max:300',
            'blood_pressure_diastolic' => 'nullable|integer|min:0|max:300',
            'oxygen_saturation' => 'nullable|numeric|min:0|max:100',
            'oxygen_flow' => 'nullable|numeric|min:0|max:100',
            'pulse_rate' => 'nullable|integer|min:0|max:300',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Ensure at least one vital sign is provided
        $hasVitalSign = !empty($validatedData['temperature']) ||
            !empty($validatedData['blood_pressure_systolic']) ||
            !empty($validatedData['blood_pressure_diastolic']) ||
            !empty($validatedData['oxygen_saturation']) ||
            !empty($validatedData['oxygen_flow']) ||
            !empty($validatedData['pulse_rate']) ||
            ($admissionVitalSign->temperature !== null) ||
            ($admissionVitalSign->blood_pressure_systolic !== null) ||
            ($admissionVitalSign->blood_pressure_diastolic !== null) ||
            ($admissionVitalSign->oxygen_saturation !== null) ||
            ($admissionVitalSign->oxygen_flow !== null) ||
            ($admissionVitalSign->pulse_rate !== null);

        if (!$hasVitalSign) {
            throw ValidationException::withMessages([
                'vital_signs' => ['يجب إدخال علامة حيوية واحدة على الأقل.'],
            ]);
        }

        $admissionVitalSign->update($validatedData);

        return new AdmissionVitalSignResource($admissionVitalSign->load('user'));
    }

    /**
     * Remove the specified vital sign from storage.
     */
    public function destroy(AdmissionVitalSign $admissionVitalSign)
    {
        $admissionVitalSign->delete();

        return response()->json(null, 204);
    }
}
