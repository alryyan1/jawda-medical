<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $package_id
 * @property string|null $package_name
 * @property string $container
 * @property int $exp_time
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MainTest> $mainTests
 * @property-read int|null $main_tests_count
 * @method static \Illuminate\Database\Eloquent\Builder|Package newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Package newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Package query()
 * @method static \Illuminate\Database\Eloquent\Builder|Package whereContainer($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Package whereExpTime($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Package wherePackageId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Package wherePackageName($value)
 * @mixin \Eloquent
 */
class Package extends Model
{
    use HasFactory;

    // If your primary key is not 'id' or not auto-incrementing, define them
    protected $primaryKey = 'package_id'; // Eloquent assumes 'id' by default
    // public $incrementing = true; // True by default for integer keys

    // If your table doesn't have timestamps (created_at, updated_at)
    public $timestamps = false; 

    protected $fillable = [
        'package_name',
        'container', // If it's a simple string field
        // 'container_id', // If you changed it to a foreign key
        'exp_time',
    ];

    protected $casts = [
        'exp_time' => 'integer',
    ];

    /**
     * Get all main tests that belong to this package.
     */
    public function mainTests()
    {
        return $this->hasMany(MainTest::class, 'pack_id', 'package_id'); // localKey is 'package_id'
    }

    /**
     * If 'container' was changed to 'container_id' and is a FK:
     * public function sampleContainer()
     * {
     *     return $this->belongsTo(Container::class, 'container_id');
     * }
     */
}