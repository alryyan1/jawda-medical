<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionRequestedServiceCost extends Model
{
    use HasFactory;

    protected $table = 'admission_requested_service_costs';

    protected $fillable = [
        'admission_requested_service_id',
        'service_cost_id',
        'sub_service_cost_id',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    protected $with = ['serviceCost'];

    public function serviceCost()
    {
        return $this->belongsTo(ServiceCost::class, 'service_cost_id');
    }

    public function subServiceCost()
    {
        return $this->belongsTo(SubServiceCost::class);
    }

    public function admissionRequestedService()
    {
        return $this->belongsTo(AdmissionRequestedService::class, 'admission_requested_service_id');
    }
}
