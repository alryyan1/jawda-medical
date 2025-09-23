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
 * @property int $user_id
 * @property int $shift_id
 * @property int $doctor_id
 * @property bool $status Is this doctor shift session currently active?
 * @property \Illuminate\Support\Carbon|null $start_time Actual start time of the doctor working
 * @property \Illuminate\Support\Carbon|null $end_time Actual end time of the doctor working
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $is_cash_revenue_prooved
 * @property bool $is_cash_reclaim_prooved
 * @property bool $is_company_revenue_prooved
 * @property bool $is_company_reclaim_prooved
 * @property-read \App\Models\Doctor $doctor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DoctorVisit> $doctorVisits
 * @property-read int|null $doctor_visits_count
 * @property-read \App\Models\Shift $generalShift
 * @property-read int|null $visits_count
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DoctorVisit> $visits
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift active()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift activeToday()
 * @method static \Database\Factories\DoctorShiftFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift query()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereEndTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereIsCashReclaimProoved($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereIsCashRevenueProoved($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereIsCompanyReclaimProoved($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereIsCompanyRevenueProoved($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereStartTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorShift whereUserId($value)
 * @mixin \Eloquent
 */
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
  


    public function additionalCosts()
    {
        $arr = [];
        foreach ($this->shift_service_costs() as $cost) {
            $min = [];
            $min['id'] = $cost['id'];
            $min['name'] = $cost['name'];
            $min['amount'] = $cost['amount'];
            $arr[] = $min;
        }

        return $arr;
    }
    public function getTotalCostsByServiceCostId($cost_id)
    {
        $total = 0;
        foreach ($this->visits as $visit) {
            $total += $visit->total_services_cost($cost_id);
        }
        return $total;
    }
    public function getVisitsCountAttribute(){
        $pdo = DB::getPdo();
        $stmt = $pdo->prepare('SELECT count(id)  FROM doctorvisits WHERE doctor_shift_id = ?');
        // $stmt->bindParam(':shift_id', $this->id);
        $stmt->execute([$this->id]);
        // return $this->id;;
        return $stmt->fetchColumn();
    }
    public function shift_service_costs()
    {
        $costs = collect();

        /**@var Doctorvisit $visit */
        foreach ($this->visits as $visit) {

            foreach ($visit->service_costs() as $cost) {
                // if(!$costs->contains(function($el)use($cost){
                //     return $el->id == $cost->id;
                // })){
                //     //add to collection
                // }

                $arr = [];
                $arr['id'] = $cost->subServiceCost->id;
                $arr['name'] = $cost->subServiceCost->name;
                $arr['amount'] = $visit->total_services_cost($cost->id);
                $costs->push($arr);
            }
        }
        // return $costs;
        return $costs
            ->groupBy('id') // Group by the 'id' key
            ->map(function ($group) {
                return [
                    'id' => $group->first()['id'],
                    'name' => $group->first()['name'],
                    'amount' => $group->sum('amount'), // Sum the 'amount' field
                ];
            })
            ->values() // Reset the keys
            ->toArray();
    }
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
    public function scopeLatestGeneralShift($query)
    {
        $shift_id = Shift::latest()->first()->id;
        return $query->where('shift_id', $shift_id)->where('status', true);
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
    public function doctor_credit_cash()
    {
        $total_credit = 0;
        $start = 0;
        /** @var Doctorvisit $doctorvisit */
        foreach ($this->visits as $doctorvisit) {
            $start++;
            if ($start >= $this->doctor->start) {
                if ($doctorvisit->patient->company_id == null) {
                    $total_credit += $this->doctor->doctor_credit($doctorvisit);
                }
            }
        }
        return $total_credit;
    }
    
    public function clinic_cash()
    {
        $total_cash = 0;
        $start = 0;
        /** @var Doctorvisit $doctorvisit */
        foreach ($this->visits as $doctorvisit) {
                if ($doctorvisit->patient->company_id == null) {
                // echo "s";

                   $cash =  $doctorvisit->total_paid_services($this->doctor) - $doctorvisit->bankak_service();
                   $total_cash += $cash;
            }
        }
        return $total_cash;
    }
    public function clinic_bank()
    {
        $total_bank = 0;
        $start = 0;
        /** @var Doctorvisit $doctorvisit */
        foreach ($this->visits as $doctorvisit) {
            $start++;
                if ($doctorvisit->patient->company_id == null) {
                   $bank =  $doctorvisit->bankak_service();
                   $total_bank += $bank;
            }
        }
        return $total_bank;
    }
    public function clinic_bank_company()
    {
        $total_bank = 0;
        $start = 0;
        /** @var Doctorvisit $doctorvisit */
        foreach ($this->visits as $doctorvisit) {
            $start++;
                if ($doctorvisit->patient->company_id != null) {
                   $bank =  $doctorvisit->bankak_service();
                   $total_bank += $bank;
            }
        }
        return $total_bank;
    }
    /**
     * Calculate doctor's credit from company/insurance patients' services during this shift.
     */
  
    public function doctor_credit_company()
    {
        $total_credit = 0;
        $start = 0;

        /** @var Doctorvisit $doctorvisit */
        foreach ($this->visits as $doctorvisit) {
            $start++;
            if ($start >= $this->doctor->start) {

                if ($doctorvisit->patient->company_id != null) {
                    $total_credit += $this->doctor->doctor_credit($doctorvisit);
                }
            }
        }
        return $total_credit;
    }
    public function count_cash()
    {
        $count = 0;
        /** @var Doctorvisit $doctorvisit */
        foreach ($this->visits as $doctorvisit) {
            if ($doctorvisit->patient->company == null) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Get total amount from all services rendered during this doctor's shift.
     */
    public function total_services()
    {
        $total_paid = 0;
        /** @var Doctorvisit $doctorvisit */
        foreach ($this->visits as $doctorvisit) {
            $total_paid += $doctorvisit->total_services($this->doctor);
        }
        return $total_paid;
    }
      public function count_insurance()
    {
        $count = 0;
        /** @var Doctorvisit $doctorvisit */
        foreach ($this->visits as $doctorvisit) {
            if ($doctorvisit->patient->company != null) {
                $count++;
            }
        }
        return $count;
    }
    /**
     * Get total amount paid for all services during this doctor's shift.
     */
    public function total_paid_services()
    {
        $total_paid = 0;
        /** @var Doctorvisit $doctorvisit */
        foreach ($this->visits as $doctorvisit) {
            $total_paid += $doctorvisit->total_paid_services($this->doctor);
        }
        return $total_paid;
    }

    /**
     * Get total amount paid via bank for services during this doctor's shift.
     */
    public function total_bank()
    {
        $total_paid = 0;
        /** @var Doctorvisit $doctorvisit */
        foreach ($this->visits as $doctorvisit) {
            $total_paid += $doctorvisit->bankak_service();
        }
        return $total_paid;
    }
    public function hospital_credit()
    {
        $total =0;
        foreach($this->visits as $visit){
            $total += $visit->hospital_credit();
        }
        return $total;
    }

}
