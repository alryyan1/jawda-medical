<?php

// app/Models/File.php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class File extends Model
{
    use HasFactory;
    // No $fillable needed if we only create it with default ID and timestamps.
    // If you add other fields like 'file_number_display' (a formatted string), add them to fillable.

    public function doctorVisits()
    {
        return $this->hasMany(DoctorVisit::class);
    }
}