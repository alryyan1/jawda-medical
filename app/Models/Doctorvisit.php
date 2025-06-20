<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $patient_id
 * @property int $doctor_id
 * @property int $user_id
 * @property int|null $doctor_shift_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $is_new Is this a new patient visit or a follow-up for an existing issue?
 * @property int $number Original "number" column, clarify purpose. Queue number?
 * @property bool $only_lab Is this visit solely for lab work without doctor consultation?
 * @property int $shift_id
 * @property int|null $file_id
 * @property \Illuminate\Support\Carbon $visit_date
 * @property string|null $visit_time
 * @property string $status
 * @property string|null $visit_type e.g., New, Follow-up, Emergency, Consultation
 * @property int|null $queue_number
 * @property string|null $reason_for_visit
 * @property string|null $visit_notes
 * @property-read \App\Models\AuditedPatientRecord|null $auditRecord
 * @property-read \App\Models\User|null $createdByUser
 * @property-read \App\Models\Doctor|null $doctor
 * @property-read \App\Models\DoctorShift|null $doctorShift
 * @property-read \App\Models\File|null $file
 * @property-read \App\Models\Shift|null $generalShift
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LabRequest> $labRequests
 * @property-read int|null $lab_requests_count
 * @property-read \App\Models\Patient $patient
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\DrugPrescribed> $prescriptions
 * @property-read int|null $prescriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RequestedService> $requestedServices
 * @property-read int|null $requested_services_count
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit completed()
 * @method static \Database\Factories\DoctorVisitFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit query()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit status($status)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit today()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit waiting()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereDoctorShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereFileId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereIsNew($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereOnlyLab($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereQueueNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereReasonForVisit($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereVisitDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereVisitNotes($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereVisitTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit whereVisitType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorVisit withDoctor()
 * @mixin \Eloquent
 */
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

    public function patientLabRequests()
    {
        return $this->hasManyThrough(
            \App\Models\LabRequest::class,
            \App\Models\Patient::class,
            'id',               // Foreign key on Patient (usually 'id')
            'pid',       // Foreign key on LabRequest
            'patient_id',       // Local key on DoctorVisit
            'id'                // Local key on Patient
        );
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
        public function bankak_service()
    {
        $total = 0;
        foreach ($this->requestedServices as $service) {
            $total+= $service->totalDepositsBank();
        }
        return $total;
    }
 
    public function total_paid_services(Doctor|null $doctor  = null, $user = null)
    {
        $total = 0;
        //        dd($this->services);
        foreach ($this->requestedServices as $service) {
            //            if (!$service->is_paid) continue;
            // if (!is_null($doctor)) {
            //     if ($doctor->id != $service->doctor_id) {
            //         continue;
            //     }
            // }
            // if($service->service->variable) continue;
            if ($user != null) {
                
               if ($service->user_deposited != $user) continue;
                $total += $service->amount_paid;
            } else {
                
                $total += $service->amount_paid;
            }
        }
        return $total;
    }
    public function total_services(Doctor|null $doctor  = null, $user = null)
    {
        $total = 0;
        //        dd($this->services);
        foreach ($this->requestedServices as $service) {

            //            if (!$service->is_paid) continue;
            if (!is_null($doctor)) {
                if ($doctor->id != $service->doctor_id) {
                    continue;
                }
            }
            if ($user != null) {
                if ($service->user_deposited != $user) continue;
                $total += $service->price;
            } else {
                $total += $service->price * $service->count;
            }
        }
        return $total;
    }

    public function total_services_cost($cost_id = null)
    {
        $total = 0;
        /**@var RequestedService $requested_service */
        foreach ($this->requestedServices as $requested_service) {
            


            /**@var RequestedServiceCost $requestedServiceCost */
            foreach ($requested_service->requestedServiceCosts as $requestedServiceCost) {
                // $total += 500
                // echo $requestedServiceCost->amount;
                $service_cost = $requestedServiceCost->serviceCost;
                if ($cost_id) {
                    // echo $cost_id . ' -  service_cost_id: ' . $service_cost?->id.'<br>';
                    if ($cost_id != $service_cost?->id) continue;
                }
                $total += $requestedServiceCost->amount;
            }
        }
        return $total;
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
    public function service_costs()
    {
        $total = [];
        /**@var RequestedService $requested_service */
        foreach ($this->requestedServices as $requested_service) {

            /**@var ServiceCost $service_cost */
            foreach ($requested_service->service->service_costs as $service_cost) {
                $total[] = $service_cost;
            }
        }
        return $total;
    }
    /**
     * Hospital's net credit from this visit.
     * Total collected for this visit - doctor's share for this visit.
     * The service costs are general clinic expenses, not subtracted per visit for hospital credit *from this visit*.
     */
    public function hospital_credit()
    {
        return ($this->total_paid_services() - $this->totalServiceCosts($this->patient->doctor)) - ($this->doctorShift->doctor->doctor_credit($this));
    }
  /**
     * Calculate total value of services (and lab tests if they are services) for this visit,
     * potentially considering the specific doctor's pricing or contract if applicable.
     * For this example, sums price * count from requested_services.
     */
   
    public function totalServiceCosts($doctor)
    {
        $total = 0;
        foreach ($this->requestedServices as $requested_service) {
            $total += $requested_service->getTotalCosts($doctor);
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
     * Concatenated string of service names for this visit.
     */
    public function services_concatinated(): string
    {
        return $this->requestedServices()->with('service:id,name')
                    ->get()->pluck('service.name')->implode(', ');
    }

 


      /**
     * Get the audit record associated with this doctor visit.
     */
    public function auditRecord() // <-- THE NEW RELATIONSHIP
    {
        return $this->hasOne(AuditedPatientRecord::class, 'doctor_visit_id');
    }
    public function totalEnduranceWillPay(){
        $total = 0;
        /**@var RequestedService $rs */
        foreach($this->requestedServices as $rs){
            $total += $rs->endurance;
        }
        return $total;
    }

    public function amountRemaining(){
        $total_paid = 0;
        /**@var RequestedService $rs */
        foreach($this->requestedServices as $rs){
            $total_paid += $rs->totalDeposits();
        }
       if($this->patient->company_id){
          return $total_paid - $this->totalEnduranceWillPay();
       }
       return $this->total_services() - $total_paid;
    }
    public function user(){
        return $this->belongsTo(User::class, 'user_id');
    }
    public function scopeLoadDefaultLabReportRelations($query) {
        return $query->with([
            'patient.company', 
            'doctor:id,name',
            'patient.labRequests' => fn ($q) => $q->where('hidden', false)->orderBy('id'),
            'patient.labRequests.mainTest.childTests' => fn($q_ct) => $q_ct->with(['unit:id,name', 'childGroup:id,name'])->orderBy('test_order')->orderBy('id'),
            'patient.labRequests.mainTest.package:package_id,package_name',
            'patient.labRequests.requestingUser:id,name',
            'patient.labRequests.results.unit:id,name',      // For result's own unit snapshot
            'patient.labRequests.results.childTest',        // For child test definition context
            'patient.labRequests.results.enteredBy:id,name', // If you have this field on RequestedResult
            'patient.labRequests.results.authorizedBy:id,name',// If you have this field on RequestedResult
            'patient.labRequests.authorizedBy:id,name',     // For overall LabRequest authorization
            'patient.labRequests.requestedOrganisms',
            'user:id,name' // User who created visit
        ]);
    }

}