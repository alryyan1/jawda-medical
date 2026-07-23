<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestedServiceCost extends Model
{
    use HasFactory;

    protected $fillable = [
        'requested_service_id',
        'party_id',
        'amount',
        'user_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function requestedService(): BelongsTo
    {
        return $this->belongsTo(RequestedService::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
