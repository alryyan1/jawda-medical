<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot; // Use Pivot for custom pivot models
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DoctorService extends Pivot // Extend Pivot
{
    use HasFactory;

    protected $table = 'doctor_services';

    protected $fillable = [
        'doctor_id',
        'service_id',
        'percentage',
        'fixed',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'fixed' => 'decimal:2',
    ];
    public $timestamps = false;  

    // You can define relationships back to Doctor and Service if needed from the pivot instance
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}