<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestedSurgeryTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_surgery_id',
        'type',
        'payment_method',
        'amount',
        'description',
        'notes',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function requestedSurgery()
    {
        return $this->belongsTo(RequestedSurgery::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
