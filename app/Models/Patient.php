<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Patient extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'shift_id',
        'user_id', // User who registered/created this patient record
        'doctor_id', // This is the patient's primary/linked doctor, if any. Separate from visit doctor.
        'phone',
        'gender',
        'age_day',
        'age_month',
        'file_id', // Foreign key to the files table
        'age_year',
        'company_id',
        'subcompany_id',
        'company_relation_id',
        'paper_fees',
        'guarantor',
        'expire_date',
        'insurance_no',
        'is_lab_paid',
        'lab_paid',
        'result_is_locked',
        'sample_collected',
        'sample_collect_time',
        'result_print_date',
        'sample_print_date',
        'visit_number',
        'result_auth',
        'auth_date',
        'present_complains',
        'history_of_present_illness',
        'procedures',
        'provisional_diagnosis',
        'bp',
        'temp',
        'weight',
        'height',
        'juandice',
        'pallor',
        'clubbing',
        'cyanosis',
        'edema_feet',
        'dehydration',
        'lymphadenopathy',
        'peripheral_pulses',
        'feet_ulcer',
        'country_id',
        'gov_id',
        'prescription_notes',
        'address',
        'heart_rate',
        'spo2',
        'discount',
        'drug_history',
        'family_history',
        'rbs',
        'doctor_finish',
        'care_plan',
        'doctor_lab_request_confirm',
        'doctor_lab_urgent_confirm',
        'general_examination_notes',
        'patient_medical_history',
        'social_history',
        'allergies',
        'general',
        'skin',
        'head',
        'eyes',
        'ear',
        'nose',
        'mouth',
        'throat',
        'neck',
        'respiratory_system',
        'cardio_system',
        'git_system',
        'genitourinary_system',
        'nervous_system',
        'musculoskeletal_system',
        'neuropsychiatric_system',
        'endocrine_system',
        'peripheral_vascular_system',
        'referred',
        'discount_comment',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expire_date' => 'date',
        'is_lab_paid' => 'boolean',
        'result_is_locked' => 'boolean',
        'sample_collected' => 'boolean',
        'result_print_date' => 'datetime',
        'sample_print_date' => 'datetime',
        'result_auth' => 'boolean',
        'auth_date' => 'datetime',
        'temp' => 'decimal:2',
        'weight' => 'decimal:2',
        'height' => 'decimal:2',
        'discount' => 'decimal:2',
        'juandice' => 'boolean',
        'pallor' => 'boolean',
        'clubbing' => 'boolean',
        'cyanosis' => 'boolean',
        'edema_feet' => 'boolean',
        'dehydration' => 'boolean',
        'lymphadenopathy' => 'boolean',
        'peripheral_pulses' => 'boolean',
        'feet_ulcer' => 'boolean',
        'doctor_finish' => 'boolean',
        'doctor_lab_request_confirm' => 'boolean',
        'doctor_lab_urgent_confirm' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     * Can be used to set default values for attributes.
     */
    protected static function booted(): void
    {
        static::creating(function ($patient) {
            // Example: Set default for visit_number if not provided
            if (empty($patient->visit_number)) {
                $patient->visit_number = 1; // Or calculate next visit number
            }
            if (empty($patient->auth_date) && $patient->result_auth === false) { // Default auth_date if not set
                 $patient->auth_date = Carbon::now();
            }
            // You can set other defaults for the many NOT NULL string fields here if needed
            // e.g., $patient->history_of_present_illness = $patient->history_of_present_illness ?? '';
        });
    }

    // Relationships

    /**
     * Get the user who registered this patient.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the shift during which this patient was registered.
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the primary doctor linked to this patient (if any).
     * This is based on the `doctor_id` column in the `patients` table itself.
     */
    public function primaryDoctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    /**
     * Get the company associated with this patient.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
    
    /**
     * Get the subcompany associated with this patient.
     */
    public function subcompany()
    {
        return $this->belongsTo(Subcompany::class);
    }

    /**
     * Get the company relation associated with this patient.
     */
    public function companyRelation()
    {
        return $this->belongsTo(CompanyRelation::class);
    }

    /**
     * Get the country of the patient.
     */
    public function country()
    {
        return $this->belongsTo(Country::class); // Assuming Country model exists
    }

    /**
     * Get all doctor visits for this patient.
     */
    public function doctorVisits()
    {
        return $this->hasMany(DoctorVisit::class);
    }

    /**
     * Get the latest doctor visit for this patient.
     */
    public function latestDoctorVisit()
    {
        return $this->hasOne(DoctorVisit::class)->latestOfMany();
    }

    /**
     * Get all lab requests for this patient.
     */
    public function labRequests()
    {
        return $this->hasMany(LabRequest::class, 'pid'); // 'pid' is the FK in labrequests table
    }

    /**
     * Get all prescribed drugs for this patient.
     */
    public function drugsPrescribed()
    {
        return $this->hasMany(DrugPrescribed::class);
    }

    /**
     * Get all sick leaves for this patient.
     */
    public function sickleaves()
    {
        return $this->hasMany(Sickleave::class);
    }

    /**
     * Get all appointments for this patient (indirectly, if appointments link to doctor_visits or directly if patient_id is added to appointments).
     * If appointments directly link to patients:
     * public function appointments() { return $this->hasMany(Appointment::class); }
     */


    // Accessors & Mutators (Examples)

    /**
     * Get the patient's full age as a string.
     * Example: "30 Y / 5 M / 10 D"
     */
    public function getFullAgeAttribute(): string
    {
        $parts = [];
        if (isset($this->age_year) && $this->age_year > 0) {
            $parts[] = $this->age_year . ' Y';
        }
        if (isset($this->age_month) && $this->age_month > 0) {
            $parts[] = $this->age_month . ' M';
        }
        if (isset($this->age_day) && $this->age_day > 0) {
            $parts[] = $this->age_day . ' D';
        }
        return empty($parts) ? 'N/A' : implode(' / ', $parts);
    }

    public function file()
{
    return $this->belongsTo(File::class);
}
    /**
     * Calculate age from Date of Birth if you were to store DOB instead of age parts.
     * public function getAgeAttribute() {
     *     return Carbon::parse($this->attributes['date_of_birth'])->age;
     * }
     */
}