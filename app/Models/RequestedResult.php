<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestedResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'lab_request_id',
        'patient_id',
        'main_test_id',
        'child_test_id',
        'result',
        'normal_range', // Normal range text as it was at time of request/result entry
        'unit_id',    // Unit name as it was at time of request/result entry
        'flags',        // e.g., 'H', 'L', 'C' for High, Low, Critical
        'result_comment',
        'entered_by_user_id',
        'entered_at',
        'authorized_by_user_id',
        'authorized_at',
        // 'device_id', // If result came from a specific device
        // 'raw_instrument_value', // Optional
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'authorized_at' => 'datetime',
    ];

    public function labRequest() { return $this->belongsTo(LabRequest::class); }
    public function patient() { return $this->belongsTo(Patient::class, 'patient_id'); } // Assuming patient_id is the FK
    public function mainTest() { return $this->belongsTo(MainTest::class); }
    public function childTest() { return $this->belongsTo(ChildTest::class); }
    public function enteredBy() { return $this->belongsTo(User::class, 'entered_by_user_id'); }
    public function authorizedBy() { return $this->belongsTo(User::class, 'authorized_by_user_id'); }
    public function unit() { return $this->belongsTo(Unit::class); }
}