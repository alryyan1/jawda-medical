<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ward extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Get the rooms for the ward.
     */
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Get the admissions for the ward.
     */
    public function admissions()
    {
        return $this->hasMany(Admission::class);
    }
}
