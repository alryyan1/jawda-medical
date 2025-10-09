<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $main_test_name
 * @property int|null $pack_id
 * @property bool $pageBreak
 * @property int $container_id
 * @property float|null $price
 * @property bool $divided
 * @property bool $available
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ChildTest> $childTests
 * @property-read int|null $child_tests_count
 * @property-read \App\Models\CompanyMainTest $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company> $companies
 * @property-read int|null $companies_count
 * @property-read \App\Models\Container $container
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LabRequest> $labRequests
 * @property-read int|null $lab_requests_count
 * @property-read \App\Models\Package|null $package
 * @method static \Illuminate\Database\Eloquent\Builder|MainTest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MainTest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|MainTest query()
 * @method static \Illuminate\Database\Eloquent\Builder|MainTest whereAvailable($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MainTest whereContainerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MainTest whereDivided($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MainTest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MainTest whereMainTestName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MainTest wherePackId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MainTest wherePageBreak($value)
 * @method static \Illuminate\Database\Eloquent\Builder|MainTest wherePrice($value)
 * @mixin \Eloquent
 */
class MainTest extends Model
{
    use HasFactory;
    protected $fillable = [
        'main_test_name',
        'pack_id', // Assuming integer for now
        'pageBreak',
        'container_id',
        'price',
        'divided', // Is the test result divided into sub-components/calculations?
        'available', // Is the test currently offered?
        'is_special_test',
    ];

    protected $casts = [
        'pageBreak' => 'boolean',
        // 'price' => 'decimal:1', // As per schema double(10,1)
        'divided' => 'boolean',
        'available' => 'boolean',
        'is_special_test' => 'boolean',
    ];

    // Timestamps are NOT in your main_tests schema by default. If you added them:
    // public $timestamps = true; 
    // If not, then:
    public $timestamps = false;


    public function container()
    {
        return $this->belongsTo(Container::class);
    }

    // If pack_id refers to a 'Packs' table/model:
    // public function pack()
    // {
    //    return $this->belongsTo(Pack::class);
    // }
    

    public function childTests()
    {
        return $this->hasMany(ChildTest::class);
    }

    public function labRequests()
    {
        return $this->hasMany(LabRequest::class);
    }
    public function package()
    {
        return $this->belongsTo(Package::class);
    }
    public function companies()
    {
        return $this->belongsToMany(Company::class, 'company_main_test', 'main_test_id', 'company_id')
            ->using(CompanyMainTest::class)
            ->withPivot([
                'id',
                'status',
                'price',
                'approve',
                'endurance_static',
                'endurance_percentage',
                'use_static'
            ]);
    }
}
