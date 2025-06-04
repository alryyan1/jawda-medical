<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Doctor> $doctors
 * @property-read int|null $doctors_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RequestedServiceCost> $requestedServiceCostEntries
 * @property-read int|null $requested_service_cost_entries_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ServiceCost> $serviceCosts
 * @property-read int|null $service_costs_count
 * @method static \Illuminate\Database\Eloquent\Builder|SubServiceCost newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SubServiceCost newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|SubServiceCost query()
 * @method static \Illuminate\Database\Eloquent\Builder|SubServiceCost whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|SubServiceCost whereName($value)
 * @mixin \Eloquent
 */
class SubServiceCost extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * The table associated with the model.
     * By default, Laravel will use 'sub_service_costs'.
     * public $table = 'sub_service_costs';
     */

    /**
     * Indicates if the model should be timestamped.
     * Your schema does not have timestamps for this table.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The service costs that use this sub-cost type.
     */
    public function serviceCosts()
    {
        return $this->hasMany(ServiceCost::class);
    }

    /**
     * The doctors associated with this sub-service cost through the pivot table.
     */
    public function doctors()
    {
        return $this->belongsToMany(Doctor::class, 'doctor_service_costs');
        // If you create a DoctorServiceCost pivot model:
        // ->using(DoctorServiceCost::class)->withPivot([]);
    }

    /**
     * The instances where this sub-cost was applied to a requested service.
     */
    public function requestedServiceCostEntries()
    {
        return $this->hasMany(RequestedServiceCost::class);
    }
}