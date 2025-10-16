<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankakImage extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'image_url',
        'doctorvisit_id',
        'phone',
    ];

    /**
     * Get the doctor visit that owns the image.
     */
    public function doctorvisit(): BelongsTo
    {
        return $this->belongsTo(Doctorvisit::class);
    }
}
