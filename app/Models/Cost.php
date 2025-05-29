<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        'cost_category_id'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'amount_bankak' => 'decimal:2',
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
