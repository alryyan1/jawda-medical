<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SurgicalOperationCharge extends Model
{
    use HasFactory;

    protected $fillable = [
        'surgical_operation_id',
        'name',
        'type',
        'amount',
        'reference_type',
        'reference_charge_id'
    ];

    public function operation()
    {
        return $this->belongsTo(SurgicalOperation::class);
    }

    public function referenceCharge()
    {
        return $this->belongsTo(SurgicalOperationCharge::class, 'reference_charge_id');
    }
}
