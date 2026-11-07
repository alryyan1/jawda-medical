<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property int|null $service_group_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Cost> $costs
 * @property-read int|null $costs_count
 * @property-read \App\Models\ServiceGroup|null $serviceGroup
 *
 * @method static \Illuminate\Database\Eloquent\Builder|CostCategory newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CostCategory newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CostCategory query()
 * @method static \Illuminate\Database\Eloquent\Builder|CostCategory whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CostCategory whereName($value)
 *
 * @mixin \Eloquent
 */
class CostCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'service_group_id'];

    // Your 'cost_categories' table schema does not have timestamps by default
    public $timestamps = false;

    /**
     * Get the costs associated with this category.
     */
    public function costs()
    {
        return $this->hasMany(Cost::class);
    }

    public function serviceGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ServiceGroup::class);
    }
}
