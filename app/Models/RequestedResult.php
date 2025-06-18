<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Import for type hinting

class RequestedResult extends Model
{
    use HasFactory;

    protected $table = 'requested_results';

    protected $fillable = [
        'lab_request_id',
        'patient_id',
        'main_test_id',
        'child_test_id',
        'result',
        'normal_range',         // Snapshot of the normal range
        'unit_id',              // Snapshot of the unit ID
        // New fields added:
        'flags',
        'result_comment',
        'entered_by_user_id',
        'entered_at',
        'authorized_by_user_id',
        'authorized_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'entered_at' => 'datetime',      // Cast to Carbon instance
        'authorized_at' => 'datetime',   // Cast to Carbon instance
    ];

    public function labRequest(): BelongsTo
    {
        return $this->belongsTo(LabRequest::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function mainTest(): BelongsTo
    {
        return $this->belongsTo(MainTest::class);
    }

    public function childTest(): BelongsTo
    {
        return $this->belongsTo(ChildTest::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    // New Relationships:
    public function enteredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'entered_by_user_id');
    }

    public function authorizedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'authorized_by_user_id');
    }
}