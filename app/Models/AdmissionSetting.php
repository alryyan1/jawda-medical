<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'morning_start',
        'morning_end',
        'evening_start',
        'evening_end',
        'full_day_boundary',
        'default_period_start',
        'default_period_end',
    ];

    /**
     * Helper to get the current (only) settings record.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'morning_start' => '07:00:00',
            'morning_end' => '12:00:00',
            'evening_start' => '13:00:00',
            'evening_end' => '06:00:00',
            'full_day_boundary' => '12:00:00',
            'default_period_start' => '06:00:00',
            'default_period_end' => '07:00:00',
        ]);
    }
}

