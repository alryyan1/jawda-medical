<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use App\Models\PatientMedicalHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientMedicalHistoryController extends Controller
{
    /**
     * Get or create the medical history record for a patient.
     */
    public function show(Patient $patient): JsonResponse
    {
        $history = PatientMedicalHistory::firstOrCreate(
            ['patient_id' => $patient->id]
        );

        return response()->json(['data' => $history]);
    }

    /**
     * Update (or create) the medical history record for a patient.
     */
    public function update(Request $request, Patient $patient): JsonResponse
    {
        $validated = $request->validate([
            'allergies'                          => 'nullable|string',
            'drug_history'                       => 'nullable|string',
            'family_history'                     => 'nullable|string',
            'social_history'                     => 'nullable|string',
            'past_medical_history'               => 'nullable|string',
            'past_surgical_history'              => 'nullable|string',
            'present_complains_summary'          => 'nullable|string',
            'history_of_present_illness_summary' => 'nullable|string',
            'baseline_bp'                        => 'nullable|string|max:50',
            'baseline_temp'                      => 'nullable|numeric',
            'baseline_weight'                    => 'nullable|numeric',
            'baseline_height'                    => 'nullable|numeric',
            'baseline_heart_rate'                => 'nullable|string|max:50',
            'baseline_spo2'                      => 'nullable|string|max:50',
            'baseline_rbs'                       => 'nullable|string|max:50',
            'general_appearance_summary'         => 'nullable|string',
            'skin_summary'                       => 'nullable|string',
            'head_neck_summary'                  => 'nullable|string',
            'cardiovascular_summary'             => 'nullable|string',
            'respiratory_summary'                => 'nullable|string',
            'gastrointestinal_summary'           => 'nullable|string',
            'genitourinary_summary'              => 'nullable|string',
            'neurological_summary'               => 'nullable|string',
            'musculoskeletal_summary'            => 'nullable|string',
            'endocrine_summary'                  => 'nullable|string',
            'peripheral_vascular_summary'        => 'nullable|string',
            'chronic_juandice'                   => 'nullable|boolean',
            'chronic_pallor'                     => 'nullable|boolean',
            'chronic_clubbing'                   => 'nullable|boolean',
            'chronic_cyanosis'                   => 'nullable|boolean',
            'chronic_edema_feet'                 => 'nullable|boolean',
            'chronic_dehydration_tendency'       => 'nullable|boolean',
            'chronic_lymphadenopathy'            => 'nullable|boolean',
            'chronic_peripheral_pulses_issue'    => 'nullable|boolean',
            'chronic_feet_ulcer_history'         => 'nullable|boolean',
            'overall_care_plan_summary'          => 'nullable|string',
            'general_prescription_notes_summary' => 'nullable|string',
        ]);

        $history = PatientMedicalHistory::updateOrCreate(
            ['patient_id' => $patient->id],
            $validated
        );

        return response()->json(['data' => $history]);
    }
}
