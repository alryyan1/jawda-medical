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
 * @property bool $calc_insurance
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
        'start', // This was an INT(11) in the schema, meaning? Patient capacity? Starting number?
        'image',
        'calc_insurance',
        'is_default',
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
     * Calculate the total doctor credit for all services in a visit.
     *
     * @param  Doctorvisit  $doctorvisit
     * @return float
     */
    public function doctor_credit(Doctorvisit $doctorvisit): float
    {
        // Resolve once — avoids N+1 inside the loop.
        $disableServiceCheck = (bool) optional(Setting::first())->disable_doctor_service_check;

        // Eligible service IDs from individual assignments.
        $individualServiceIds = $this->specificServices()->pluck('service_id')->toArray();

        $total = 0.0;

        foreach ($doctorvisit->requestedServices as $service) {
            // Only process services assigned to this doctor.
            if ($service->doctor_id !== $this->id) {
                continue;
            }

            $eligible = $disableServiceCheck
                || in_array($service->service_id, $individualServiceIds);

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
     * Credit calculation for company / insurance patients.
     *
     * Formula: (price × count) × company_percentage / 100
     *
     * @param  RequestedService  $service
     * @return float
     */
    private function calcCompanyCredit(RequestedService $service): float
    {
        $grossPrice = $service->price * $service->count;

        return $grossPrice * $this->company_percentage / 100;
    }

    /**
     * Credit calculation for cash (self-pay) patients.
     *
     * Priority order for rate lookup:
     *   1. Individual doctor-service pivot (percentage > fixed > default)
     *   2. Doctor's default cash_percentage
     *
     * @param  RequestedService  $service
     * @return float
     */
    private function calcCashCredit(RequestedService $service): float
    {
        // --- 1. Individual doctor-service settings ---
        $doctorService = $this->specificServices
            ->first(fn ($s) => $s->id == $service->service_id);

        if ($doctorService?->pivot) {
            return $this->applyPivotRate($service, $doctorService->pivot);
        }

        // --- 2. Default: cash_percentage on amount paid ---
        return $service->amount_paid * $this->cash_percentage / 100;
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
      
        if (($pivot->fixed ?? 0) > 0 && ($pivot->percentage ?? 0) == 0) {
            return $pivot->fixed * $service->count;
        }
          if (($pivot->percentage ?? 0) > 0) {
            return $service->amount_paid * $pivot->percentage / 100;
        }


        // Neither percentage nor fixed — fall back to doctor's default.
        return $service->amount_paid * $this->cash_percentage / 100;
    }
}
