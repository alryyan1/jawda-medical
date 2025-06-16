<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceSetting extends Model
{
    use HasFactory;
    protected $fillable = ['number_of_shifts_per_day'];
    protected $casts = ['number_of_shifts_per_day' => 'integer'];

    public static function current(): self // Helper to get the current (only) setting
    {
        return static::firstOrCreate([], ['number_of_shifts_per_day' => 2]); // Default to 2 if none exists
    }
}