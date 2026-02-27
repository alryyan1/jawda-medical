<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DiscountLabRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'patient_id',
        'is_approved',
    ];

    public function patient()
    {
        return $this->belongsTo(Patient::class);
    }
}
