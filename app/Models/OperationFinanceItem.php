<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationFinanceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation_id',
        'item_type',
        'category',
        'description',
        'amount',
        'is_auto_calculated',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'is_auto_calculated' => 'boolean',
    ];

    /**
     * Relationship
     */
    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }
}
