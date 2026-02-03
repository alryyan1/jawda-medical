<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'ward_id',
        'room_number',
        'room_type',
        'capacity',
        'status',
        'price_per_day',
    ];

    protected $casts = [
        'capacity' => 'integer',
        'status' => 'boolean',
        'price_per_day' => 'decimal:2',
    ];

    /**
     * Get the ward that owns the room.
     */
    public function ward()
    {
        return $this->belongsTo(Ward::class);
    }

    /**
     * Get the beds for the room.
     */
    public function beds()
    {
        return $this->hasMany(Bed::class);
    }

    /**
     * Get the admissions for the room.
     */
    public function admissions()
    {
        return $this->hasMany(Admission::class);
    }

    /**
     * Get the current active admission for the room (room booking type only).
     */
    public function currentAdmission()
    {
        return $this->hasOne(Admission::class)
            ->where('status', 'admitted')
            ->where('booking_type', 'room')
            ->latest('admission_date');
    }
}
