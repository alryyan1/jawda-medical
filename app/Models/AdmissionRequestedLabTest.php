<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionRequestedLabTest extends Model
{
    use HasFactory;

    protected $table = 'admission_requested_lab_tests';

    protected $fillable = [
        'admission_id',
        'main_test_id',
        'user_id',
        'doctor_id',
        'price',
        'discount',
        'discount_per',
        'done',
        'approval',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'discount' => 'decimal:2',
        'discount_per' => 'integer',
        'done' => 'boolean',
        'approval' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Relationships

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function mainTest()
    {
        return $this->belongsTo(MainTest::class, 'main_test_id');
    }

    public function requestingUser()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function performingDoctor()
    {
        return $this->belongsTo(Doctor::class, 'doctor_id');
    }

    // Accessors

    /**
     * Calculates the net amount payable by the patient after discounts.
     */
    public function getNetPayableByPatientAttribute(): float
    {
        $price = (float) $this->price;

        $discountAmountFixed = (float) $this->discount;
        $discountAmountPercentage = ($price * (int)($this->discount_per ?? 0)) / 100;
        $totalDiscount = $discountAmountFixed + $discountAmountPercentage;

        return $price - $totalDiscount;
    }

    public function getBalanceAttribute(): float
    {
        return $this->net_payable_by_patient;
    }
}

