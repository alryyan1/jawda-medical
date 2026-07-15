<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 *
 *
 * @property int $id
 * @property int $service_id
 * @property int $party_id
 * @property string $price
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Party $party
 * @property-read \App\Models\Service $service
 * @method static \Illuminate\Database\Eloquent\Builder|PartyServiceCost newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartyServiceCost newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|PartyServiceCost query()
 * @method static \Illuminate\Database\Eloquent\Builder|PartyServiceCost whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyServiceCost whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyServiceCost wherePartyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyServiceCost wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyServiceCost whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|PartyServiceCost whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class PartyServiceCost extends Pivot
{
    protected $table = 'party_service_costs';

    public $incrementing = true;

    protected $fillable = [
        'service_id',
        'party_id',
        'price',
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function party()
    {
        return $this->belongsTo(Party::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
