<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OperationCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'operation_id',
        'operation_item_id',
        'perc',
        'fixed',
        'is_surgeon',
    ];

    protected $casts = [
        'perc' => 'decimal:2',
        'fixed' => 'decimal:2',
        'is_surgeon' => 'boolean',
    ];

    /**
     * Relationship: Linked to the Operation Template (Settings)
     */
    public function operation()
    {
        return $this->belongsTo(Operation::class);
    }

    /**
     * Relationship: Linked to the Catalogue Item
     */
    public function operationItem()
    {
        return $this->belongsTo(OperationItem::class);
    }
}
