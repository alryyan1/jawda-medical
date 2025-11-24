<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubSpecialist extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'specialists_id',
    ];

    /**
     * Get the specialist that owns the sub specialist.
     */
    public function specialist(): BelongsTo
    {
        return $this->belongsTo(Specialist::class, 'specialists_id');
    }
}
