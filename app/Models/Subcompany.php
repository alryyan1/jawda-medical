<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subcompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company_id',
        'lab_endurance',
        'service_endurance',
    ];

    protected $casts = [
        'lab_endurance' => 'decimal:2',
        'service_endurance' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
