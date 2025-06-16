<?php

namespace App\Services;

use App\Models\User;
use App\Models\WorkdayPattern; // If you create this model
use Carbon\Carbon;

class AttendanceCalculatorService
{
    protected array $holidays; // Dates as "YYYY-MM-DD"

    public function __construct(array $holidaysForPeriod)
    {
        $this->holidays = $holidaysForPeriod;
    }

    public function isWorkingDay(Carbon $date, ?WorkdayPattern $pattern = null, ?User $user = null): bool
    {
        if (in_array($date->format('Y-m-d'), $this->holidays)) {
            return false; // It's a public holiday
        }

        if ($pattern) {
            // Advanced logic: Check $date against the rules in $pattern
            // e.g., if pattern is "Mon-Fri", check if $date->dayOfWeek is 1-5
            // e.g., if pattern is "4 on, 2 off", needs more complex logic with a reference start date for the user's cycle.
            // For now, simplified:
            if ($pattern->type === 'standard_weekly') { // Example pattern type
                 // Assuming pattern has 'works_on_monday', 'works_on_tuesday' boolean fields
                 $dayOfWeekField = 'works_on_' . strtolower($date->format('l')); // e.g., works_on_monday
                 if (isset($pattern->{$dayOfWeekField})) {
                    return (bool) $pattern->{$dayOfWeekField};
                 }
            }
            // Fallback for unhandled patterns, or if no specific pattern check applies
        }
        
        // Default: Standard Mon-Fri working days if no specific pattern applies or pattern is simple
        return !$date->isWeekend(); // Default considers Sat/Sun as weekend
    }

    // ... other calculation methods:
    // calculatePayableHours, calculateOvertime, calculateLatenessDeduction, etc.
}