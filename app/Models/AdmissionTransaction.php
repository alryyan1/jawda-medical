<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'type',
        'amount',
        'description',
        'reference_type',
        'reference_id',
        'is_bank',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_bank' => 'boolean',
    ];

    /**
     * Get the admission that owns the transaction.
     */
    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * Get the user who created the transaction.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the related service if reference_type is 'service'.
     */
    public function service()
    {
        return $this->morphTo('reference', 'reference_type', 'reference_id');
    }

    /**
     * Scope a query to only include credit transactions.
     */
    public function scopeCredits($query)
    {
        return $query->where('type', 'credit');
    }

    /**
     * Scope a query to only include debit transactions.
     */
    public function scopeDebits($query)
    {
        return $query->where('type', 'debit');
    }
}

