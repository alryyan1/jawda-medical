<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property int $service_group_id
 * @property string $price
 * @property bool $activate
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $variable
 * @property-read \App\Models\DoctorService $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company> $companies
 * @property-read int|null $companies_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Doctor> $doctorsProviding
 * @property-read int|null $doctors_providing_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ServiceCost> $serviceCosts
 * @property-read int|null $service_costs_count
 * @property-read \App\Models\ServiceGroup $serviceGroup
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ServiceCost> $service_costs
 * @method static \Database\Factories\ServiceFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Service newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Service newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Service query()
 * @method static \Illuminate\Database\Eloquent\Builder|Service whereActivate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Service whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Service whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Service whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Service wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Service whereServiceGroupId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Service whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Service whereVariable($value)
 * @mixin \Eloquent
 */
class Service extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'service_group_id',
        'price',
        'activate',
        'variable',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'activate' => 'boolean',
        'variable' => 'boolean',
    ];

    public function serviceGroup()
    {
        return $this->belongsTo(ServiceGroup::class);
    }
    public function companies() {
        return $this->belongsToMany(Company::class, 'company_service')
                    ->using(CompanyService::class)
                    ->withPivot([
                        'price', 'static_endurance', 'percentage_endurance',
                        'static_wage', 'percentage_wage', 'use_static', 'approval'
                    ]);
                    // ->withTimestamps(); // if pivot has timestamps
    }
    
    // NEWLY ADDED for the cost breakdown structure:
    /**
     * Get all defined costs associated with this service.
     */
    public function serviceCosts()
    {
        return $this->hasMany(ServiceCost::class);
    }
       /**
     * The doctors who offer this service with specific financial terms.
     */
    public function doctorsProviding()
    {
        return $this->belongsToMany(Doctor::class, 'doctor_services')
                    ->using(DoctorService::class)
                    ->withPivot(['id', 'percentage', 'fixed'])
                    ->withTimestamps();
    }
    public function service_costs()
    {
        return $this->hasMany(ServiceCost::class);
    }
}