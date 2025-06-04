<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $child_id_array
 * @property string $name_in_sysmex_table
 * @method static \Illuminate\Database\Eloquent\Builder|CbcBinding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CbcBinding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CbcBinding query()
 * @method static \Illuminate\Database\Eloquent\Builder|CbcBinding whereChildIdArray($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CbcBinding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CbcBinding whereNameInSysmexTable($value)
 * @mixin \Eloquent
 */
class CbcBinding extends Model
{
    use HasFactory;
}
