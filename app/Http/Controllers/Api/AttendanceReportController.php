<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AttendanceResource;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\User;
use App\Models\ShiftDefinition;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth; // If reports are user-aware

class AttendanceReportController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:view attendance_reports');
    }

    /**
     * Generates a monthly attendance summary for each employee.
     */
    public function monthlyEmployeeSummary(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'department_id' => 'nullable|integer|exists:departments,id', // Optional filter
            'user_id' => 'nullable|integer|exists:users,id',         // Optional filter
        ]);

        $month = $validated['month'];
        $year = $validated['year'];
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        // Get users to report on
        $usersQuery = User::query()->select('id', 'name') // Add other fields like employee_id if you have it
                          ->withCount(['attendances' => function ($query) use ($startDate, $endDate) {
                                $query->whereBetween('attendance_date', [$startDate, $endDate]);
                           }]) // Eager load count for quick check if user had any attendance
                           ->orderBy('name');

        if ($request->filled('user_id')) {
            $usersQuery->where('id', $request->user_id);
        }
        // Add department filter if 'department_id' exists on User model
        // if ($request->filled('department_id')) {
        //     $usersQuery->where('department_id', $request->department_id);
        // }

        $users = $usersQuery->get();

        // Get all holidays for the month for accurate 'working_days' calculation
        $holidaysInMonth = Holiday::whereBetween('holiday_date', [$startDate, $endDate])
                            ->pluck('holiday_date')
                            ->map(fn ($date) => $date->format('Y-m-d'))
                            ->unique()
                            ->toArray();
        
        // Calculate total working days in the month (excluding weekends and public holidays)
        // This is a simplified calculation. Real-world might need WorkdayPattern logic.
        $totalDaysInMonth = $startDate->daysInMonth;
        $workingDaysInMonth = 0;
        $period = CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $date) {
            if (!$date->isWeekend() && !in_array($date->format('Y-m-d'), $holidaysInMonth)) {
                $workingDaysInMonth++;
            }
        }


        $reportData = [];
        foreach ($users as $user) {
            // Skip users with no attendance records in the month if not filtering for a specific user
            if ($user->attendances_count === 0 && !$request->filled('user_id')) {
                // continue;
            }

            $userAttendances = Attendance::where('user_id', $user->id)
                ->whereBetween('attendance_date', [$startDate, $endDate])
                ->get();

            $presentDays = $userAttendances->whereIn('status', ['present', 'late_present', 'early_leave'])->count();
            $absentDays = $workingDaysInMonth - $presentDays - $userAttendances->where('status', 'on_leave')->count() - $userAttendances->where('status', 'sick_leave')->count();
            $lateDays = $userAttendances->where('status', 'late_present')->count();
            $earlyLeaveDays = $userAttendances->where('status', 'early_leave')->count();
            $leaveDays = $userAttendances->where('status', 'on_leave')->count();
            $sickLeaveDays = $userAttendances->where('status', 'sick_leave')->count();
            // $offDaysTaken = $userAttendances->where('status', 'off_day')->count(); // User specific off-days if any
            // $holidaysWorked = $userAttendances->where('status', 'holiday_worked')->count(); // If tracking this

            // Calculate total worked hours (if check_in_time and check_out_time are reliably populated)
            $totalWorkedHours = 0;
            foreach ($userAttendances->whereNotNull('check_in_time')->whereNotNull('check_out_time') as $att) {
                if ($att->check_in_time && $att->check_out_time) {
                    $totalWorkedHours += $att->check_in_time->diffInHours($att->check_out_time);
                }
            }

            $reportData[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'present_days' => $presentDays,
                'absent_days' => max(0, $absentDays), // Ensure not negative
                'late_days' => $lateDays,
                'early_leave_days' => $earlyLeaveDays,
                'leave_days' => $leaveDays,
                'sick_leave_days' => $sickLeaveDays,
                'total_worked_hours' => round($totalWorkedHours, 2), // if captured
                'working_days_in_month' => $workingDaysInMonth, // For context
            ];
        }

        return response()->json([
            'data' => $reportData,
            'meta' => [
                'month' => $month,
                'year' => $year,
                'month_name' => $startDate->translatedFormat('F Y'),
                'total_working_days' => $workingDaysInMonth,
            ]
        ]);
    }

    /**
     * Generates a detailed attendance report for a specific day.
     */
    public function dailyAttendanceDetail(Request $request)
    {
        $validated = $request->validate([
            'date' => 'required|date_format:Y-m-d',
            'shift_definition_id' => 'nullable|integer|exists:shifts_definitions,id', // Optional: filter by specific shift
        ]);

        $attendanceDate = Carbon::parse($validated['date']);
        $shiftDefinitionId = $request->input('shift_definition_id');

        $attendanceSettings = AttendanceSetting::current();
        $numberOfShiftsPerDay = $attendanceSettings->number_of_shifts_per_day;
        
        $shiftDefinitionsQuery = ShiftDefinition::where('is_active', true)
                                    ->orderBy('shift_label')
                                    ->take($numberOfShiftsPerDay);
        
        if ($shiftDefinitionId) {
            $shiftDefinitionsQuery->where('id', $shiftDefinitionId);
        }
        $activeShiftDefinitions = $shiftDefinitionsQuery->get();

        $reportData = [];

        foreach ($activeShiftDefinitions as $shiftDef) {
            $attendances = Attendance::where('attendance_date', $attendanceDate->toDateString())
                ->where('shift_definition_id', $shiftDef->id)
                ->with(['user:id,name', 'supervisor:id,name', 'recorder:id,name'])
                ->orderBy('users.name') // Need join for this or sort after fetching
                ->join('users', 'attendances.user_id', '=', 'users.id') // Join for sorting by user name
                ->select('attendances.*') // Important to avoid selecting only users.name
                ->get();

            $shiftData = [
                'shift_definition_id' => $shiftDef->id,
                'shift_label' => $shiftDef->shift_label,
                'shift_name' => $shiftDef->name,
                'start_time' => $shiftDef->start_time,
                'end_time' => $shiftDef->end_time,
                'records' => AttendanceResource::collection($attendances),
            ];
            $reportData[] = $shiftData;
        }

        return response()->json([
            'data' => $reportData,
            'meta' => [
                'date' => $attendanceDate->format('Y-m-d'),
                'day_name' => $attendanceDate->translatedFormat('l'), // Full day name
            ]
        ]);
    }
