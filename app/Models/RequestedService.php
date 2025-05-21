<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestedService extends Model
{
    use HasFactory;

    protected $table = 'requested_services'; // Explicitly define if not following plural convention perfectly

    protected $fillable = [
        'doctorvisits_id', // Or 'doctor_visit_id' if you changed it
        'service_id',
        'user_id',          // User who added/requested
        'user_deposited',   // User who confirmed payment
        'doctor_id',        // Doctor performing/responsible for this service item
        'price',
        'amount_paid',
        'endurance',
        'is_paid',
        'discount',
        'discount_per',
        'bank',
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
        'discount' => 'decimal:2', // If fixed discount can have decimals
        'discount_per' => 'integer',
        'bank' => 'boolean',
        'count' => 'integer',
        'done' => 'boolean',
        'approval' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    /**
     * Get the doctor visit this requested service belongs to.
     * Adjust 'doctorvisits_id' if your FK column is named 'doctor_visit_id'.
     */
    public function doctorVisit()
    {
        return $this->belongsTo(DoctorVisit::class, 'doctorvisits_id'); // Or 'doctor_visit_id'
    }

    /**
     * Get the service that was requested.
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * Get the user who requested/added this service item.
     */
    public function requestingUser() // Renamed to avoid conflict with 'user' if that's patient
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the user who handled the deposit/payment for this service.
     */
    public function depositUser() // Or paymentConfirmedByUser
    {
        return $this->belongsTo(User::class, 'user_deposited');
    }

    /**
     * Get the doctor specifically associated with this service instance.
     */
    public function performingDoctor() // Renamed to avoid conflict if 'doctor' is the main visit doctor
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    // Accessors for calculated values (Examples)

    /**
     * Calculate the total price for this line item (price * count).
     */
    public function getTotalPriceAttribute(): float
    {
        return (float) $this->price * (int) $this->count;
    }

    /**
     * Calculate the net amount due after discount and endurance.
     * Net Due = (Price * Count) - Discount Amount - Endurance Amount
     */
    public function getNetAmountDueAttribute(): float
    {
        $totalPrice = $this->total_price; // Uses the accessor above
        $discountAmount = $this->discount; // Assuming 'discount' is a fixed amount
        
        // If 'discount_per' is used and 'discount' stores the calculated amount:
        // $discountAmount = ($totalPrice * $this->discount_per) / 100; 
        // OR if 'discount' is a fixed amount and 'discount_per' is additional, adjust logic.
        // For now, assuming 'discount' is the primary discount value.

        return $totalPrice - $discountAmount - (float) $this->endurance;
    }

    /**
     * Calculate the remaining balance for this service.
     */
    public function getBalanceAttribute(): float
    {
        return $this->net_amount_due - (float) $this->amount_paid;
    }
}