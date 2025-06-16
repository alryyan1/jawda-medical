<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', 'shift_definition_id', 'attendance_date', 'status',
        'check_in_time', 'check_out_time', 'supervisor_id', 'notes', 'recorded_by_user_id'
    ];
    protected $casts = [
        'attendance_date' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shiftDefinition()
    {
        return $this->belongsTo(ShiftDefinition::class);
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function recorder() // User who recorded the attendance
    {
        return $this->belongsTo(User::class, 'recorded_by_user_id');
    }
}