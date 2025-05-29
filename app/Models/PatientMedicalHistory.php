<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PatientMedicalHistory extends Model
{
    use HasFactory;

    protected $table = 'patient_medical_histories';

    protected $fillable = [
        'patient_id', 'allergies', 'drug_history', 'family_history', 'social_history',
        'past_medical_history', 'past_surgical_history',
        'general_appearance_summary', 'skin_summary', 'head_neck_summary',
        'cardiovascular_summary', 'respiratory_summary', 'gastrointestinal_summary',
        'genitourinary_summary', 'neurological_summary', 'musculoskeletal_summary',
        'endocrine_summary', 'peripheral_vascular_summary',
        'present_complains_summary', 'history_of_present_illness_summary',
        'baseline_bp', 'baseline_temp', 'baseline_weight', 'baseline_height',
        'baseline_heart_rate', 'baseline_spo2', 'baseline_rbs',
        'chronic_juandice', 'chronic_pallor', 'chronic_clubbing', 'chronic_cyanosis',
        'chronic_edema_feet', 'chronic_dehydration_tendency', 'chronic_lymphadenopathy',
        'chronic_peripheral_pulses_issue', 'chronic_feet_ulcer_history',
        'overall_care_plan_summary', 'general_prescription_notes_summary',
    ];

    protected $casts = [
        'baseline_temp' => 'decimal:2',
        'baseline_weight' => 'decimal:2',
        'baseline_height' => 'decimal:2',
        'chronic_juandice' => 'boolean',
        'chronic_pallor' => 'boolean',
        // ... cast other booleans
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}