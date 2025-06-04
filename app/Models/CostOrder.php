<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
/**
 * 
 *
 * @property int $id
 * @property int $service_id
 * @property int|null $service_cost_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int $sub_service_cost_id
 * @method static \Illuminate\Database\Eloquent\Builder|CostOrder newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CostOrder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CostOrder query()
 * @method static \Illuminate\Database\Eloquent\Builder|CostOrder whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CostOrder whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CostOrder whereServiceCostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CostOrder whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CostOrder whereSubServiceCostId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CostOrder whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CostOrder extends Model
{
    use HasFactory;
    protected $table = 'cost_order';
    protected $guarded = ['id'];

}
