<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 *
 *
 * @property int $id
 * @property string $name
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PartyServiceCost> $partyServiceCosts
 * @property-read int|null $party_service_costs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Service> $services
 * @property-read int|null $services_count
 * @method static \Illuminate\Database\Eloquent\Builder|Party newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Party newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Party query()
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Party whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Party extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    public function partyServiceCosts()
    {
        return $this->hasMany(PartyServiceCost::class);
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'party_service_costs')
                    ->using(PartyServiceCost::class)
                    ->withPivot(['id', 'price'])
                    ->withTimestamps();
    }
}
