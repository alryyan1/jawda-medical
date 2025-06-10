<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * App\Models\RequestedServiceCost
 *
 * @property int $id
 * @property int $requested_service_id
 * @property int $sub_service_cost_id
 * @property int $service_cost_id
 * @property int $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\ServiceCost|null $serviceCost
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceCost newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceCost newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceCost query()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceCost whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceCost whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceCost whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceCost whereRequestedServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceCost whereServiceCostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceCost whereSubServiceCostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceCost whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class RequestedServiceCost extends Model
{

    use HasFactory;
    protected $with = ['serviceCost'];
    protected $table ='requested_service_cost';
    protected $guarded = [];
    public function serviceCost()
    {
        return $this->belongsTo(ServiceCost::class,'service_cost_id');
    }
}
