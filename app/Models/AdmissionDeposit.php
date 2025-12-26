<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionDeposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'amount',
        'is_bank',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_bank' => 'boolean',
    ];

    /**
     * Get the admission that owns the deposit.
     */
    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * Get the user who created the deposit.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

