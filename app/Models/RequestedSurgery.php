<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestedSurgery extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'surgery_id',
        'price',
        'doctor_id',
        'user_id',
    ];

    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    public function surgery()
    {
        return $this->belongsTo(SurgicalOperation::class, 'surgery_id');
    }

    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function finances()
    {
        return $this->hasMany(RequestedSurgeryFinance::class);
    }
}