/**
     * Generates a consolidated attendance report suitable for payroll processing.
     */
    public function payrollAttendanceReport(Request $request)
    {
        // if (!Auth::user()->can('view payroll_attendance_report')) { /* ... */ }

        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            'department_id' => 'nullable|integer|exists:departments,id', // Optional
            'user_ids' => 'nullable|array',                             // Optional: specific users
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $month = $validated['month'];
        $year = $validated['year'];
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $usersQuery = User::query()->select('id', 'name') // Assuming 'employee_id' for payroll systems
                          ->with([
                              'attendances' => function ($query) use ($startDate, $endDate) {
                                  $query->whereBetween('attendance_date', [$startDate, $endDate])
                                        ->with('shiftDefinition:id,start_time,end_time'); // For hour calculation
                              },
                              // 'workdayPattern', // If you implement workday patterns
                              // 'leaves' => function ($q) use ($startDate, $endDate) { // If separate leave system
                              //    $q->whereBetween('start_date', [$startDate, $endDate])
                              //      ->orWhereBetween('end_date', [$startDate, $endDate]);
                              // }
                          ])
                          ->orderBy('name');

        if ($request->filled('user_ids')) {
            $usersQuery->whereIn('id', $validated['user_ids']);
        }
        if ($request->filled('department_id')) {
            // $usersQuery->where('department_id', $validated['department_id']);
        }

        $users = $usersQuery->get();

        $holidaysInMonth = Holiday::whereBetween('holiday_date', [$startDate, $endDate])
                            ->pluck('holiday_date')
                            ->map(fn ($date) => $date->format('Y-m-d'))
                            ->unique()
                            ->toArray();
        
        // Attendance Calculator Service (for complex logic)
        // $calculator = new AttendanceCalculatorService($holidaysInMonth);

        $reportData = [];
        foreach ($users as $user) {
            // $userWorkdayPattern = $user->workdayPattern; // If implemented

            $userAttendances = $user->attendances; // Already filtered by month/year

            // --- Initialize Counters ---
            $payableDays = 0;
            $unpaidLeaveDays = 0;
            $paidLeaveDays = 0; // If you distinguish leave types
            $sickLeaveDays = 0;
            $absentDays = 0;
            $lateCount = 0;
            $earlyLeaveCount = 0;
            $workedHours = 0;
            $overtimeHours = 0; // Requires shift definitions and rules
            $standardHoursExpected = 0;

            $period = CarbonPeriod::create($startDate, $endDate);
            foreach ($period as $date) {
                $dateString = $date->format('Y-m-d');
                $isWeekend = $date->isWeekend(); // Simple weekend check
                $isHoliday = in_array($dateString, $holidaysInMonth);
                
                // More advanced: Check against user's specific workday pattern
                // $isWorkingDayForUser = $calculator->isWorkingDay($date, $userWorkdayPattern, $holidaysInMonth);
                $isConsideredWorkingDay = !$isWeekend && !$isHoliday; // Simplified

                $dayAttendances = $userAttendances->filter(fn($att) => $att->attendance_date->isSameDay($date));

                if ($dayAttendances->isEmpty()) {
                    if ($isConsideredWorkingDay) {
                        $absentDays++;
                    }
                    continue; // No attendance record for this day
                }

                // Assuming one attendance record per user per day for this summary
                // If multiple shifts, payroll report might need to sum hours across shifts or count "workdays"
                $dayAttendance = $dayAttendances->first(); // Or logic to pick the primary shift if multiple

                // Basic Payable Day Logic (can be much more complex)
                if (in_array($dayAttendance->status, ['present', 'late_present', 'early_leave', 'sick_leave'])) { // Assuming sick leave is paid
                    $payableDays++;
                } elseif ($dayAttendance->status === 'on_leave') {
                    // Distinguish paid vs unpaid leave if your 'leaves' table/system supports it
                    // For now, let's assume all 'on_leave' from attendance is unpaid for simplicity here.
                    // This should be tied to a proper Leave Management System.
                    $unpaidLeaveDays++;
                }
                
                if ($dayAttendance->status === 'late_present') $lateCount++;
                if ($dayAttendance->status === 'early_leave') $earlyLeaveCount++;
                if ($dayAttendance->status === 'sick_leave') $sickLeaveDays++;


                // Calculate worked hours if times are present
                if ($dayAttendance->check_in_time && $dayAttendance->check_out_time && $dayAttendance->shiftDefinition) {
                    $checkIn = Carbon::parse($dayAttendance->check_in_time);
                    $checkOut = Carbon::parse($dayAttendance->check_out_time);
                    $shiftStart = Carbon::parse($dayAttendance->attendance_date->toDateString() . ' ' . $dayAttendance->shiftDefinition->start_time);
                    $shiftEnd = Carbon::parse($dayAttendance->attendance_date->toDateString() . ' ' . $dayAttendance->shiftDefinition->end_time);
                    if($shiftEnd->lessThan($shiftStart)) $shiftEnd->addDay(); // Handle overnight shift end

                    $hoursOnShift = $checkIn->diffInMinutes($checkOut) / 60;
                    $workedHours += $hoursOnShift;
                    
                    // Basic Overtime (if worked beyond defined shift end)
                    // This is very simplified. Real OT needs rules (e.g., grace periods, approval)
                    // if ($checkOut->greaterThan($shiftEnd)) {
                    //    $ot = $shiftEnd->diffInMinutes($checkOut) / 60;
                    //    $overtimeHours += max(0, $ot - 0.25); // Example: OT if > 15min past shift
                    // }
                }
                if ($isConsideredWorkingDay && $dayAttendance->shiftDefinition) {
                     $standardHoursExpected += $dayAttendance->shiftDefinition->duration_hours;
                }
            }


            $reportData[] = [
                'user_id' => $user->id,
                'employee_id' => $user->id, // Payroll system ID
                'user_name' => $user->name,
                'payable_days' => $payableDays,
                'absent_days' => $absentDays,
                'unpaid_leave_days' => $unpaidLeaveDays,
                'paid_leave_days' => $paidLeaveDays, // Requires proper leave system integration
                'sick_leave_days' => $sickLeaveDays,
                'late_count' => $lateCount,
                'early_leave_count' => $earlyLeaveCount,
                'total_worked_hours' => round($workedHours, 2),
                'standard_hours_expected' => round($standardHoursExpected,2),
                'overtime_hours' => round($overtimeHours, 2),
                // Add LOP (Loss of Pay) days if logic is defined
            ];
        }

        return response()->json([
            'data' => $reportData,
            'meta' => [
                'month' => $month,
                'year' => $year,
                'month_name' => $startDate->translatedFormat('F Y'),
                'period_start_date' => $startDate->toDateString(),
                'period_end_date' => $endDate->toDateString(),
            ]
        ]);
    }
    // You can add more report methods here:
    // - lateComersReport
    // - overtimeReport
    // - attendanceVarianceReport (expected vs. actual)
}