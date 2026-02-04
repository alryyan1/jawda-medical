<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShortStayBed extends Model
{
    use HasFactory;

    protected $fillable = [
        'bed_number',
        'price_12h',
        'price_24h',
        'status',
        'notes',
    ];

    protected $casts = [
        'price_12h' => 'decimal:2',
        'price_24h' => 'decimal:2',
    ];

    /**
     * Get the admissions for this short stay bed.
     */
    public function admissions()
    {
        return $this->hasMany(Admission::class, 'short_stay_bed_id');
    }

    /**
     * Get the price based on duration.
     */
    public function getPriceForDuration(string $duration): float
    {
        return match($duration) {
            '12h' => (float) $this->price_12h,
            '24h' => (float) $this->price_24h,
            default => 0.00,
        };
    }
}
