<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class DoctorShift extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',          // User who started/manages this specific doctor's shift
        'shift_id',         // The general clinic shift ID
        'doctor_id',
        'status',           // true (active/open), false (closed)
        'start_time',
        'end_time',
        'is_cash_revenue_prooved',
        'is_cash_reclaim_prooved',
        'is_company_revenue_prooved',
        'is_company_reclaim_prooved',
    ];

    protected $casts = [
        'status' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_cash_revenue_prooved' => 'boolean',
        'is_cash_reclaim_prooved' => 'boolean',
        'is_company_revenue_prooved' => 'boolean',
        'is_company_reclaim_prooved' => 'boolean',
    ];

    /**
     * Get the doctor associated with this shift session.
     */
    public function doctor()
    {
        return $this->belongsTo(Doctor::class);
    }

    /**
     * Get the user who created or is associated with this doctor's shift session.
     * This might be the doctor themselves if they log their own shifts, or an admin/manager.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the general clinic shift this doctor's session belongs to.
     */
    public function generalShift() // Renamed to avoid conflict if 'shift' is a keyword or used elsewhere
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    /**
     * Get all visits that occurred during this specific doctor's shift session.
     */
    public function doctorVisits()
    {
        return $this->hasMany(DoctorVisit::class); // Assuming DoctorVisit has a doctor_shift_id FK
    }


    // Scopes for querying

    /**
     * Scope a query to only include active doctor shifts.
     */
    public function scopeActive($query)
    {
        // Define what "active" means.
        // Example 1: Explicit status column
        return $query->where('status', true);

        // Example 2: Active if start_time is set and end_time is null
        // return $query->whereNotNull('start_time')->whereNull('end_time');
    }

    /**
     * Scope a query to doctor shifts that are active today.
     */
    public function scopeActiveToday($query)
    {
        // This logic depends heavily on how you track active shifts.
        // If a shift can span multiple days, this is more complex.
        // Assuming a shift is for a single day based on start_time or created_at.
        return $query->active() // Use the active scope
                     ->where(function ($q) {
                         $q->whereDate('start_time', Carbon::today()) // Started today and still active
                           ->orWhere(function ($q2) { // Or started yesterday but end_time is null or today
                               $q2->whereDate('start_time', Carbon::yesterday())
                                  ->where(function ($q3) {
                                      $q3->whereNull('end_time')
                                         ->orWhereDate('end_time', Carbon::today());
                                  });
                           });
                     });
                     // Simpler version if 'status' column is reliably managed:
                     // ->where('status', true)->whereDate('created_at', Carbon::today());
    }
}