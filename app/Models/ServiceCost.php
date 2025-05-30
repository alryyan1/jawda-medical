<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ServiceCost extends Model
{
    use HasFactory;

    protected $table = 'service_cost'; // Explicitly define table name

    protected $fillable = [
        'name',
        'service_id',
        'percentage',
        'fixed',
        'cost_type',
        'sub_service_cost_id',
    ];

    protected $casts = [
        'percentage' => 'decimal:2',
        'fixed' => 'decimal:2',
        // 'cost_type' will be handled as string by default, which is fine for enums.
    ];

    /**
     * Indicates if the model should be timestamped.
     * Your schema does not have timestamps for this table.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * The service this cost definition belongs to.
     */
    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * The sub-service cost type this definition relates to.
     */
    public function subServiceCost()
    {
        return $this->belongsTo(SubServiceCost::class);
    }

    /**
     * The instances where this defined cost was applied.
     */
    public function requestedServiceCostEntries()
    {
        return $this->hasMany(RequestedServiceCost::class);
    }
}