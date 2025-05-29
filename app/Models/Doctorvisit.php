<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorVisit extends Model
{
    use HasFactory;

    // If your table name is 'doctorvisits' (plural)
    protected $table = 'doctorvisits';

    protected $fillable = [
        'patient_id',
        'doctor_id',
        'user_id',
        'shift_id',
        'doctor_shift_id',
        // 'appointment_id',
        'file_id',
        'visit_date',
        'visit_time',
        'status',
        'visit_type',
        'queue_number',
        'reason_for_visit',
        'visit_notes',
        'is_new', // from original schema
        'number', // from original schema
        'only_lab', // from original schema
    ];

    protected $casts = [
        'visit_date' => 'date',
        // 'visit_time' => 'datetime:H:i:s', // If storing as TIME, Laravel might handle it without explicit cast. If DATETIME, use 'datetime'
        'is_new' => 'boolean',
        'only_lab' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships
    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * User who created/managed this visit entry (e.g., receptionist).
     */
    public function createdByUser() // Renamed to avoid conflict if a 'user' is the patient/doctor
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * The general clinic shift this visit belongs to.
     */
    public function generalShift()
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * The specific doctor's working session, if applicable.
     */
    public function doctorShift()
    {
        return $this->belongsTo(DoctorShift::class);
    }

    /**
     * The appointment linked to this visit, if any.
     * public function appointment() {
     *     return $this->belongsTo(Appointment::class); // Or hasOne if FK is on appointments table
     * }
     */
    
    /**
     * The "file" associated with this visit, if any.
     */
    public function file()
    {
        return $this->belongsTo(File::class); // Assuming File model exists
    }


    /**
     * Get all requested services for this visit.
     */
    public function requestedServices()
    {
        // Adjust FK name if your requested_services table uses 'doctor_visit_id'
        return $this->hasMany(RequestedService::class, 'doctorvisits_id'); 
    }

    /**
     * Get all lab requests associated with this visit.
     */
    public function labRequests()
    {
        return $this->hasMany(LabRequest::class, 'doctor_visit_id');
    }
    
    /**
     * Get all prescriptions issued during this visit.
     */
    public function prescriptions()
    {
        return $this->hasMany(DrugPrescribed::class, 'doctor_visit_id');
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('visit_date', today());
    }

    public function scopeStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeWaiting($query)
    {
        return $query->where('status', 'waiting');
    }

    public function scopeWithDoctor($query)
    {
        return $query->where('status', 'with_doctor');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
    public function calculateVisitCredit(DoctorVisit $visit, string $paymentType): float
    {
        $credit = 0;
        $cashPercentage = $this->cash_percentage / 100;
        $companyPercentage = $this->company_percentage / 100;
        // Potentially $labPercentage = $this->lab_percentage / 100;
    
        // For Requested Services
        foreach ($visit->requestedServices as $rs) {
            $amountForCalc = (float)$rs->amount_paid; // Or net payable after endurance
            // If $paymentType is determined by patient, not by service payment method
            if ($paymentType === 'cash') {
                $credit += $amountForCalc * $cashPercentage;
            } elseif ($paymentType === 'company') {
                $credit += $amountForCalc * $companyPercentage;
            }
        }
    
        // For Lab Requests
        foreach ($visit->labRequests as $lr) {
            $amountForCalc = (float)$lr->amount_paid; // Or net payable after endurance
            // Assuming lab requests follow the same percentage rules as services.
            // If lab has its own percentage ($this->lab_percentage), apply it here.
            if ($paymentType === 'cash') {
                $credit += $amountForCalc * $cashPercentage; // Or $labPercentage
            } elseif ($paymentType === 'company') {
                $credit += $amountForCalc * $companyPercentage; // Or $labPercentage
            }
        }
        return $credit;
    }
       /**
     * Calculate total cost of providing services/labs for THIS visit.
     * This requires a 'cost' field on Service/MainTest or a related costs table.
     */
    public function total_services_cost(): float
    {
        $totalCost = 0;
        foreach ($this->requestedServices as $rs) {
            // Assuming Service model has a 'cost_price' attribute
            $totalCost += (float)($rs->service?->cost_price ?? 0) * (int)($rs->count ?? 1);
        }
        foreach ($this->labRequests as $lr) {
             // Assuming MainTest model has a 'cost_price' attribute
            $totalCost += (float)($lr->mainTest?->cost_price ?? 0); // Assuming count is 1
        }
        return $totalCost;
    }

    /**
     * Concatenated string of service cost names (or descriptions of costs incurred for this visit).
     */
    public function services_cost_name(): string
    {
        // This is highly dependent on how you track costs.
        // If costs are line items:
        // return $this->visitCosts()->pluck('description')->implode(', ');
        if ($this->total_services_cost() > 0) {
            return "تكاليف تشغيلية للخدمات والفحوصات"; // Generic description
        }
        return "-";
    }

    /**
     * Hospital's net credit from this visit.
     * Total collected for this visit - doctor's share for this visit.
     * The service costs are general clinic expenses, not subtracted per visit for hospital credit *from this visit*.
     */
    public function hospital_credit(): float
    {
        $totalCollectedForVisit = $this->calculateTotalPaid();
        
        // Ensure doctorShift relationship is loaded or available
        if (!$this->doctorShift || !$this->doctorShift->doctor) {
            // Fallback or error if doctorShift relationship isn't loaded,
            // or if DoctorVisit is not directly linked to a DoctorShift
            // but rather to a general Shift and a Doctor.
            // In that case, use $this->doctor for percentages.
            $doctorForCredit = $this->doctor; // The doctor of the visit
        } else {
            $doctorForCredit = $this->doctorShift->doctor;
        }
        
        if (!$doctorForCredit) return $totalCollectedForVisit; // No doctor, all goes to hospital

        $doctorShare = $doctorForCredit->calculateVisitCredit($this, $this->patient->company_id ? 'company' : 'cash');
        
        return $totalCollectedForVisit - $doctorShare;
    }
    
  /**
     * Calculate total value of services (and lab tests if they are services) for this visit,
     * potentially considering the specific doctor's pricing or contract if applicable.
     * For this example, sums price * count from requested_services.
     */
    public function calculateTotalServiceValue(Doctor $contextDoctor = null): float
    {
        $total = 0;
        foreach ($this->requestedServices as $rs) {
            $total += ((float)$rs->price * (int)($rs->count ?? 1));
        }
        // Add LabRequest prices if they are separate from services
        foreach ($this->labRequests as $lr) {
            $total += (float)$lr->price; // Assuming count is 1 for lab requests
        }
        return $total;
    }

    /**
     * Calculate total amount paid for services/labs in this visit.
     */
    public function calculateTotalPaid(): float
    {
        $totalPaid = 0;
        foreach ($this->requestedServices as $rs) {
            $totalPaid += (float)$rs->amount_paid;
        }
        foreach ($this->labRequests as $lr) {
            $totalPaid += (float)$lr->amount_paid;
        }
        return $totalPaid;
    }

    /**
     * Calculate total amount paid via bank for this visit.
     */
    public function calculateTotalBankPayments(): float
    {
         $totalBank = 0;
         foreach($this->requestedServices as $rs) {
             if ($rs->bank) { // Assuming 'bank' boolean on RequestedService
                 $totalBank += (float)$rs->amount_paid;
             }
         }
         foreach($this->labRequests as $lr) {
             if ($lr->is_bankak) { // Assuming 'is_bankak' boolean on LabRequest
                 $totalBank += (float)$lr->amount_paid;
             }
         }
         return $totalBank;
    }
    
    /**
     * Concatenated string of service names for this visit.
     */
    public function services_concatinated(): string
    {
        return $this->requestedServices()->with('service:id,name')
                    ->get()->pluck('service.name')->implode(', ');
    }

 


    /**
     * Hospital's credit from this visit (e.g. total collected - doctor share - service costs)
     * This is highly dependent on your financial model.
     */
    // public function hospital_credit(): float
    // {
    //     $totalCollected = $this->calculateTotalPaid();
    //     $doctorShare = $this->doctorShift->doctor->calculateVisitCredit($this, $this->patient->company_id ? 'company' : 'cash');
    //     $serviceCosts = $this->total_services_cost();
        
    //     // return $totalCollected - $doctorShare - $serviceCosts; // Simplified
    //     return $this->calculateTotalServiceValue() * 0.2; // Placeholder 20% hospital share of total service value
    // }

}