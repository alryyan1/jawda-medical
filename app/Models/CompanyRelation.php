<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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