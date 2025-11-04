<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

/**
 * 
 *
 * @property int $id
 * @property int|null $file_id
 * @property string $name
 * @property int $shift_id
 * @property int $user_id
 * @property int|null $doctor_id
 * @property string $phone
 * @property string $gender
 * @property int|null $age_day
 * @property int|null $age_month
 * @property int|null $age_year
 * @property int|null $company_id
 * @property int|null $subcompany_id
 * @property int|null $company_relation_id
 * @property int|null $paper_fees
 * @property string|null $guarantor
 * @property \Illuminate\Support\Carbon|null $expire_date
 * @property string|null $insurance_no
 * @property bool $is_lab_paid
 * @property int $lab_paid
 * @property bool $result_is_locked
 * @property bool $sample_collected
 * @property string|null $sample_collect_time
 * @property \Illuminate\Support\Carbon|null $result_print_date
 * @property \Illuminate\Support\Carbon|null $sample_print_date
 * @property int $visit_number
 * @property bool $result_auth
 * @property \Illuminate\Support\Carbon $auth_date
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $country_id
 * @property string|null $gov_id
 * @property string|null $address
 * @property string $discount
 * @property bool $doctor_finish
 * @property bool $doctor_lab_request_confirm
 * @property bool $doctor_lab_urgent_confirm
 * @property string|null $referred
 * @property string|null $discount_comment
 * @property string|null $lab_to_lab_object_id
 * @property-read \App\Models\Company|null $company
 * @property-read \App\Models\CompanyRelation|null $companyRelation
 * @property-read \App\Models\Country|null $country
 * @property-read \App\Models\Doctor|null $doctor
 * @property-read \App\Models\DoctorVisit|null $doctorVisit
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DrugPrescribed> $drugsPrescribed
 * @property-read int|null $drugs_prescribed_count
 * @property-read \App\Models\File|null $file
 * @property-read string $full_age
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LabRequest> $labRequests
 * @property-read int|null $lab_requests_count
 * @property-read \App\Models\Doctor|null $primaryDoctor
 * @property-read \App\Models\Shift $shift
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Sickleave> $sickleaves
 * @property-read int|null $sickleaves_count
 * @property-read \App\Models\Subcompany|null $subcompany
 * @property-read \App\Models\User $user
 * @method static \Database\Factories\PatientFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Patient newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Patient newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Patient query()
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereAgeDay($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereAgeMonth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereAgeYear($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereAuthDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereCompanyRelationId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereCountryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereDiscountComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereDoctorFinish($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereDoctorLabRequestConfirm($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereDoctorLabUrgentConfirm($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereExpireDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereFileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereGender($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereGovId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereGuarantor($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereInsuranceNo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereIsLabPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereLabPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient wherePaperFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereReferred($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereResultAuth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereResultIsLocked($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereResultPrintDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereSampleCollectTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereSampleCollected($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereSamplePrintDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereSubcompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Patient whereVisitNumber($value)
 * @mixin \Eloquent
 */
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
        'user_id',
        'doctor_id', // Primary doctor link
        'phone',
        'gender',
        'age_day',
        'age_month',
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
        'result_url',
        'sample_print_date',
        'visit_number',
        'result_auth',
        'result_auth_user',
        'auth_date',
        'country_id',
        'gov_id',
        'address',
        'discount',
        'referred',
        'discount_comment',
        // Removed: 'present_complains', 'history_of_present_illness', etc.
        // Kept visit/process specific flags IF they are still relevant at patient level, else move them to DoctorVisit
        'doctor_finish', // Likely visit specific
        'doctor_lab_request_confirm', // Likely visit/request specific
        'doctor_lab_urgent_confirm', // Likely visit/request specific
        'discount_comment', // If general patient discount comment
        'lab_to_lab_object_id',
        'lab_to_lab_id',
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
        'lab_to_lab_id' => 'string',
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
     * Get the user who authorized the results.
     */
    public function resultAuthUser()
    {
        return $this->belongsTo(User::class, 'result_auth_user');
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
    public function total_lab_value_will_pay(){
        $total =0;
        foreach ($this->labrequests as $requested) {
            if ($this->company){
                $total+= $requested->endurance;

            }else{
                $total+= $requested->price ;

            }
        }
        return $total;
    }

    /**
     * Get the subcompany associated with this patient.
     */
    public function subcompany()
    {
        return $this->belongsTo(Subcompany::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }
    public function doctorVisit()
    {
        return $this->hasOne(DoctorVisit::class);
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
    public function paid_lab($user = null)
    {
        $total = 0;
        /** @var LabRequest $labrequest */
        foreach ($this->labrequests as $labrequest) {

            if ($user) {
                if ($labrequest->user_deposited != $user) continue;
            }
            if (!$labrequest->is_paid) continue;

            $total += $labrequest->amount_paid;
        }
        return $total;
    }
    public function total_lab_value_unpaid(){


        return $this->labrequests()->sum('labrequests.price');
  
      }
    public function lab_bank($user = null){

        $total = 0;
        foreach ($this->labrequests as $labrequest){
            if ($user){
                if ($labrequest->user_deposited != $user) continue;

            }
            if ($labrequest->is_paid){
                if ($labrequest->is_bankak == 1){

                    $total+=$labrequest->amount_paid;
                }

            }

        }
        return $total;

    }
    
    public function file()
    {
        return $this->belongsTo(File::class);
    }
    public function total_price($user = null){
        $total = 0;
        /** @var LabRequest $labrequest */
        foreach ($this->labrequests as $labrequest){

            if ($user){
                if ($labrequest->user_deposited != $user) continue;

            }
            if(!$labrequest->is_paid) continue;

                $total+=$labrequest->price;
        }
        return $total;
    }
    public function tests_concatinated(){
        return join(',',$this->labRequests->pluck('name')->all());
     }
     public function discountAmount($user=null){
        $total = 0;
        foreach ($this->labrequests as $labrequest){
            if ($user){
                if ($labrequest->user_deposited != $user) continue;
            }
            $amount_discounted = $labrequest->price * $labrequest->discount_per / 100;
            $total += $amount_discounted;

        }
        return $total;

    }
    /**
     * Calculate age from Date of Birth if you were to store DOB instead of age parts.
     * public function getAgeAttribute() {
     *     return Carbon::parse($this->attributes['date_of_birth'])->age;
     * }
     */
}
