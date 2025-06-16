<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class ShiftDefinition extends Model
{
    use HasFactory;
    protected $table = 'shifts_definitions';
    protected $fillable = ['name', 'shift_label', 'start_time', 'end_time', 'is_active'];
    protected $casts = [
        'is_active' => 'boolean',
        // 'start_time' => 'datetime:H:i', // If you want Carbon instances
        // 'end_time' => 'datetime:H:i',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_default_shifts');
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    // Accessor for duration if not stored
    public function getDurationHoursAttribute(): float
    {
        if ($this->start_time && $this->end_time) {
            $start = Carbon::parse($this->start_time);
            $end = Carbon::parse($this->end_time);
            if ($end->lessThan($start)) { // Handles overnight shifts
                $end->addDay();
            }
            return round($start->diffInMinutes($end) / 60, 2);
        }
        return 0;
    }
}