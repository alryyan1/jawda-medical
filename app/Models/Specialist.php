<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialist extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    // No timestamps defined in the specialists migration, so:
    public $timestamps = false;

    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }
}