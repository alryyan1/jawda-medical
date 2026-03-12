<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnedRequestedService extends Model
{
    use HasFactory;

    protected $table = 'returned_requested_services';

    protected $fillable = [
        'requested_service_id',
        'amount',
        'returned_payment_method',
        'user_id',
        'shift_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function requestedService(): BelongsTo
    {
        return $this->belongsTo(RequestedService::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
