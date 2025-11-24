<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string|null $created_at
 * @property string|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Doctor> $doctors
 * @property-read int|null $doctors_count
 * @method static \Database\Factories\SpecialistFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Specialist newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Specialist newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Specialist query()
 * @method static \Illuminate\Database\Eloquent\Builder|Specialist whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Specialist whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Specialist whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Specialist whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Specialist extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'firestore_id'];

    // No timestamps defined in the specialists migration, so:
    public $timestamps = false;

    public function doctors()
    {
        return $this->hasMany(Doctor::class);
    }

    public function subSpecialists()
    {
        return $this->hasMany(SubSpecialist::class, 'specialists_id');
    }
}