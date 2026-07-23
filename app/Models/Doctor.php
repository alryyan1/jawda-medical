<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
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
 *
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
 *
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

    public function patients(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function doctorVisits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Doctorvisit::class);
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
     */
    public function doctor_credit(Doctorvisit $doctorvisit, ?DoctorShift $doctorShift = null): float
    {
        // Resolve once per request — avoids re-querying settings on every call
        // (doctor_credit() is invoked once per requested service, across every
        // visit in a doctor shift, so this can run hundreds of times per report).
        $disableServiceCheck = (bool) optional(self::cachedSetting())->disable_doctor_service_check;

        // Eligible service IDs from individual assignments. Uses the cached
        // relation collection (property access) instead of specificServices(),
        // which would re-run the pivot query on every call.
        $individualServiceIds = $this->specificServices->pluck('id')->toArray();

        // Prefer the shift's snapshotted percentages so closed-shift reports stay
        // accurate even if the doctor's live percentages change afterwards. Falls
        // back to the doctor's current percentages when the shift hasn't been
        // snapshotted yet (snap_* columns are nullable until the shift closes).
        // Callers that already hold the shift should pass it in to avoid an
        // extra query per visit; otherwise it's resolved via the visit's relation.
        $doctorShift ??= $doctorvisit->doctorShift;
        $cashPercentage = (float) ($doctorShift?->snap_doctor_cash_percentage ?? $this->cash_percentage);
        $companyPercentage = (float) ($doctorShift?->snap_doctor_insurance_percentage ?? $this->company_percentage);

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
                ? $this->calcCompanyCredit($service, $companyPercentage)
                : $this->calcCashCredit($service, $cashPercentage);
        }

        return $total;
    }

    /**
     * Credit calculation for company / insurance patients.
     *
     * Formula: ((price × count) − total_cost) × company_percentage / 100
     * The recorded requested_service_costs total (money owed to a third party
     * for the service) is netted out before the doctor's percentage applies.
     */
    private function calcCompanyCredit(RequestedService $service, float $companyPercentage): float
    {
        $grossPrice = max(0.0, $service->price * $service->count - $service->total_cost);

        return $grossPrice * $companyPercentage / 100;
    }

    /**
     * Credit calculation for cash (self-pay) patients.
     *
     * Priority order for rate lookup:
     *   1. Individual doctor-service pivot (percentage > fixed > default)
     *   2. Doctor's default cash_percentage
     */
    private function calcCashCredit(RequestedService $service, float $cashPercentage): float
    {
        // --- 1. Individual doctor-service settings ---
        $doctorService = $this->specificServices
            ->first(fn ($s) => $s->id == $service->service_id);

        if ($doctorService?->pivot) {
            return $this->applyPivotRate($service, $doctorService->pivot, $cashPercentage);
        }

        // --- 2. Default: cash_percentage on amount paid, net of recorded costs ---
        return $this->netAmountPaid($service) * $cashPercentage / 100;
    }

    /**
     * Apply a pivot rate (percentage or fixed) to a service, falling back to the
     * doctor's default cash_percentage when neither is set.
     *
     * @param  object  $pivot  Eloquent pivot with percentage / fixed fields
     */
    private function applyPivotRate(RequestedService $service, object $pivot, float $cashPercentage): float
    {

        if (($pivot->fixed ?? 0) > 0 && ($pivot->percentage ?? 0) == 0) {
            return $pivot->fixed * $service->count;
        }
        if (($pivot->percentage ?? 0) > 0) {
            return $this->netAmountPaid($service) * $pivot->percentage / 100;
        }

        // Neither percentage nor fixed — fall back to doctor's default.
        return $this->netAmountPaid($service) * $cashPercentage / 100;
    }

    /**
     * Amount paid for a service, net of any recorded requested_service_costs
     * (money owed to a third party), never negative.
     */
    private function netAmountPaid(RequestedService $service): float
    {
        return max(0.0, $service->amount_paid - $service->total_cost);
    }

    /**
     * Request-scoped cache for the single global Setting row, since
     * doctor_credit() may be called hundreds of times per request.
     */
    private static ?Setting $settingCache = null;

    private static bool $settingCacheResolved = false;

    private static function cachedSetting(): ?Setting
    {
        if (! self::$settingCacheResolved) {
            self::$settingCache = Setting::first();
            self::$settingCacheResolved = true;
        }

        return self::$settingCache;
    }
}
