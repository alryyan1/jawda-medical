<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'pageBreak' => 'boolean',
        // 'price' => 'decimal:1', // As per schema double(10,1)
        'divided' => 'boolean',
        'available' => 'boolean',
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
