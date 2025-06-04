<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $child_test_id
 * @property int $device_id
 * @property string $normal_range
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestDevice newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestDevice newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestDevice query()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestDevice whereChildTestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestDevice whereDeviceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestDevice whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestDevice whereNormalRange($value)
 * @mixin \Eloquent
 */
class ChildTestDevice extends Model
{
    use HasFactory;
}
