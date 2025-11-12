<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $requested_service_id
 * @property string $amount
 * @property int $user_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $is_bank
 * @property bool $is_claimed
 * @property int $shift_id
 * @property-read \App\Models\RequestedService|null $requestedService
 * @property-read \App\Models\Shift|null $shift
 * @property-read \App\Models\User|null $user
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit query()
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit whereIsBank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit whereIsClaimed($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit whereRequestedServiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|RequestedServiceDeposit whereUserId($value)
 * @mixin \Eloquent
 */
class RequestedServiceDeposit extends Model
{
    use HasFactory;
    protected $fillable = [
        'requested_service_id',
        'amount',
        'user_id',
        'is_bank',
        'is_claimed',
        'shift_id'
    ];
    protected $casts = [
        'amount' => 'decimal:2',
        'is_bank' => 'boolean',
        'is_claimed' => 'boolean',
    ];

    public function requestedService()
    {
        return $this->belongsTo(RequestedService::class, 'requested_service_id', 'id');
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    } // User who processed payment
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    } // Shift of payment
}