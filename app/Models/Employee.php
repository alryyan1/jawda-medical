<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'job_title',
        'department_id',
        'fixed_amount',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'fixed_amount' => 'float',
    ];

    public function department(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(EmployeeExpense::class);
    }
}
