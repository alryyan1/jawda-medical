<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdmissionNursingAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'admission_id',
        'user_id',
        'assignment_description',
        'priority',
        'status',
        'due_date',
        'due_time',
        'completed_date',
        'completed_time',
        'notes',
        'completion_notes',
        'assigned_by_user_id',
    ];

    protected $casts = [
        'due_date' => 'date',
        'due_time' => 'datetime',
        'completed_date' => 'date',
        'completed_time' => 'datetime',
    ];

    /**
     * Get the admission that owns the assignment.
     */
    public function admission()
    {
        return $this->belongsTo(Admission::class);
    }

    /**
     * Get the nurse assigned to this task.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who assigned this task.
     */
    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by_user_id');
    }

    /**
     * Scope a query to only include pending assignments.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include completed assignments.
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }
}
