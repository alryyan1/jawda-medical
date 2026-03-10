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
     * Get the admissions for the room (via beds; admissions table has no room_id).
     */
    public function admissions()
    {
        return $this->hasManyThrough(Admission::class, Bed::class, 'room_id', 'bed_id');
    }

    /**
     * Get the current active admission for the room (via beds).
     */
    public function currentAdmission()
    {
        return $this->hasOneThrough(Admission::class, Bed::class, 'room_id', 'bed_id')
            ->where('admissions.status', 'admitted')
            ->latest('admissions.admission_date');
    }
}
