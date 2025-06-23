<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\AttendanceSetting;
use App\Models\ShiftDefinition;
use App\Models\User;
use App\Models\Holiday;
use Illuminate\Http\Request;
use App\Http\Resources\AttendanceResource;
use App\Http\Resources\ShiftDefinitionResource;
use App\Http\Resources\UserStrippedResource; // Assuming you have this for user lists
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AttendanceController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:record attendance')->only(['getMonthlySheet', 'recordAttendance']);
        // $this->middleware('can:edit attendance')->only('updateAttendance');
        // $this->middleware('can:delete attendance')->only('destroyAttendance'); // If deletion is allowed
    }

    /**
     * Get data for the monthly attendance recording sheet.
     */
    public function getMonthlySheet(Request $request)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 5),
        ]);

        $month = $validated['month'];
        $year = $validated['year'];
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $attendanceSettings = AttendanceSetting::current();
        $numberOfShiftsPerDay = $attendanceSettings->number_of_shifts_per_day;
        $activeShiftDefinitions = ShiftDefinition::where('is_active', true)
                                    ->orderBy('shift_label') // Ensures Shift 1, Shift 2, etc.
                                    ->take($numberOfShiftsPerDay) // Only take configured number of shifts
                                    ->get();

        // Fetch all relevant data in fewer queries
        $attendancesForMonth = Attendance::whereBetween('attendance_date', [$startDate, $endDate])
            ->with(['user:id,name', 'supervisor:id,name']) // Eager load for display
            ->get()
            ->groupBy(function ($attendance) {
                return $attendance->attendance_date->format('Y-m-d'); // Group by date string "YYYY-MM-DD"
            })
            ->map(function ($dailyAttendances) {
                return $dailyAttendances->groupBy('shift_definition_id')
                    ->map(function ($shiftAttendances) {
                        return $shiftAttendances->keyBy('user_id'); // Key by user_id for easy lookup
                    });
            });

        $holidaysForMonth = Holiday::whereBetween('holiday_date', [$startDate, $endDate])
            ->pluck('holiday_date')
            ->map(fn ($date) => $date->format('Y-m-d')); // Collection of "YYYY-MM-DD" strings

        $allUsers = User::orderBy('name')->get(['id', 'name', 'is_supervisor']); // For Autocomplete
        $supervisorUsers = $allUsers->where('is_supervisor', true)->values(); // Filtered supervisors

        $days = [];
        $period = CarbonPeriod::create($startDate, $endDate);

        foreach ($period as $date) {
            $dateString = $date->format('Y-m-d');
            $isHoliday = $holidaysForMonth->contains($dateString);
            $dayData = [
                'date' => $dateString,
                'day_name' => $date->translatedFormat('D'), // Localized day name (e.g., Mon, Tue)
                'is_holiday' => $isHoliday,
                'shifts' => [],
            ];

            foreach ($activeShiftDefinitions as $shiftDef) {
                $shiftAttendanceData = $attendancesForMonth[$dateString][$shiftDef->id] ?? collect();
                
                // Determine expected users for this shift on this day (basic version)
                // More advanced: consider workday patterns, specific assignments for the day
                $expectedUsersThisShift = User::whereHas('defaultShifts', function ($query) use ($shiftDef) {
                    $query->where('shift_definition_id', $shiftDef->id);
                })->pluck('id')->toArray();


                $shiftEntry = [
                    'shift_definition_id' => $shiftDef->id,
                    'shift_label' => $shiftDef->shift_label,
                    'shift_name' => $shiftDef->name,
                    'supervisor_id' => null, // Placeholder, will be filled from actual attendance
                    'employee_user_ids' => [], // Placeholder
                    'attendance_records' => [], // Store actual Attendance model/resource for this shift
                ];
                
                // Populate with actual attendance data
                $supervisorRecordFound = null;
                foreach ($shiftAttendanceData as $userId => $attendanceRecord) {
                    $shiftEntry['attendance_records'][] = new AttendanceResource($attendanceRecord);
                    if ($attendanceRecord->supervisor_id === $attendanceRecord->user_id) { // Convention: if supervisor_id is self, they are the shift supervisor
                        $shiftEntry['supervisor_id'] = $userId;
                        $supervisorRecordFound = $attendanceRecord;
                    } else if ($attendanceRecord->supervisor_id) { // Someone else supervised this specific entry
                         // This case is less common for "shift supervisor" which is usually one per shift-day
                    }
                }
                // If no specific supervisor marked, check if any of the attended users is a supervisor
                if (!$supervisorRecordFound && $shiftAttendanceData->isNotEmpty()) {
                     $potentialSupervisor = $shiftAttendanceData->first(function ($att) use ($allUsers) {
                        $user = $allUsers->firstWhere('id', $att->user_id);
                        return $user && $user->is_supervisor;
                     });
                     if ($potentialSupervisor) {
                        //  $shiftEntry['supervisor_id'] = $potentialSupervisor->user_id;
                     }
                }

                // Simplified expected employees: show those with default assignment OR those already marked present.
                $presentUserIds = $shiftAttendanceData->pluck('user_id')->toArray();
                $allRelevantUserIds = array_unique(array_merge($expectedUsersThisShift, $presentUserIds));
                
                // Remove supervisor if already assigned to the supervisor slot
                // $shiftEntry['employee_user_ids'] = array_values(array_diff($allRelevantUserIds, [$shiftEntry['supervisor_id']]));
                
                // For the UI, you'll likely need to provide:
                // 1. The user_id assigned to the supervisor slot for this shift-day.
                // 2. An array of user_ids assigned to the employee slots for this shift-day.
                // The frontend can then render Autocompletes based on these, plus empty slots.

                $dayData['shifts'][] = $shiftEntry;
            }
            $days[] = $dayData;
        }

        return response()->json([
            'days' => $days,
            'meta' => [
                'month' => $month,
                'year' => $year,
                'month_name' => $startDate->translatedFormat('F Y'),
                'number_of_shifts_configured' => $numberOfShiftsPerDay,
                'active_shift_definitions' => ShiftDefinitionResource::collection($activeShiftDefinitions),
            ],
            'selectable_users' => UserStrippedResource::collection($allUsers),
            'selectable_supervisors' => UserStrippedResource::collection($supervisorUsers),
        ]);
    }

    /**
     * Record or update an attendance entry.
     */
    public function recordOrUpdateAttendance(Request $request)
    {
        // if (!Auth::user()->can('record attendance')) { /* ... */ }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'shift_definition_id' => 'required|integer|exists:shifts_definitions,id',
            'attendance_date' => 'required|date_format:Y-m-d',
            'status' => ['required', Rule::in(['present', 'absent', 'late_present', 'early_leave', 'on_leave', 'holiday', 'off_day', 'sick_leave'])],
            'check_in_time' => 'nullable|date_format:Y-m-d H:i:s',
            'check_out_time' => 'nullable|date_format:Y-m-d H:i:s|after_or_equal:check_in_time',
            'supervisor_id' => 'nullable|integer|exists:users,id', // User who is supervising this shift/entry
            'notes' => 'nullable|string|max:1000',
            'is_shift_supervisor_entry' => 'sometimes|boolean' // Flag if this user_id is being set as the shift supervisor
        ]);
        
        $attendanceDate = Carbon::parse($validated['attendance_date']);
        $userId = $validated['user_id'];
        $shiftDefinitionId = $validated['shift_definition_id'];

        // Prevent assigning same user to multiple slots in the same shift on the same day, unless it's an update to their existing record.
        $existingEntryForUser = Attendance::where('user_id', $userId)
            ->where('attendance_date', $attendanceDate->toDateString())
            ->where('shift_definition_id', $shiftDefinitionId)
            ->first();

        // If this entry is for marking the shift supervisor
        $shiftSupervisorFieldUpdate = [];
        if ($request->boolean('is_shift_supervisor_entry')) {
            // Remove any other user as supervisor for this specific shift-day if they were previously marked
            Attendance::where('attendance_date', $attendanceDate->toDateString())
                        ->where('shift_definition_id', $shiftDefinitionId)
                        ->where('supervisor_id', '!=', $userId) // Keep if current user is already supervisor
                        ->whereColumn('supervisor_id', '=', 'user_id') // Clear only if they were the SHIFT supervisor
                        ->update(['supervisor_id' => null]); // Or point to a general shift manager if that's the logic
            
            $shiftSupervisorFieldUpdate['supervisor_id'] = $userId; // Mark this user as the one supervising THEMSELVES (shift supervisor convention)
        } else if ($request->filled('supervisor_id')) {
            // If a general supervisor_id is provided for the entry (not necessarily the shift supervisor)
            $shiftSupervisorFieldUpdate['supervisor_id'] = $validated['supervisor_id'];
        }


        $attendance = Attendance::updateOrCreate(
            [ // Find by these attributes
                'user_id' => $userId,
                'attendance_date' => $attendanceDate->toDateString(),
                'shift_definition_id' => $shiftDefinitionId,
            ],
            [ // Update with these, or create if not found
                'status' => $validated['status'],
                'check_in_time' => isset($validated['check_in_time']) ? Carbon::parse($validated['check_in_time']) : ($existingEntryForUser?->check_in_time ?: ($validated['status'] === 'present' ? $attendanceDate->copy()->setTimeFromTimeString(ShiftDefinition::find($shiftDefinitionId)->start_time) : null)),
                'check_out_time' => isset($validated['check_out_time']) ? Carbon::parse($validated['check_out_time']) : $existingEntryForUser?->check_out_time,
                'notes' => $validated['notes'] ?? $existingEntryForUser?->notes,
                'recorded_by_user_id' => Auth::id(),
                // Apply supervisor update carefully
                ...$shiftSupervisorFieldUpdate 
            ]
        );
        
        // If status is 'absent' or 'on_leave', etc., clear check_in/out times
        if (in_array($validated['status'], ['absent', 'on_leave', 'holiday', 'off_day', 'sick_leave'])) {
            $attendance->check_in_time = null;
            $attendance->check_out_time = null;
            $attendance->saveQuietly();
        }


        return new AttendanceResource($attendance->load(['user', 'shiftDefinition', 'supervisor', 'recorder']));
    }
    
    /**
     * Remove an attendance entry. (e.g., if user was marked by mistake)
     */
    public function destroyAttendance(Attendance $attendance)
    {
        // if (!Auth::user()->can('delete attendance', $attendance)) { /* ... */ }
        $attendance->delete();
        return response()->json(['message' => 'Attendance record deleted successfully.'], 200); // 200 with message, or 204
    }
}