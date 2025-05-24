<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;
    protected $fillable = [
        'name', 'lab_endurance', 'service_endurance', 'status',
        'lab_roof', 'service_roof', 'phone', 'email', 'finance_account_id',
    ];
    protected $casts = [
        'lab_endurance' => 'decimal:2',
        'service_endurance' => 'decimal:2',
        'status' => 'boolean',
    ];

    public function financeAccount() { return $this->belongsTo(FinanceAccount::class); }
    public function subcompanies() { return $this->hasMany(Subcompany::class); }
    public function companyRelations() { return $this->hasMany(CompanyRelation::class); }
    public function patients() { return $this->hasMany(Patient::class); }

    // Relationship to services through the company_service pivot table
    public function contractedServices()
    {
        return $this->belongsToMany(Service::class, 'company_service')
                    ->using(CompanyService::class) // Specify the pivot model
                    ->withPivot([ // Pivot table columns to retrieve
                        'price', 'static_endurance', 'percentage_endurance',
                        'static_wage', 'percentage_wage', 'use_static', 'approval'
                    ])
                    ->withTimestamps(); // If your company_service pivot table has timestamps (it doesn't based on schema)
    }

    // Direct hasMany relationship to the pivot model if you want to manage CompanyService records directly
    public function companyServiceEntries() {
        return $this->hasMany(CompanyService::class);
    }
    public function contractedMainTests()
{
    return $this->belongsToMany(MainTest::class, 'company_main_test', 'company_id', 'main_test_id')
                ->using(CompanyMainTest::class) // Specify the pivot model
                ->withPivot([ // Pivot table columns to retrieve
                    'id', // The pivot table's own ID
                    'status', 'price', 'approve', 'endurance_static', 
                    'endurance_percentage', 'use_static'
                ]);
                // ->withTimestamps(); // If company_main_test table has timestamps
}
// Direct relationship to pivot records if needed
public function companyMainTestEntries() {
    return $this->hasMany(CompanyMainTest::class);
}
}