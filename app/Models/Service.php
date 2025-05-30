<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}