<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property string $name
 * @property string $lab_endurance
 * @property string $service_endurance
 * @property int $company_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company|null $company
 * @method static \Illuminate\Database\Eloquent\Builder|Subcompany newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Subcompany newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Subcompany query()
 * @method static \Illuminate\Database\Eloquent\Builder|Subcompany whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Subcompany whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Subcompany whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Subcompany whereLabEndurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Subcompany whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Subcompany whereServiceEndurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Subcompany whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class Subcompany extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'company_id',
        'lab_endurance',
        'service_endurance',
    ];

    protected $casts = [
        'lab_endurance' => 'decimal:2',
        'service_endurance' => 'decimal:2',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
