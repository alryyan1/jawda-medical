<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property int $shift_id
 * @property int|null $user_cost
 * @property int|null $doctor_shift_id
 * @property string|null $description
 * @property string|null $comment
 * @property string $amount
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property int|null $cost_category_id
 * @property string $amount_bankak
 * @property-read \App\Models\CostCategory|null $costCategory
 * @property-read \App\Models\DoctorShift|null $doctorShift
 * @property-read \App\Models\Shift $shift
 * @property-read \App\Models\User|null $userCost
 * @method static \Illuminate\Database\Eloquent\Builder|Cost newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Cost newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Cost query()
 * @method static \Illuminate\Database\Eloquent\Builder|Cost whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cost whereAmountBankak($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cost whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cost whereCostCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cost whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cost whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cost whereDoctorShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cost whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cost whereShiftId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cost whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Cost whereUserCost($value)
 * @mixin \Eloquent
 */
class Cost extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'shift_id',
        'user_cost',
        'doctor_shift_id',
        'description',
        'comment',
        'amount',
        'amount_bankak',
        'cost_category_id',
        'doctor_shift_id_for_sub_cost'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'amount_bankak' => 'decimal:2',
        'doctor_shift_id_for_sub_cost' => 'integer',
    ];

    /**
     * Get the shift that owns the cost.
     */
    public function shift()
    {
        return $this->belongsTo(Shift::class);
    }

    /**
     * Get the cost category that owns the cost.
     */
    public function costCategory()
    {
        return $this->belongsTo(CostCategory::class);
    }

    /**
     * Get the user who created/is responsible for the cost.
     */
    public function userCost()
    {
        return $this->belongsTo(User::class, 'user_cost');
    }

    /**
     * Get the doctor shift associated with this cost.
     */
    public function doctorShift()
    {
        return $this->belongsTo(DoctorShift::class);
    }
}
