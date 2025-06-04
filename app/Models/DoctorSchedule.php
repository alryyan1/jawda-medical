<?php // app/Models/DoctorSchedule.php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $doctor_id
 * @property int $day_of_week
 * @property string $time_slot
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Doctor $doctor
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorSchedule newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorSchedule newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorSchedule query()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorSchedule whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorSchedule whereDayOfWeek($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorSchedule whereDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorSchedule whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorSchedule whereTimeSlot($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorSchedule whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class DoctorSchedule extends Model {
    use HasFactory;
    protected $fillable = ['doctor_id', 'day_of_week', 'time_slot', /* 'start_time', 'end_time' */ ];
    protected $casts = [ 'day_of_week' => 'integer' ];
    public function doctor() { return $this->belongsTo(Doctor::class); }
}