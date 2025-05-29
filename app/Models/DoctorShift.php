<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DoctorShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',          // User who started/manages this specific doctor's shift
        'shift_id',         // The general clinic shift ID
        'doctor_id',
        'status',           // true (active/open), false (closed)
        'start_time',
        'end_time',
        'is_cash_revenue_prooved',
        'is_cash_reclaim_prooved',
        'is_company_revenue_prooved',
        'is_company_reclaim_prooved',
    ];

    protected $casts = [
        'status' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_cash_revenue_prooved' => 'boolean',
        'is_cash_reclaim_prooved' => 'boolean',
        'is_company_revenue_prooved' => 'boolean',
        'is_company_reclaim_prooved' => 'boolean',
    ];

    /**
     * Get the doctor associated with this shift session.
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the user who created or is associated with this doctor's shift session.
     * This might be the doctor themselves if they log their own shifts, or an admin/manager.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the general clinic shift this doctor's session belongs to.
     */
    public function generalShift() // Renamed to avoid conflict if 'shift' is a keyword or used elsewhere
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Get all visits that occurred during this specific doctor's shift session.
     */
    public function doctorVisits()
    {
        return $this->hasMany(DoctorVisit::class); // Assuming DoctorVisit has a doctor_shift_id FK
    }
    // // These methods calculate sums across all visits within THIS doctor's shift session.
    // public function total_services(): float // Total value of services/labs from visits in this doctor's shift
    // {
    //     return $this->visits->sum(fn(DoctorVisit $visit) => $visit->calculateTotalServiceValue());
    // }
    // public function total_paid_services(): float // Total paid for services/labs from visits in this doctor's shift
    // {
    //     return $this->visits->sum(fn(DoctorVisit $visit) => $visit->calculateTotalPaid());
    // }
    // public function total_bank(): float // Total paid via bank for services/labs from visits in this doctor's shift
    // {
    //     return $this->visits->sum(fn(DoctorVisit $visit) => $visit->calculateTotalBankPayments());
    // }

    // Scopes for querying

    /**
     * Scope a query to only include active doctor shifts.
     */
    public function scopeActive($query)
    {
        // Define what "active" means.
        // Example 1: Explicit status column
        return $query->where('status', true);

        // Example 2: Active if start_time is set and end_time is null
        // return $query->whereNotNull('start_time')->whereNull('end_time');
    }

    /**
     * Scope a query to doctor shifts that are active today.
     */
    public function scopeActiveToday($query)
    {
        // This logic depends heavily on how you track active shifts.
        // If a shift can span multiple days, this is more complex.
        // Assuming a shift is for a single day based on start_time or created_at.
        return $query->active() // Use the active scope
            ->where(function ($q) {
                $q->whereDate('start_time', Carbon::today()) // Started today and still active
                    ->orWhere(function ($q2) { // Or started yesterday but end_time is null or today
                        $q2->whereDate('start_time', Carbon::yesterday())
                            ->where(function ($q3) {
                                $q3->whereNull('end_time')
                                    ->orWhereDate('end_time', Carbon::today());
                            });
                    });
            });
        // Simpler version if 'status' column is reliably managed:
        // ->where('status', true)->whereDate('created_at', Carbon::today());
    }
    /**
     * Get all visits associated with this specific doctor's shift session.
     * This assumes DoctorVisit has a 'doctor_shift_id' FK.
     */
    public function visits()
    {
        return $this->hasMany(DoctorVisit::class, 'doctor_shift_id', 'id');
    }

    /**
     * Calculate doctor's credit from cash-paying patients' services during this shift.
     * This is a complex calculation and depends on your rules.
     */
    public function doctor_credit_cash(): float
    {
        $totalCredit = 0;
        foreach ($this->visits()->whereHas('patient', fn($q) => $q->whereNull('company_id'))->get() as $visit) {
            // Assuming Doctor model has a method to calculate credit for a specific visit
            $totalCredit += $this->doctor->calculateVisitCredit($visit, 'cash');
        }
        return $totalCredit;
    }

    /**
     * Calculate doctor's credit from company/insurance patients' services during this shift.
     */
    public function doctor_credit_company(): float
    {
        $totalCredit = 0;
        foreach ($this->visits()->whereHas('patient', fn($q) => $q->whereNotNull('company_id'))->get() as $visit) {
            $totalCredit += $this->doctor->calculateVisitCredit($visit, 'company');
        }
        return $totalCredit;
    }

    /**
     * Get total amount from all services rendered during this doctor's shift.
     */
    public function total_services(): float
    {
        $total = 0;
        // Iterate through visits, then their requested services/lab requests and sum prices
        // This needs to be precise based on what $doctorvisit->total_services() does
        foreach ($this->visits as $visit) {
            $total += $visit->calculateTotalServiceValue(); // Example method on DoctorVisit
        }
        return $total;
    }

    /**
     * Get total amount paid for all services during this doctor's shift.
     */
    public function total_paid_services(): float
    {
        $totalPaid = 0;
        foreach ($this->visits as $visit) {
            $totalPaid += $visit->calculateTotalPaid(); // Example method on DoctorVisit
        }
        return $totalPaid;
    }

    /**
     * Get total amount paid via bank for services during this doctor's shift.
     */
    public function total_bank(): float
    {
        $totalBank = 0;
        foreach ($this->visits as $visit) {
            $totalBank += $visit->calculateTotalBankPayments(); // Example method on DoctorVisit
        }
        return $totalBank;
    }

    /**
     * Aggregate service costs associated with this doctor's shift.
     * This implies a relationship or logic to link 'service_cost' items to a 'doctor_shift'.
     * For now, assuming a placeholder or that these are general costs for the main shift.
     * This needs clarification on how 'service_costs' are linked.
     */
    public function shift_service_costs(): array
    {
        // Example: If Costs are linked to the general Shift, and DoctorShift is linked to that general Shift
        // $generalShiftId = $this->shift_id;
        // $costs = Cost::where('shift_id', $generalShiftId)
        //                ->whereNotNull('service_id_from_cost_table') // Assuming Cost has a service_id
        //                ->with('serviceFromCostTable') // Assuming Cost has service relation
        //                ->get();
        // $aggregatedCosts = [];
        // foreach($costs as $cost) {
        //    $aggregatedCosts[] = ['name' => $cost->serviceFromCostTable->name, 'amount' => $cost->amount];
        // }
        // return $aggregatedCosts;
        return [
            ['name' => 'تكلفة خدمة افتراضية ١', 'amount' => 500],
            ['name' => 'تكلفة خدمة افتراضية ٢', 'amount' => 300],
        ]; // Placeholder
    }
}
