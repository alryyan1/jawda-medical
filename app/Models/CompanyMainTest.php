<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * 
 *
 * @property int $id
 * @property int $main_test_id
 * @property int $company_id
 * @property bool $status
 * @property string $price
 * @property bool $approve
 * @property int $endurance_static
 * @property string $endurance_percentage
 * @property bool $use_static
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\MainTest $mainTest
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest query()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest whereApprove($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest whereEndurancePercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest whereEnduranceStatic($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest whereMainTestId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyMainTest whereUseStatic($value)
 * @mixin \Eloquent
 */
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