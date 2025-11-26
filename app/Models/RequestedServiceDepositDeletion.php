<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestedServiceDepositDeletion extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_service_deposit_id',
        'requested_service_id',
        'amount',
        'user_id',
        'is_bank',
        'is_claimed',
        'shift_id',
        'original_created_at',
        'deleted_by',
        'deleted_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_bank' => 'boolean',
        'is_claimed' => 'boolean',
        'original_created_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function requestedService()
    {
        return $this->belongsTo(RequestedService::class, 'requested_service_id');
    }

    public function deposit()
    {
        return $this->belongsTo(RequestedServiceDeposit::class, 'requested_service_deposit_id');
    }

    public function user()
    {
        // User who originally created the deposit
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deletedByUser()
    {
        // User who deleted/voided the deposit
        return $this->belongsTo(User::class, 'deleted_by');
    }
}


