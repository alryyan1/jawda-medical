<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $cash_percentage
 * @property string $company_percentage
 * @property string $static_wage
 * @property string $lab_percentage
 * @property int $specialist_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $start
 * @property string|null $image
 * @property int|null $finance_account_id
 * @property int $finanace_account_id_insurance
 * @property bool $calc_insurance
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DoctorServiceCost> $doctorServiceCosts
 * @property-read int|null $doctor_service_costs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DoctorServiceCost> $doctorSubServiceCosts
 * @property-read int|null $doctor_sub_service_costs_count
 * @property-read \App\Models\FinanceAccount|null $financeAccount
 * @property-read \App\Models\FinanceAccount|null $insuranceFinanceAccount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DoctorSchedule> $schedules
 * @property-read int|null $schedules_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DoctorService> $services
 * @property-read int|null $services_count
 * @property-read \App\Models\Specialist $specialist
 * @property-read \App\Models\DoctorService $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Service> $specificServices
 * @property-read int|null $specific_services_count
 * @property-read \App\Models\User|null $user
 * @method static \Database\Factories\DoctorFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor query()
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereCalcInsurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereCashPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereCompanyPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereFinanaceAccountIdInsurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereFinanceAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereImage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereLabPercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereSpecialistId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereStaticWage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Doctor whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Doctor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'firebase_id',
        'phone',
        'cash_percentage',
        'company_percentage',
        'static_wage',
        'lab_percentage',
        'specialist_id',
        'sub_specialist_id',
        'start', // This was an INT(11) in the schema, meaning? Patient capacity? Starting number?
        'image',
        'finance_account_id',
        'finance_account_id_insurance', // Corrected name from migration
        'calc_insurance',
        'is_default',
        'category_id',
    ];

    protected $casts = [
        'cash_percentage' => 'decimal:2',
        'company_percentage' => 'decimal:2',
        'static_wage' => 'decimal:2',
        'lab_percentage' => 'decimal:2',
        'calc_insurance' => 'boolean',
        'is_default' => 'boolean',
        // 'start' => 'integer', // If it's just a number
    ];

    /**
     * Get the specialist that owns the doctor.
     */
    public function specialist()
    {
        return $this->belongsTo(Specialist::class);
    }

    /**
     * Get the sub specialist that belongs to the doctor.
     */
    public function subSpecialist()
    {
        return $this->belongsTo(SubSpecialist::class, 'sub_specialist_id');
    }

    /**
     * Get the finance account for the doctor.
     */
    public function financeAccount()
    {
        // Assuming 'finance_account_id' is the FK column name
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id');
    }

    /**
     * Get the insurance finance account for the doctor.
     */
    public function insuranceFinanceAccount()
    {
        // Assuming 'finance_account_id_insurance' is the FK column name
        return $this->belongsTo(FinanceAccount::class, 'finance_account_id_insurance');
    }

    /**
     * Get the users associated with this doctor (if a doctor can be a user).
     * Or, if a user *has one* doctor profile.
     */
    public function user()
    {
        // If a User has a doctor_id, then a Doctor hasOne User (acting as that doctor)
        return $this->hasOne(User::class);
        // Or if a Doctor can have multiple user accounts (less common for this field name)
        // return $this->hasMany(User::class);
    }
    public function doctorServiceCosts()
    {
        return $this->hasMany(DoctorServiceCost::class);
    }
    public function doctorSubServiceCosts()
    {
        return $this->hasMany(DoctorServiceCost::class);
    }
    public function services()
    {
        return $this->hasMany(DoctorService::class);
    }
    /**
     * The services offered by the doctor with specific financial terms.
     */
    public function specificServices()
    {
        return $this->belongsToMany(Service::class, 'doctor_services')
            ->using(DoctorService::class) // Use our custom pivot model
            ->withPivot(['id', 'percentage', 'fixed']) // id is the DoctorService record id
            ->withTimestamps(); // If your doctor_services table has timestamps
    }
    /**
     * Get the doctor's schedules.
     */
    public function schedules()
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    /**
     * Get the category that the doctor belongs to.
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    /**
     * Calculate doctor's credit for a specific visit.
     * This is a placeholder for your specific business logic.
     * @param DoctorVisit $visit
     * @param string $paymentType 'cash' or 'company'
     * @return float
     */
    public function doctor_credit(Doctorvisit $doctorvisit)
    {
        //filter only paid_services
        //        $doctorvisit =  $doctorvisit->load(['services'=>function ($query) {
        //            return  $query->where('is_paid',1);
        //        }]);
        $array_1 = $this->specificServices()->pluck('service_id')->toArray();
        $total = 0;
        
        // Load category with services if doctor has a category
        $category_service_ids = [];
        if ($this->category_id && !$this->relationLoaded('category')) {
            $this->load('category.services');
        }
        if ($this->category_id && $this->relationLoaded('category') && $this->category) {
            $category_service_ids = $this->category->services->pluck('id')->toArray();
        }

        // if ($only_company) {
        //     if ($doctorvisit->patient->company == null) return 0;
        // }
        // if ($only_cash) {
        //     if ($doctorvisit->patient->company) return 0;
        // }
        foreach ($doctorvisit->requestedServices as $service) {

            if ($service->doctor_id != $this->id)
                continue;
            /**@var Setting $settings  */
            $settings = Setting::first();
            $disable_doctor_service_check = $settings->disable_doctor_service_check;

            // Check if service is in individual doctor services OR in category services OR check is disabled
            $isInIndividualServices = in_array($service->service_id, $array_1);
            $isInCategoryServices = !empty($category_service_ids) && in_array($service->service_id, $category_service_ids);
            
            if ($isInIndividualServices || $isInCategoryServices || $disable_doctor_service_check) {


                if ($doctorvisit->patient->company_id != null) {
                    //                    dd($service);                
                    $totalCost = $service->getTotalCostsForDoctor($this);
                    $total_price = ($service->price * $service->count);
                    $total_price -= $totalCost;
                    $doctor_credit = $total_price * $this->company_percentage / 100;
                    $total += $doctor_credit;
                } else {
                    $doctor_credit = 0;
                    
                    // Check if doctor has a category and if service exists in category
                    $category_service = null;
                    if ($this->category_id) {
                        $category_service = $this->category->services->firstWhere(function ($item) use ($service) {
                            return $item->id == $service->service_id;
                        });
                    }
                    
                    // Priority: Category service settings > Individual doctor-service settings > Default percentage
                    if ($category_service && $category_service->pivot) {
                        // Use category service settings (priority)
                        if ($category_service->pivot->percentage > 0) {
                            $doctor_credit = $service->amount_paid * $category_service->pivot->percentage / 100;
                        } elseif ($category_service->pivot->fixed > 0 && $category_service->pivot->percentage == 0) {
                            $doctor_credit = $category_service->pivot->fixed;
                        } else {
                            // Fallback to default calculation if category service has no percentage/fixed
                            $totalCost = $service->getTotalCostsForDoctor($this);
                            $paid = $service->amount_paid;
                            $paid -= $totalCost;
                            $doctor_credit = $paid * $this->cash_percentage / 100;
                        }
                    } else {
                        // Fallback to individual doctor-service settings
                        $doctor_service = $this->specificServices->firstWhere(function ($item) use ($service) {
                            return $item->pivot->service_id == $service->service_id;
                        });

                        if ($doctor_service?->pivot->percentage > 0) {
                            $doctor_credit = $service->amount_paid * $doctor_service->pivot->percentage / 100;
                        } elseif ($doctor_service?->pivot->fixed > 0 && $doctor_service->pivot->percentage == 0) {
                            $doctor_credit = $doctor_service->pivot->fixed;
                        } else {
                            // Default calculation
                            //احتساب مصروفات الخدمه
                            $totalCost = $service->getTotalCostsForDoctor($this);
                            $paid = $service->amount_paid;
                            $paid -= $totalCost;
                            $doctor_credit = $paid * $this->cash_percentage / 100;
                        }
                    }

                    $total += $doctor_credit;
                }
            }
        }
        return $total;
    }
}
