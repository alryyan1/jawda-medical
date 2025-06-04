<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $route_id
 * @property string $name
 * @property string $path
 * @property string $icon
 * @method static \Illuminate\Database\Eloquent\Builder|SubRoute newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SubRoute newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SubRoute query()
 * @method static \Illuminate\Database\Eloquent\Builder|SubRoute whereIcon($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SubRoute whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SubRoute whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SubRoute wherePath($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SubRoute whereRouteId($value)
 * @mixin \Eloquent
 */
class SubRoute extends Model
{
    use HasFactory;
}
