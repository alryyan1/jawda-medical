<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CompanyMainTest extends Pivot
{
    protected $table = 'company_main_test'; // Explicitly define table name

    public $incrementing = true; // Since your pivot table has an 'id' PK
    // public $timestamps = false; // If your company_main_test table has no created_at/updated_at

    protected $fillable = [
        'company_id', // Not typically in pivot fillable, but useful if creating records directly
        'main_test_id',
        'status',
        'price',
        'approve',
        'endurance_static',
        'endurance_percentage',
        'use_static',
    ];

    protected $casts = [
        'status' => 'boolean',
        'price' => 'decimal:2',
        'approve' => 'boolean',
        'endurance_static' => 'integer', // Or decimal if it can have cents
        'endurance_percentage' => 'decimal:2',
        'use_static' => 'boolean',
    ];

    public function company() { return $this->belongsTo(Company::class); }
    public function mainTest() { return $this->belongsTo(MainTest::class); }
}