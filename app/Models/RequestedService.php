<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $doctorvisits_id
 * @property int $service_id
 * @property int $user_id
 * @property int|null $user_deposited
 * @property int $doctor_id
 * @property string $price
 * @property string $amount_paid
 * @property string $endurance
 * @property bool $is_paid
 * @property string $discount
 * @property bool $bank
 * @property int $count
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string $doctor_note
 * @property string $nurse_note
 * @property bool $done
 * @property bool $approval
 * @property int $discount_per
 * @property-read \App\Models\User|null $depositUser
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RequestedServiceDeposit> $deposits
 * @property-read int|null $deposits_count
 * @property-read \App\Models\DoctorVisit $doctorVisit
 * @property-read float $balance
 * @property-read float $net_payable_by_patient
 * @property-read float $total_price
 * @property-read \App\Models\Doctor $performingDoctor
 * @property-read \App\Models\User $requestingUser
 * @property-read \App\Models\Service $service
 * @method static \Database\Factories\RequestedServiceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService query()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereAmountPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereApproval($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereBank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereCount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereDiscount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereDiscountPer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereDoctorNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereDoctorvisitsId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereDone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereEndurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereIsPaid($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereNurseNote($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereUserDeposited($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedService whereUserId($value)
 * @mixin \Eloquent
 */
class RequestedService extends Model
{
    use HasFactory;

    protected $table = 'requested_services';

    protected $fillable = [
        'doctorvisits_id', // Or 'doctor_visit_id'
        'service_id',
        'user_id',
        'user_deposited',
        'doctor_id',
        'price',
        'amount_paid',
        'endurance',
        'is_paid',
        'discount',
        'discount_per',
        'bank', // This 'bank' field indicates if the *last or primary* payment was bank. Individual deposits have their own is_bank.
        'count',
        'doctor_note',
        'nurse_note',
        'done',
        'done_by_user_id',
        'done_at',
        'approval',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'endurance' => 'decimal:2',
        'is_paid' => 'boolean',
        'discount' => 'decimal:2',
        'discount_per' => 'integer',
        'bank' => 'boolean',
        'count' => 'integer',
        'done' => 'boolean',
        'done_at' => 'datetime',
        'approval' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function doctorVisit()
    {
        // Ensure this FK name matches your DB schema for requested_services table
        return $this->belongsTo(DoctorVisit::class, 'doctorvisits_id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function requestingUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function depositUser() // User who made the last/main deposit perhaps
    {
        return $this->belongsTo(User::class, 'user_deposited');
    }

    public function doneByUser()
    {
        return $this->belongsTo(User::class, 'done_by_user_id');
    }

    public function performingDoctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    /**
     * Get all payment deposits made for this requested service.
     */
    public function deposits() // <-- THE MISSING RELATIONSHIP
    {
        return $this->hasMany(RequestedServiceDeposit::class, 'requested_service_id');
    }

    public function diagnosis()
    {
        return $this->hasOne(RequestedServiceDiagnosis::class, 'requested_service_id');
    }
    public function totalDepositsBank()
    {
        return $this->deposits()->where('is_bank', 1)->sum('amount');
    }
    public function totalDepositsCash()
    {
        return $this->deposits()->where('is_bank', 0)->sum('amount');
    }
    // Accessors

    public function getTotalPriceAttribute(): float
    {
        return (float) $this->price * (int) ($this->count ?? 1);
    }

    /**
     * Calculates the net amount payable by the patient after discounts and company endurance.
     */
    public function getNetPayableByPatientAttribute(): float
    {
        $totalPrice = $this->total_price; // Uses accessor

        $discountAmountFixed = (float) $this->discount;
        $discountAmountPercentage = ($totalPrice * (int)($this->discount_per ?? 0)) / 100;
        $totalDiscount = $discountAmountFixed + $discountAmountPercentage;

        $amountAfterDiscount = $totalPrice - $totalDiscount;

        $enduranceAmount = 0;
        // To apply endurance conditionally, we need patient context.
        // This requires the doctorVisit and its patient relation to be loaded.
        // $patient = $this->doctorVisit?->patient; // Make sure doctorVisit is loaded
        // if ($patient && $patient->company_id) {
        //     $enduranceAmount = (float) $this->endurance;
        // }
        // For simplicity in the model accessor, if endurance is set, we assume it applies.
        // The controller or service layer should ensure `endurance` is correctly set based on patient type.
        $enduranceAmount = (float) $this->endurance;


        return $amountAfterDiscount - $enduranceAmount;
    }


    public function getBalanceAttribute(): float
    {
        // Net payable by patient (after their discounts and company endurance)
        $netPatientOwes = $this->net_payable_by_patient; // Uses accessor
        return $netPatientOwes - (float) $this->amount_paid;
    }
    public function totalDeposits()
    {
        return $this->deposits()->sum('amount');
    }
    public function getAttributeValue($key)
    {
        if ($key === 'amount_paid') {
            return $this->totalDeposits();
        }

        return parent::getAttributeValue($key);
    }
}
