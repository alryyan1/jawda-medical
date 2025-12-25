<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Bed extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'bed_number',
        'status',
    ];

    /**
     * Get the room that owns the bed.
     */
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the admissions for the bed.
     */
    public function admissions()
    {
        return $this->hasMany(Admission::class);
    }

    /**
     * Get the current active admission for the bed.
     */
    public function currentAdmission()
    {
        return $this->hasOne(Admission::class)
                    ->where('status', 'admitted')
                    ->latest();
    }

    /**
     * Check if bed is available.
     */
    public function isAvailable(): bool
    {
        if ($this->status !== 'available') {
            return false;
        }
        
        $activeAdmission = $this->admissions()
            ->where('status', 'admitted')
            ->exists();
            
        return !$activeAdmission;
    }
}
