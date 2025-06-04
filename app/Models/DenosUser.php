<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $user_id
 * @property int $shift_id
 * @property int $deno_id
 * @property int $amount
 * @method static \Illuminate\Database\Eloquent\Builder|DenosUser newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DenosUser newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|DenosUser query()
 * @method static \Illuminate\Database\Eloquent\Builder|DenosUser whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DenosUser whereDenoId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DenosUser whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DenosUser whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|DenosUser whereUserId($value)
 * @mixin \Eloquent
 */
class DenosUser extends Model
{
    use HasFactory;
}
