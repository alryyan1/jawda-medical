<?php // app/Models/DoctorSchedule.php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DoctorSchedule extends Model {
    use HasFactory;
    protected $fillable = ['doctor_id', 'day_of_week', 'time_slot', /* 'start_time', 'end_time' */ ];
    protected $casts = [ 'day_of_week' => 'integer' ];
    public function doctor() { return $this->belongsTo(Doctor::class); }
}