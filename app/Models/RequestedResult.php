<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $lab_request_id
 * @property int $patient_id
 * @property int $main_test_id
 * @property int $child_test_id
 * @property string $result
 * @property string $normal_range
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\User|null $authorizedBy
 * @property-read \App\Models\ChildTest|null $childTest
 * @property-read \App\Models\User|null $enteredBy
 * @property-read \App\Models\LabRequest $labRequest
 * @property-read \App\Models\MainTest $mainTest
 * @property-read \App\Models\Patient $patient
 * @property-read \App\Models\Unit|null $unit
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult query()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult whereChildTestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult whereLabRequestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult whereMainTestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult whereNormalRange($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult wherePatientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult whereResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedResult whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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