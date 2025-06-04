<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot; // Important: extends Pivot

/**
 * 
 *
 * @property int $id
 * @property int $service_id
 * @property int $company_id
 * @property string $price
 * @property string $static_endurance
 * @property string $percentage_endurance
 * @property string $static_wage
 * @property string $percentage_wage
 * @property bool $use_static
 * @property bool $approval
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Service $service
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService query()
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService whereApproval($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService wherePercentageEndurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService wherePercentageWage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService whereServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService whereStaticEndurance($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService whereStaticWage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|CompanyService whereUseStatic($value)
 * @mixin \Eloquent
 */
class CompanyService extends Pivot
{
    // If your pivot table doesn't follow Laravel's naming convention (company_service),
    // you might need to specify the table name:
    // protected $table = 'company_service';

    // Laravel by default assumes a pivot model does not have an incrementing ID.
    // If your `company_service` table has an `id` (it does based on your schema),
    // and you want to treat it as a regular model in some contexts:
    // public $incrementing = true; // Uncomment if you want to use the 'id' as primary key

    // If it has an 'id' and you want it to be findable by that ID,
    // you might consider making it extend Model instead of Pivot,
    // but then belongsToMany relationships need manual setup for pivot attributes.
    // For now, extending Pivot is fine for use with belongsToMany->using().

    protected $fillable = [
        'company_id', // Not usually in fillable for pivot, but good if creating directly
        'service_id', // Not usually in fillable for pivot
        'price',
        'static_endurance',
        'percentage_endurance',
        'static_wage',
        'percentage_wage',
        'use_static',
        'approval'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'static_endurance' => 'decimal:2',
        'percentage_endurance' => 'decimal:2',
        'static_wage' => 'decimal:2',
        'percentage_wage' => 'decimal:2',
        'use_static' => 'boolean',
        'approval' => 'boolean',
    ];

    // Relationships from the pivot model itself (optional but can be useful)
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}