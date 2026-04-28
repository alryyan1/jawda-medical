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
     * Calculate the total doctor credit for all services in a visit.
     *
     * @param  Doctorvisit  $doctorvisit
     * @return float
     */
    public function doctor_credit(Doctorvisit $doctorvisit): float
    {
        // Resolve once — avoids N+1 inside the loop.
        $disableServiceCheck = (bool) optional(Setting::first())->disable_doctor_service_check;

        // Eligible service IDs from individual assignments and category.
        $individualServiceIds = $this->specificServices()->pluck('service_id')->toArray();
        $categoryServiceIds   = $this->resolveCategoryServiceIds();

        $total = 0.0;

        // Eager load returnedRefunds to avoid N+1 in the loop
        $doctorvisit->loadMissing('requestedServices.returnedRefunds');

        foreach ($doctorvisit->requestedServices as $service) {
            // Only process services assigned to this doctor.
            if ($service->doctor_id !== $this->id) {
                continue;
            }

            // Skip returned (refunded) services.
            if ($service->returnedRefunds->isNotEmpty()) {
                continue;
            }

            $eligible = $disableServiceCheck
                || in_array($service->service_id, $individualServiceIds)
                || in_array($service->service_id, $categoryServiceIds);

            if (! $eligible) {
                continue;
            }

            $total += $doctorvisit->patient->company_id
                ? $this->calcCompanyCredit($service)
                : $this->calcCashCredit($service);
        }

        return $total;
    }

    /**
     * Return the service IDs covered by the doctor's category (empty if no category).
     *
     * @return array<int>
     */
    private function resolveCategoryServiceIds(): array
    {
        if (! $this->category_id) {
            return [];
        }

        if (! $this->relationLoaded('category')) {
            $this->load('category.services');
        }

        return $this->category?->services->pluck('id')->toArray() ?? [];
    }

    /**
     * Credit calculation for company / insurance patients.
     *
     * Formula: (price × count − total_costs) × company_percentage / 100
     *
     * @param  RequestedService  $service
     * @return float
     */
    private function calcCompanyCredit(RequestedService $service): float
    {
        $grossPrice = $service->price * $service->count;
        $totalCost  = $service->getTotalCostsForDoctor($this);

        return ($grossPrice - $totalCost) * $this->company_percentage / 100;
    }

    /**
     * Credit calculation for cash (self-pay) patients.
     *
     * Priority order for rate lookup:
     *   1. Category-service pivot  (percentage > fixed > default)
     *   2. Individual doctor-service pivot (percentage > fixed > default)
     *   3. Doctor's default cash_percentage
     *
     * @param  RequestedService  $service
     * @return float
     */
    private function calcCashCredit(RequestedService $service): float
    {
        // --- 1. Category-service settings ---
        if ($this->category_id) {
            $categoryService = $this->category->services
                ->first(fn ($s) => $s->id === $service->service_id);

            if ($categoryService?->pivot) {
                return $this->applyPivotRate($service, $categoryService->pivot);
            }
        }

        // --- 2. Individual doctor-service settings ---
        $doctorService = $this->specificServices
            ->first(fn ($s) => $s->pivot->service_id === $service->service_id);

        if ($doctorService?->pivot) {
            return $this->applyPivotRate($service, $doctorService->pivot);
        }

        // --- 3. Default: cash_percentage on amount paid minus costs ---
        $totalCost = $service->getTotalCostsForDoctor($this);

        return ($service->amount_paid - $totalCost) * $this->cash_percentage / 100;
    }

    /**
     * Apply a pivot rate (percentage or fixed) to a service, falling back to the
     * doctor's default cash_percentage when neither is set.
     *
     * @param  RequestedService  $service
     * @param  object            $pivot   Eloquent pivot with percentage / fixed fields
     * @return float
     */
    private function applyPivotRate(RequestedService $service, object $pivot): float
    {
        if (($pivot->percentage ?? 0) > 0) {
            return $service->amount_paid * $pivot->percentage / 100;
        }

        if (($pivot->fixed ?? 0) > 0 && ($pivot->percentage ?? 0) == 0) {
            return $pivot->fixed * $service->count;
        }

        // Neither percentage nor fixed — fall back to doctor's default.
        $totalCost = $service->getTotalCostsForDoctor($this);

        return ($service->amount_paid - $totalCost) * $this->cash_percentage / 100;
    }
}
