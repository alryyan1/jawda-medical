<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];

    protected $casts = [
        'low' => 'float', // Or decimal if you used that in migration
        'upper' => 'float',
        'max' => 'decimal:2',
        'lowest' => 'decimal:2',
        'test_order' => 'integer',
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