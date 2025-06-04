<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Service> $services
 * @property-read int|null $services_count
 * @method static \Database\Factories\ServiceGroupFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceGroup newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceGroup newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceGroup query()
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceGroup whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|ServiceGroup whereName($value)
 * @mixin \Eloquent
 */
class ServiceGroup extends Model
{
    use HasFactory;
    protected $fillable = ['name'];
    public $timestamps = false; // As per your migration for service_groups

    public function services()
    {
        return $this->hasMany(Service::class);
    }
}