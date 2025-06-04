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
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Patient> $patients
 * @property-read int|null $patients_count
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyRelation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyRelation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyRelation query()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyRelation whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyRelation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyRelation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyRelation whereLabEndurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyRelation whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyRelation whereServiceEndurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyRelation whereUpdatedAt($value)
 * @mixin \Eloquent
 */
class CompanyRelation extends Model
{
    use HasFactory;

    // If table name is different from Laravel's convention (company_relations)
    // protected $table = 'company_relations';

    protected $fillable = [
        'company_id', // Crucial: links this relation type to a specific company
        'name',
        'lab_endurance',
        'service_endurance',
    ];

    protected $casts = [
        'lab_endurance' => 'decimal:2',
        'service_endurance' => 'decimal:2',
    ];

    /**
     * Get the company this relation type belongs to.
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get the patients who have this specific company relation.
     */
    public function patients()
    {
        return $this->hasMany(Patient::class);
    }
}