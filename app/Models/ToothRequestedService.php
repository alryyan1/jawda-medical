<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $requested_service_id
 * @property int $tooth_id
 * @property int $doctorvisit_id
 * @method static \Illuminate\Database\Eloquent\Builder|ToothRequestedService newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ToothRequestedService newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ToothRequestedService query()
 * @method static \Illuminate\Database\Eloquent\Builder|ToothRequestedService whereDoctorvisitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ToothRequestedService whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ToothRequestedService whereRequestedServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ToothRequestedService whereToothId($value)
 * @mixin \Eloquent
 */
class ToothRequestedService extends Model
{
    use HasFactory;
}
