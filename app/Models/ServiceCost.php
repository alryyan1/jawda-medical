<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property int $service_id
 * @property string $percentage
 * @property string $fixed
 * @property string $cost_type
 * @property int $sub_service_cost_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CostOrder> $costOrders
 * @property-read int|null $cost_orders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RequestedServiceCost> $requestedServiceCostEntries
 * @property-read int|null $requested_service_cost_entries_count
 * @property-read \App\Models\Service $service
 * @property-read \App\Models\SubServiceCost|null $subServiceCost
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceCost newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceCost newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceCost query()
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceCost whereCostType($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceCost whereFixed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceCost whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceCost whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceCost wherePercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceCost whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceCost whereSubServiceCostId($value)
 * @mixin \Eloquent
 */
class ServiceCost extends Model
{
    use HasFactory;

    protected $table = 'service_cost'; // Explicitly define table name

    protected $fillable = [
        'service_id',
        'percentage',
        'fixed',
        'cost_type',
        'sub_service_cost_id',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'fixed' => 'decimal:2',
        // 'cost_type' will be handled as string by default, which is fine for enums.
    ];

    /**
     * Indicates if the model should be timestamped.
     * Your schema does not have timestamps for this table.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The service this cost definition belongs to.
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The sub-service cost type this definition relates to.
     */
    public function subServiceCost()
    {
        return $this->belongsTo(SubServiceCost::class);
    }

    /**
     * The instances where this defined cost was applied.
     */
    public function requestedServiceCostEntries()
    {
        return $this->hasMany(RequestedServiceCost::class);
    }
    public function costOrders(){
        return $this->hasMany(CostOrder::class,'service_cost_id');
    }
}