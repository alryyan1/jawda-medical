<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $lab_endurance
 * @property string $service_endurance
 * @property bool $status
 * @property int $lab_roof
 * @property int $service_roof
 * @property string $phone
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $finance_account_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyMainTest> $companyMainTestEntries
 * @property-read int|null $company_main_test_entries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyRelation> $companyRelations
 * @property-read int|null $company_relations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyService> $companyServiceEntries
 * @property-read int|null $company_service_entries_count
 * @property-read \App\Models\CompanyService $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\MainTest> $contractedMainTests
 * @property-read int|null $contracted_main_tests_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Service> $contractedServices
 * @property-read int|null $contracted_services_count
 * @property-read \App\Models\FinanceAccount|null $financeAccount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Patient> $patients
 * @property-read int|null $patients_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subcompany> $subcompanies
 * @property-read int|null $subcompanies_count
 * @method static \Illuminate\Database\Eloquent\Builder|Company newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Company newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Company query()
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereFinanceAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereLabEndurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereLabRoof($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereServiceEndurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereServiceRoof($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Company whereUpdatedAt($value)
 * @mixin \Eloquent
 */
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