<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $doctor_id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read Doctor $doctor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, MainTest> $mainTests
 */
class DoctorLabTestProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'doctor_id',
        'name',
    ];

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function mainTests(): BelongsToMany
    {
        return $this->belongsToMany(MainTest::class, 'doctor_lab_test_profile_main_test');
    }
}
