<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $child_test_name
 * @property float|null $low
 * @property float|null $upper
 * @property int $main_test_id
 * @property string $defval
 * @property int|null $unit_id
 * @property string $normalRange
 * @property string|null $max
 * @property string|null $lowest
 * @property int|null $test_order
 * @property int|null $child_group_id
 * @property-read \App\Models\ChildGroup|null $childGroup
 * @property-read \App\Models\MainTest|null $mainTest
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChildTestOption> $options
 * @property-read int|null $options_count
 * @property-read \App\Models\Unit|null $unit
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest query()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereChildGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereChildTestName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereDefval($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereLow($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereLowest($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereMainTestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereMax($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereNormalRange($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereTestOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereUnitId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTest whereUpper($value)
 * @mixin \Eloquent
 */
class ChildTest extends Model
{
    use HasFactory;
    protected $fillable = [
        'main_test_id',
        'child_test_name',
        'low',
        'upper',
        'defval',
        'unit_id',
        'normalRange', // Stored as text
        'max',
        'lowest',
        'test_order',
        'child_group_id',
        'json_params',
    ];

    protected $casts = [
        'low' => 'float', // Or decimal if you used that in migration
        'upper' => 'float',
        'max' => 'decimal:2',
        'lowest' => 'decimal:2',
        'test_order' => 'integer',
        'json_params' => 'array',
    ];
    
    public $timestamps = false; // As per your child_tests migration (no timestamps)

    public function mainTest()
    {
        return $this->belongsTo(MainTest::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class); // Assuming Unit model exists
    }

    public function childGroup()
    {
        return $this->belongsTo(ChildGroup::class); // Assuming ChildGroup model exists
    }

    public function options() // child_test_options
    {
        return $this->hasMany(ChildTestOption::class);
    }
}