<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestedSurgeryFinance extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_surgery_id',
        'admission_id',
        'surgery_id',
        'finance_charge_id',
        'amount',
    ];

    public function requestedSurgery()
    {
        return $this->belongsTo(RequestedSurgery::class);
    }

    public function financeCharge()
    {
        return $this->belongsTo(SurgicalOperationCharge::class, 'finance_charge_id');
    }

    public function surgery()
    {
        return $this->belongsTo(SurgicalOperation::class, 'surgery_id');
    }
}
