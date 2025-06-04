<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $container_name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MainTest> $mainTests
 * @property-read int|null $main_tests_count
 * @method static \Illuminate\Database\Eloquent\Builder|Container newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Container newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Container query()
 * @method static \Illuminate\Database\Eloquent\Builder|Container whereContainerName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Container whereId($value)
 * @mixin \Eloquent
 */
class Container extends Model
{
    use HasFactory;
    protected $fillable = ['container_name'];
    public $timestamps = false; // As per your migration for containers

    public function mainTests()
    {
        return $this->hasMany(MainTest::class);
    }
}