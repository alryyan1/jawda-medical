<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestedServiceCost extends Model
{
    use HasFactory;

    protected $table = 'requested_service_cost'; // Explicitly define table name

    protected $fillable = [
        'requested_service_id',
        'sub_service_cost_id',
        'service_cost_id', // The ID of the ServiceCost definition used
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    /**
     * The requested service this cost breakdown belongs to.
     */
    public function requestedService()
    {
        return $this->belongsTo(RequestedService::class);
    }

    /**
     * The sub-service cost type applied.
     */
    public function subServiceCost()
    {
        return $this->belongsTo(SubServiceCost::class);
    }

    /**
     * The general service cost definition that was instantiated here.
     */
    public function serviceCostDefinition() // Renamed to avoid conflict with model name
    {
        return $this->belongsTo(ServiceCost::class, 'service_cost_id');
    }
}