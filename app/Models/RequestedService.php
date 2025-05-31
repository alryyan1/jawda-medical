<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function performingDoctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    /**
     * Get all the actual cost breakdown entries for this requested service.
     */
    public function costBreakdown()
    {
        return $this->hasMany(RequestedServiceCost::class, 'requested_service_id');
    }

    /**
     * Get all payment deposits made for this requested service.
     */
    public function deposits() // <-- THE MISSING RELATIONSHIP
    {
        return $this->hasMany(RequestedServiceDeposit::class, 'requested_service_id');
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
}