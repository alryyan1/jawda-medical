<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot; // Use Pivot for custom pivot models
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * 
 *
 * @property int $id
 * @property int $doctor_id
 * @property int $service_id
 * @property string $percentage
 * @property string $fixed
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property-read \App\Models\Doctor $doctor
 * @property-read \App\Models\Service $service
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorService newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorService newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorService query()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorService whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorService whereDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorService whereFixed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorService whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorService wherePercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorService whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorService whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DoctorService extends Pivot // Extend Pivot
{
    use HasFactory;

    protected $table = 'doctor_services';

    protected $fillable = [
        'doctor_id',
        'service_id',
        'percentage',
        'fixed',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'fixed' => 'decimal:2',
    ];
    public $timestamps = false;  

    // You can define relationships back to Doctor and Service if needed from the pivot instance
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}