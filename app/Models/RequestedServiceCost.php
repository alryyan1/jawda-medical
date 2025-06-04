<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $requested_service_id
 * @property int $sub_service_cost_id
 * @property int $service_cost_id
 * @property string $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\RequestedService $requestedService
 * @property-read \App\Models\ServiceCost|null $serviceCostDefinition
 * @property-read \App\Models\SubServiceCost $subServiceCost
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

    protected $table = 'requested_service_cost'; // Explicitly define table name

    protected $fillable = [
        'requested_service_id',
        'sub_service_cost_id',
        'service_cost_id', // The ID of the ServiceCost definition used
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * The requested service this cost breakdown belongs to.
     */
    public function requestedService()
    {
        return $this->belongsTo(RequestedService::class);
    }

    /**
     * The sub-service cost type applied.
     */
    public function subServiceCost()
    {
        return $this->belongsTo(SubServiceCost::class);
    }

    /**
     * The general service cost definition that was instantiated here.
     */
    public function serviceCostDefinition() // Renamed to avoid conflict with model name
    {
        return $this->belongsTo(ServiceCost::class, 'service_cost_id');
    }
}