<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $doctor_id
 * @property int $sub_service_cost_id
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorServiceCost newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorServiceCost newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorServiceCost query()
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorServiceCost whereDoctorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorServiceCost whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DoctorServiceCost whereSubServiceCostId($value)
 * @mixin \Eloquent
 */
class DoctorServiceCost extends Model
{
    use HasFactory;
}
