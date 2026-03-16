<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'amount',
        'cash_amount',
        'bank_amount',
        'date',
        'shift_id',
        'user_id',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'float',
        'cash_amount' => 'float',
        'bank_amount' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
