<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $child_id_array
 * @property string $name_in_hormone_table
 * @method static \Illuminate\Database\Eloquent\Builder|HormoneBinding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HormoneBinding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|HormoneBinding query()
 * @method static \Illuminate\Database\Eloquent\Builder|HormoneBinding whereChildIdArray($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HormoneBinding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|HormoneBinding whereNameInHormoneTable($value)
 * @mixin \Eloquent
 */
class HormoneBinding extends Model
{
    use HasFactory;
}
