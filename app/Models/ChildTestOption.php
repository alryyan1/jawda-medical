<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property int $child_test_id
 * @property-read \App\Models\ChildTest $childTest
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestOption newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestOption newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestOption query()
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestOption whereChildTestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestOption whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ChildTestOption whereName($value)
 * @mixin \Eloquent
 */
class ChildTestOption extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'child_test_id',
    ];
    public $timestamps = false;
    public function childTest()
    {
        return $this->belongsTo(ChildTest::class);
    }
}
