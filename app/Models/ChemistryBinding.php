<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string|null $child_id_array
 * @property string $name_in_mindray_table
 * @method static \Illuminate\Database\Eloquent\Builder|ChemistryBinding newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChemistryBinding newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChemistryBinding query()
 * @method static \Illuminate\Database\Eloquent\Builder|ChemistryBinding whereChildIdArray($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChemistryBinding whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChemistryBinding whereNameInMindrayTable($value)
 * @mixin \Eloquent
 */
class ChemistryBinding extends Model
{
    use HasFactory;
}
