<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurgicalOperation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
    ];

    public function charges()
    {
        return $this->hasMany(SurgicalOperationCharge::class);
    }
}
