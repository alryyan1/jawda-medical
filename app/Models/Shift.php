<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
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
 *
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
 *
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
     * Per-user payment and cost totals for this shift, computed with grouped
     * queries (lab requests, service deposits, costs) instead of per-user loops.
     * Only users with payments are included.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, username: string|null, total_paid: float, total_bank: float, total_cash: float, total_cost: float, total_cost_bank: float, net_bank: float, net_cash: float}>
     */
    public function collectionsByUser(): \Illuminate\Support\Collection
    {
        $labTotals = LabRequest::query()
            ->join('doctorvisits', 'labrequests.pid', '=', 'doctorvisits.patient_id')
            ->where('doctorvisits.shift_id', $this->id)
            ->where('labrequests.is_paid', 1)
            ->whereNotNull('labrequests.user_deposited')
            ->groupBy('labrequests.user_deposited')
            ->select([
                'labrequests.user_deposited as user_id',
                DB::raw('SUM(labrequests.amount_paid) as total_paid'),
                DB::raw('SUM(CASE WHEN labrequests.is_bankak = 1 THEN labrequests.amount_paid ELSE 0 END) as total_bank'),
            ])
            ->get()
            ->keyBy('user_id');

        $serviceTotals = RequestedServiceDeposit::query()
            ->where('shift_id', $this->id)
            ->groupBy('user_id')
            ->select([
                'user_id',
                DB::raw('SUM(amount) as total_paid'),
                DB::raw('SUM(CASE WHEN is_bank = 1 THEN amount ELSE 0 END) as total_bank'),
            ])
            ->get()
            ->keyBy('user_id');

        $costTotals = Cost::query()
            ->where('shift_id', $this->id)
            ->whereNotNull('user_cost')
            ->groupBy('user_cost')
            ->select([
                'user_cost as user_id',
                DB::raw('SUM(amount + amount_bankak) as total_cost'),
                DB::raw('SUM(amount_bankak) as total_cost_bank'),
            ])
            ->get()
            ->keyBy('user_id');

        $userIds = $labTotals->keys()->merge($serviceTotals->keys())->unique()->values();

        return User::whereIn('id', $userIds)
            ->orderBy('id')
            ->get()
            ->map(function (User $user) use ($labTotals, $serviceTotals, $costTotals) {
                $totalPaid = (float) ($labTotals[$user->id]->total_paid ?? 0) + (float) ($serviceTotals[$user->id]->total_paid ?? 0);
                $totalBank = (float) ($labTotals[$user->id]->total_bank ?? 0) + (float) ($serviceTotals[$user->id]->total_bank ?? 0);
                $totalCost = (float) ($costTotals[$user->id]->total_cost ?? 0);
                $totalCostBank = (float) ($costTotals[$user->id]->total_cost_bank ?? 0);
                $totalCash = $totalPaid - $totalBank;
                $netCash = $totalCash - ($totalCost - $totalCostBank);

                return [
                    'id' => $user->id,
                    'name' => $user->name ?: $user->username,
                    'username' => $user->username,
                    'total_paid' => $totalPaid,
                    'total_bank' => $totalBank,
                    'total_cash' => $totalCash,
                    'total_cost' => $totalCost,
                    'total_cost_bank' => $totalCostBank,
                    'net_bank' => $totalBank - $totalCostBank,
                    'net_cash' => $netCash,
                ];
            })
            ->filter(fn (array $row) => $row['total_paid'] > 0 || $row['total_bank'] > 0)
            ->values();
    }

    /**
     * Payment and cost totals for a single user within this shift, computed with
     * scalar sum queries instead of the per-visit PHP loops used by paidLab()/
     * totalPaidService()/totalCost() and friends.
     *
     * @return array{total_paid_service: float, total_bank_service: float, total_lab: float, total_lab_bank: float, total_cost: float, total_cost_bank: float}
     */
    public function userIncomeTotals(int $userId): array
    {
        $labTotals = LabRequest::query()
            ->join('doctorvisits', 'labrequests.pid', '=', 'doctorvisits.patient_id')
            ->where('doctorvisits.shift_id', $this->id)
            ->where('labrequests.is_paid', 1)
            ->where('labrequests.user_deposited', $userId)
            ->selectRaw('SUM(labrequests.amount_paid) as total_paid')
            ->selectRaw('SUM(CASE WHEN labrequests.is_bankak = 1 THEN labrequests.amount_paid ELSE 0 END) as total_bank')
            ->first();

        $serviceTotals = RequestedServiceDeposit::query()
            ->where('shift_id', $this->id)
            ->where('user_id', $userId)
            ->selectRaw('SUM(amount) as total_paid')
            ->selectRaw('SUM(CASE WHEN is_bank = 1 THEN amount ELSE 0 END) as total_bank')
            ->first();

        $costTotals = Cost::query()
            ->where('shift_id', $this->id)
            ->where('user_cost', $userId)
            ->selectRaw('SUM(amount + amount_bankak) as total_cost')
            ->selectRaw('SUM(amount_bankak) as total_cost_bank')
            ->first();

        return [
            'total_paid_service' => (float) ($serviceTotals->total_paid ?? 0),
            'total_bank_service' => (float) ($serviceTotals->total_bank ?? 0),
            'total_lab' => (float) ($labTotals->total_paid ?? 0),
            'total_lab_bank' => (float) ($labTotals->total_bank ?? 0),
            'total_cost' => (float) ($costTotals->total_cost ?? 0),
            'total_cost_bank' => (float) ($costTotals->total_cost_bank ?? 0),
        ];
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
                if ($cost->user_cost != $user) {
                    continue;
                }
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
                if ($cost->user_cost != $user) {
                    continue;
                }
            }
            $total += $cost->amount_bankak;
        }

        return $total;
    }

    public function cost()
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
