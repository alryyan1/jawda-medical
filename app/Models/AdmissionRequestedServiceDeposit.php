<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionRequestedServiceDeposit extends Model
{
    use HasFactory;

    protected $table = 'admission_requested_service_deposits';

    protected $fillable = [
        'admission_requested_service_id',
        'user_id',
        'amount',
        'is_bank',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_bank' => 'boolean',
    ];

    public function admissionRequestedService()
    {
        return $this->belongsTo(AdmissionRequestedService::class, 'admission_requested_service_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
