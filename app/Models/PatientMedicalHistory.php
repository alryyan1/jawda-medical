<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $patient_id
 * @property string|null $allergies
 * @property string|null $drug_history Chronic medications, past significant drug use
 * @property string|null $family_history
 * @property string|null $social_history
 * @property string|null $past_medical_history
 * @property string|null $past_surgical_history
 * @property string|null $general_appearance_summary
 * @property string|null $skin_summary
 * @property string|null $head_neck_summary
 * @property string|null $cardiovascular_summary
 * @property string|null $respiratory_summary
 * @property string|null $gastrointestinal_summary
 * @property string|null $genitourinary_summary
 * @property string|null $neurological_summary
 * @property string|null $musculoskeletal_summary
 * @property string|null $endocrine_summary
 * @property string|null $peripheral_vascular_summary
 * @property string|null $present_complains_summary
 * @property string|null $history_of_present_illness_summary
 * @property string|null $baseline_bp
 * @property string|null $baseline_temp
 * @property string|null $baseline_weight
 * @property string|null $baseline_height
 * @property string|null $baseline_heart_rate
 * @property string|null $baseline_spo2
 * @property string|null $baseline_rbs
 * @property bool|null $chronic_juandice
 * @property bool|null $chronic_pallor
 * @property int|null $chronic_clubbing
 * @property int|null $chronic_cyanosis
 * @property int|null $chronic_edema_feet
 * @property int|null $chronic_dehydration_tendency
 * @property int|null $chronic_lymphadenopathy
 * @property int|null $chronic_peripheral_pulses_issue
 * @property int|null $chronic_feet_ulcer_history
 * @property string|null $overall_care_plan_summary
 * @property string|null $general_prescription_notes_summary
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Patient $patient
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory query()
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereAllergies($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereBaselineBp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereBaselineHeartRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereBaselineHeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereBaselineRbs($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereBaselineSpo2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereBaselineTemp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereBaselineWeight($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereCardiovascularSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereChronicClubbing($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereChronicCyanosis($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereChronicDehydrationTendency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereChronicEdemaFeet($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereChronicFeetUlcerHistory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereChronicJuandice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereChronicLymphadenopathy($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereChronicPallor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereChronicPeripheralPulsesIssue($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereDrugHistory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereEndocrineSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereFamilyHistory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereGastrointestinalSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereGeneralAppearanceSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereGeneralPrescriptionNotesSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereGenitourinarySummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereHeadNeckSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereHistoryOfPresentIllnessSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereMusculoskeletalSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereNeurologicalSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereOverallCarePlanSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory wherePastMedicalHistory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory wherePastSurgicalHistory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory wherePeripheralVascularSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory wherePresentComplainsSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereRespiratorySummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereSkinSummary($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereSocialHistory($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PatientMedicalHistory whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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