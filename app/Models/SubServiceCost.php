<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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