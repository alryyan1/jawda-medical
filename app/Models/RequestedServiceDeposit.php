<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestedServiceDeposit extends Model
{
    use HasFactory;
    protected $fillable = [
        'requested_service_id', 'amount', 'user_id', 
        'is_bank', 'is_claimed', 'shift_id'
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'is_bank' => 'boolean',
        'is_claimed' => 'boolean',
    ];

    public function requestedService() { return $this->belongsTo(RequestedService::class); }
    public function user() { return $this->belongsTo(User::class); } // User who processed payment
    public function shift() { return $this->belongsTo(Shift::class); } // Shift of payment
}