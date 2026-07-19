<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * 
 *
 * @property int $id
 * @property string $total
 * @property string $bank
 * @property string $expenses
 * @property bool $touched
 * @property \Illuminate\Support\Carbon|null $closed_at
 * @property bool $is_closed
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool|null $pharamacy_entry
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Cost> $cost
 * @property-read int|null $cost_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Cost> $costs
 * @property-read int|null $costs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DoctorShift> $doctorShifts
 * @property-read int|null $doctor_shifts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DoctorVisit> $doctorVisits
 * @property-read int|null $doctor_visits_count
 * @property-read float $net_cash
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Patient> $patients
 * @property-read int|null $patients_count
 * @method static \Illuminate\Database\Eloquent\Builder|Shift closed()
 * @method static \Database\Factories\ShiftFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Shift newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Shift newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Shift open()
 * @method static \Illuminate\Database\Eloquent\Builder|Shift query()
 * @method static \Illuminate\Database\Eloquent\Builder|Shift today()
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereBank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereClosedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereExpenses($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereIsClosed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift wherePharamacyEntry($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereTotal($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereTouched($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Shift whereUserId($value)
 * @mixin \Eloquent
 */
class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'total',
        'bank',
        'expenses',
        'touched',
        'closed_at',
        'is_closed',
        'pharamacy_entry',
        'user_closed',
        'user_id', // If you add this
        // 'user_id_opened', // If you add this
        // 'user_id_closed', // If you add this
        // 'name', // If you add a name field
        // 'start_datetime', // If you add explicit start/end
        // 'end_datetime',
    ];

    protected $guarded = [
        'user_closed',
    ];


    protected $casts = [
        'total' => 'decimal:2',
        'bank' => 'decimal:2',
        'expenses' => 'decimal:2',
        'touched' => 'boolean',
        'closed_at' => 'datetime',
        'is_closed' => 'boolean',
        'pharamacy_entry' => 'boolean',
        'user_closed' => 'integer',
    ];

    // Relationships

    /**
     * Get all patient registrations that occurred during this shift.
     */
    public function patients()
    {
        return $this->hasMany(Doctorvisit::class)->orderBy('id', 'desc');
    }
    /**
     * Get all doctor-specific work sessions that occurred within this general shift.
     */
    public function doctorShifts()
    {
        return $this->hasMany(DoctorShift::class);
    }

    /**
     * Get all doctor visits that occurred during this shift.
     */
    public function doctorVisits()
    {
        return $this->hasMany(DoctorVisit::class);
    }

 

    public function totalLabDiscount($user = null)
    {
        $total = 0;
        /** @var Doctorvisit $patient */
        foreach ($this->patients as $patient) {
            $total += $patient->patient->discountAmount($user);
        }
        return $total;
    }
    /**
     * Get all costs recorded during this shift.
     */
    public function costs()
    {
        return $this->hasMany(Cost::class);
    }

    /**
     * Get the user who opened this shift (if tracked).
     * public function openedBy() {
     *     return $this->belongsTo(User::class, 'user_id_opened');
     * }
     */

    /**
     * Get the user who closed this shift (if tracked).
     * public function closedBy() {
     *     return $this->belongsTo(User::class, 'user_id_closed');
     * }
     */


    // Scopes

    /**
     * Scope a query to only include open shifts.
     */
    public function scopeOpen($query)
    {
        return $query->where('is_closed', false);
    }

    /**
     * Scope a query to only include closed shifts.
     */
    public function scopeClosed($query)
    {
        return $query->where('is_closed', true);
    }

    /**
     * Scope a query to shifts created today.
     */
    public function scopeToday($query)
    {
        return $query->whereDate('created_at', Carbon::today());
    }


    // Business Logic (Example)

    /**
     * Calculate the net cash for the shift.
     * This is a conceptual example; actual calculation might be more complex
     * or derived from finance_entries.
     */
    public function getNetCashAttribute(): float
    {
        // Assuming 'total' is total cash collected, and 'bank' is amount deposited
        // and 'expenses' are cash expenses from the till.
        // Net Cash in hand = Total Cash - Bank Deposits - Cash Expenses
        // Or, more accurately, this would be calculated from summing relevant transactions.
        // For now, a simple calculation based on existing fields.
        return (float) $this->total - (float) $this->bank - (float) $this->expenses;
    }
    /**
     * Total amount paid for lab requests associated with this general shift,
     * optionally filtered by a specific user who handled the deposit.
     */
    public function paidLab($user = null)
    {
        $total = 0;
        /** @var Doctorvisit $patient */
        foreach ($this->patients as $patient) {
            $total += $patient->patient->paid_lab($user);
        }
        return $total;
    }

    /**
     * Total amount paid via Bankak/Bank for lab requests for this shift,
     * optionally filtered by a specific user.
     */
    public function bankakLab($user = null)
    {
        $total = 0;
        /** @var Doctorvisit $patient */
        foreach ($this->patients as $patient) {
            $total += $patient->patient->lab_bank($user);
        }
        return $total;
    }
    /**
     * Total amount paid for general services for this shift,
     * optionally filtered by a specific user who handled the deposit.
     */
    public function totalPaidService($user = null): mixed
    {
        $total = 0;
        // /** @var DoctorShift $doctorShift */
        // foreach ($this->doctorShifts as $doctorShift) {
        //     /** @var Doctorvisit $doctorvisit */
        //     foreach ($doctorShift->visits as $doctorvisit) {
        //         $total += $doctorvisit->total_paid_services(null, $user);
        //     }
        // }
        if ($user) {
            return RequestedServiceDeposit::where('shift_id', $this->id)->where('user_id', $user)->sum('amount');
        } else {
            return RequestedServiceDeposit::where('shift_id', $this->id)->sum('amount');
        }
        // return $total;
    }

    /**
     * Total amount paid via Bank for general services for this shift,
     * optionally filtered by a specific user.
     */
    public function totalPaidServiceBank($user = null): mixed
    {
        $total = 0;
        /** @var DoctorShift $doctorShift */
        // foreach ($this->doctorShifts as $doctorShift) {

        //     /** @var Doctorvisit $doctorvisit */
        //     foreach ($doctorShift->visits as $doctorvisit) {
        //         foreach ($doctorvisit->services as $service) {
        //             if ($user != null) {

        //                 if ($service->user_deposited != $user) continue;
        //             }
        //             $total += $service->totalDepositsBank();
        //         }
        //     }
        // }
        if ($user) {
            return RequestedServiceDeposit::where('shift_id', $this->id)->where('user_id', $user)->where('is_bank', 1)->sum('amount');
        } else {
            return RequestedServiceDeposit::where('shift_id', $this->id)->where('is_bank', 1)->sum('amount');
        }
    }

    public function totalCost($user = null)
    {
        $total = 0;
        foreach ($this->cost as $cost) {
            if ($user) {
                if ($cost->user_cost != $user) continue;
            }
            $total += ($cost->amount + $cost->amount_bankak);
        }
        return $total;
    }
    public function totalCostBank($user = null)
    {
        $total = 0;
        foreach ($this->cost as $cost) {
            if ($user) {
                if ($cost->user_cost != $user) continue;
            }
            $total += $cost->amount_bankak;
        }
        return $total;
    }

    public  function cost()
    {
        return $this->hasMany(Cost::class);
    }
    // In App/Models/Shift.php
    public function userOpened() // Or just user() if user_id means opened_by
    {
        return $this->belongsTo(User::class, 'user_id'); // Or 'user_id_opened'
    }

    public function userClosed()
    {
        return $this->belongsTo(User::class, 'user_closed');
    }
}
