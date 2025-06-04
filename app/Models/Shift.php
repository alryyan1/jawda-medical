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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\FinanceEntry> $financeEntries
 * @property-read int|null $finance_entries_count
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
        'user_id', // If you add this
        // 'user_id_opened', // If you add this
        // 'user_id_closed', // If you add this
        // 'name', // If you add a name field
        // 'start_datetime', // If you add explicit start/end
        // 'end_datetime',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'bank' => 'decimal:2',
        'expenses' => 'decimal:2',
        'touched' => 'boolean',
        'closed_at' => 'datetime',
        'is_closed' => 'boolean',
        'pharamacy_entry' => 'boolean',
    ];

    // Relationships

    /**
     * Get all patient registrations that occurred during this shift.
     */
    public function patients()
    {
        return $this->hasMany(Patient::class);
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

    /**
     * Get all financial entries associated with this shift.
     */
    public function financeEntries()
    {
        return $this->hasMany(FinanceEntry::class);
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
    public function paidLab(int $userId = null): float
    {
        $query = LabRequest::query()
            ->whereHas('doctorVisit', fn($q) => $q->where('shift_id', $this->id))
            ->where('is_paid', true);
        
        if ($userId) {
            $query->where('user_deposited', $userId);
        }
        return (float) $query->sum('amount_paid');
    }

    /**
     * Total amount paid via Bankak/Bank for lab requests for this shift,
     * optionally filtered by a specific user.
     */
    public function bankakLab(int $userId = null): float
    {
        $query = LabRequest::query()
            ->whereHas('doctorVisit', fn($q) => $q->where('shift_id', $this->id))
            ->where('is_paid', true)
            ->where('is_bankak', true); // Or your field name for bank payment

        if ($userId) {
            $query->where('user_deposited', $userId);
        }
        return (float) $query->sum('amount_paid');
    }

    /**
     * Total amount paid for general services for this shift,
     * optionally filtered by a specific user who handled the deposit.
     */
    public function totalPaidService(int $userId = null): float
    {
        $query = RequestedService::query()
            ->whereHas('doctorVisit', fn($q) => $q->where('shift_id', $this->id))
            ->where('is_paid', true);

        if ($userId) {
            // Assuming RequestedService has user_deposited or similar
            // $query->where('user_deposited_id_on_requested_service', $userId); 
            // For now, sum all if user specific deposit tracking isn't on RequestedService directly
        }
        return (float) $query->sum('amount_paid');
    }
    
    /**
     * Total amount paid via Bank for general services for this shift,
     * optionally filtered by a specific user.
     */
     public function totalPaidServiceBank(int $userId = null): float
     {
         $query = RequestedService::query()
             ->whereHas('doctorVisit', fn($q) => $q->where('shift_id', $this->id))
             ->where('is_paid', true)
             ->where('bank', true); // Assuming 'bank' boolean on RequestedService

         if ($userId) {
             // Filter by user if applicable
         }
         return (float) $query->sum('amount_paid');
     }


    /**
     * Total general costs recorded for this shift, optionally filtered by user.
     * This refers to your `costs` table.
     */
    public function totalCost(int $userId = null): float
    {
        $query = $this->costs(); // Uses the hasMany relationship
        if ($userId) {
            $query->where('user_cost', $userId);
        }
        // Sum 'amount' + 'amount_bankak' from costs table
        return (float) $query->sum(DB::raw('amount + amount_bankak'));
    }

    /**
     * Total general costs paid via bank for this shift, optionally by user.
     */
    public function totalCostBank(int $userId = null): float
    {
        $query = $this->costs();
        if ($userId) {
            $query->where('user_cost', $userId);
        }
        return (float) $query->sum('amount_bankak');
    }
    
    /**
     * Clinic's service costs (not patient-specific, but general operational costs for services).
     * This is a complex one. It depends on how you define and link "service costs" that are not
     * directly tied to a patient visit but are part of the clinic's operational expenses for services during this shift.
     * The original PDF code ` $doctorShift->shift_service_costs()` was on DoctorShift.
     * If these are general shift costs related to services:
     */
    public function shiftClinicServiceCosts(): array
    {
        // This needs a clear data source. Are these from the `costs` table with a specific category?
        // Or a different table entirely?
        // Example: Costs categorized as "Service Operational Cost"
        $serviceOperationalCosts = $this->costs()
                                    ->whereHas('costCategory', fn($q) => $q->where('name', 'LIKE', '%مصروف خدمة%')) // Example category name
                                    ->get();
        $aggregated = [];
        foreach ($serviceOperationalCosts as $cost) {
            $aggregated[] = [
                'name' => $cost->description, // Or a more specific cost item name
                'amount' => (float) ($cost->amount + $cost->amount_bankak),
            ];
        }
        // If you have a table that directly links services to their operational costs (materials etc.)
        // and then link those to the shift, the logic would be different.
        return $aggregated; // Return empty array if no such costs
    }
    public  function cost()
    {
        return $this->hasMany(Cost::class);
    }
}