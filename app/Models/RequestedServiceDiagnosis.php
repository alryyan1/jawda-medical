<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestedServiceDiagnosis extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_service_id',
        'user_id',
        'diagnosis',
        'complete',
        'completed_at',
        'is_printed',
        'printed_by_user_id',
    ];

    protected $casts = [
        'complete'     => 'boolean',
        'completed_at' => 'datetime',
        'is_printed'   => 'boolean',
    ];

    public function requestedService()
    {
        return $this->belongsTo(RequestedService::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function printedByUser()
    {
        return $this->belongsTo(User::class, 'printed_by_user_id');
    }
}
