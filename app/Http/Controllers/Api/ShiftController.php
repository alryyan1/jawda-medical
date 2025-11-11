<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use App\Http\Resources\ShiftResource;
use App\Models\DoctorShift;
use App\Models\LabRequest;
use App\Models\RequestedService;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ShiftController extends Controller
{
    public function __construct()
    {
        // Define permissions for shift management
        // $this->middleware('can:list shifts')->only('index');
        // $this->middleware('can:view shifts')->only('show');
        // $this->middleware('can:create shifts')->only('openShift'); // 'openShift' maps to store-like action
        // $this->middleware('can:close shifts')->only('closeShift'); // 'closeShift' maps to update-like action
        // $this->middleware('can:manage shift_financials')->only('updateFinancials');
    }

    /**
     * Display a listing of the shifts.
     * Filters can be added: e.g., date range, open/closed status.
     */
    // public function index(Request $request)
    // {
    //     $query = Shift::latest(); // Default order by most recent

    //     if ($request->has('is_closed') && $request->is_closed !== '') {
    //         // $query->where('is_closed', (bool)$request->is_closed);
    //     }
    //     if ($request->filled('date_from')) {
    //         $query->whereDate('created_at', '>=', $request->date_from);
    //     }
    //     if ($request->filled('date_to')) {
    //         $query->whereDate('created_at', '<=', $request->date_to);
    //     }

    //     //if perpage = 0 return all
    //     if ($request->get('per_page') == 0) {
    //         $shifts = $query->get();
    //     } else {
    //         $shifts = $query->paginate($request->get('per_page', 15));
    //     }
    //     return ShiftResource::collection($shifts);
    // }
    public function index(Request $request)
    {
        $query = Shift::with(['userOpened:id,name', 'userClosed:id,name']) // Eager load users
            ->latest('created_at');

        if ($request->has('is_closed') && $request->is_closed !== '' && $request->is_closed !== 'all') {
            $query->where('is_closed', (bool) $request->is_closed);
        }

        // If a single date is provided, treat it as filtering for that day
        if ($request->filled('date')) { // Expecting 'YYYY-MM-DD'
            $query->whereDate('created_at', '=', Carbon::parse($request->date)->toDateString());
        }
        // Keep existing date_from/date_to logic if still needed for other use cases
        elseif ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }


        $perPage = $request->input('per_page');
        if ($perPage === '0' || $perPage === 0 || filter_var($perPage, FILTER_VALIDATE_INT) === 0) {
            $shifts = $query->get();
            return ShiftResource::collection($shifts); // Return as collection if not paginating
        }

        $shifts = $query->paginate($perPage ?: 15);
        return ShiftResource::collection($shifts);
    }
    /**
     * Get the current open shift, if any.
     * This is useful for the frontend to know which shift operations are happening under.
     */
    public function getCurrentOpenShift()
    {
        $openShift = Shift::orderBy('id', 'desc')->first();
        if ($openShift) {
            return new ShiftResource($openShift->loadMissing(['userOpened', 'userClosed']));
        }
        return response()->json(['message' => 'لا توجد وردية عمل مفتوحة حالياً.'], 404);
    }

    public function getCurrentShift()
    {
        $currentShift = Shift::latest('id')->first();
        return new ShiftResource($currentShift->loadMissing(['userOpened', 'userClosed']));
    }


    /**
     * Open a new clinic shift.
     */
    public function openShift(Request $request)
    {
        // //check if user has permission to open shift
        // if (!Auth::user()->can('open financials_shift')) {
        //     return response()->json(['message' => 'ليس لديك صلاحية لفتح وردية عمل.'], 403);
        // }

        $user = Auth::user();
        
        // Check if there's already an open shift to prevent multiple open shifts
        $currentOpenShift = Shift::latest('id')->first();
        if ($currentOpenShift && $currentOpenShift->is_closed == false) {
            return response()->json(['message' => 'يوجد وردية عمل مفتوحة بالفعل. يجب إغلاق الوردية الحالية قبل فتح وردية جديدة.'], 409);
        }

        // Check if at least 6 hours have passed since the last shift was closed
        $lastClosedShift = Shift::where('is_closed', true)
            ->whereNotNull('closed_at')
            ->orderBy('closed_at', 'desc')
            ->first();

        if ($lastClosedShift) {
            $hoursSinceLastShift = Carbon::now()->diffInHours($lastClosedShift->created_at);
            if ($hoursSinceLastShift < 6) {
                $remainingHours = 6 - $hoursSinceLastShift;
                return response()->json([
                    'message' => "يجب أن تمر 6 ساعات على الأقل بين الورديات. الوقت المتبقي: {$remainingHours} ساعة.",
                    'hours_remaining' => $remainingHours,
                    'last_shift_closed_at' => $lastClosedShift->closed_at->toDateTimeString()
                ], 409);
            }
        }

        $validatedData = $request->validate([
            // 'name' => 'nullable|string|max:255', // If you add a name field
            'pharmacy_entry' => 'nullable|boolean',
            // 'user_id_opened' => 'required|exists:users,id', // If tracking who opened
        ]);

        $shift = Shift::create([
            'is_closed' => false,
            'touched' => false,
            'pharmacy_entry' => $request->input('pharmacy_entry', null),
            // 'user_id_opened' => Auth::id(), // Or passed in request
            // 'name' => $validatedData['name'] ?? 'Shift - ' . Carbon::now()->toDateTimeString(),
            'total' => 0, // Initial values
            'bank' => 0,
            'expenses' => 0,
            'user_id' => $user->id, // Assuming the user is the one opening the shift
        ]);

        return new ShiftResource($shift);
    }
    public function getFinancialSummary(Request $request, Shift $shift)
    {
        // Permission check: e.g., can('view shift_financial_summary', $shift)
        if ($shift->is_closed && !$request->user()->can('view closed_shift_summary')) {
            // Potentially different permission for closed shifts
        }

        // 1. Calculate Total Net Income (Services + Lab) for this shift
        // This logic needs to be robust and match how you define "income"
        // and how you link services/lab_requests to shifts.

        // Service Revenue
        $serviceNetIncome = RequestedService::query()
            ->whereHas('doctorVisit', fn($dvq) => $dvq->where('shift_id', $shift->id))
            ->join('doctorvisits', 'requested_services.doctorvisits_id', '=', 'doctorvisits.id')
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
            ->selectRaw('SUM(
                (requested_services.price * requested_services.count) 
                - requested_services.discount 
                - CASE 
                    WHEN patients.company_id IS NOT NULL 
                    THEN requested_services.endurance 
                    ELSE 0 
                END
            ) as total_net')
            ->value('total_net') ?? 0;

        $labNetIncome = LabRequest::query()
            ->whereHas('doctorVisit', fn($dvq) => $dvq->where('shift_id', $shift->id))
            ->join('doctorvisits', 'labrequests.doctor_visit_id', '=', 'doctorvisits.id')
            ->join('patients', 'doctorvisits.patient_id', '=', 'patients.id')
            ->selectRaw('SUM(
                labrequests.price 
                - (labrequests.price * labrequests.discount_per / 100)
                - CASE 
                    WHEN patients.company_id IS NOT NULL 
                    THEN labrequests.endurance 
                    ELSE 0 
                END
            ) as total_net')
            ->value('total_net') ?? 0;

        $totalNetIncome = (float) $serviceNetIncome + (float) $labNetIncome;

        // 2. Calculate Total Cash and Bank Payments specifically for this Shift
        // This requires payments (e.g., RequestedServiceDeposit or direct updates on items)
        // to be linked to the shift_id.
        // Let's assume 'is_bankak' or 'is_bank' field exists on LabRequest and RequestedService
        // and amount_paid reflects actual collection.

        $totalCashCollected = 0;
        $totalBankCollected = 0;

        // Cash/Bank from Services
        $servicePayments = RequestedService::query()
            ->whereHas('doctorVisit', fn($dvq) => $dvq->where('shift_id', $shift->id))
            ->where('is_paid', true) // Consider only fully paid or sum all amount_paid
            ->get(['amount_paid', 'bank']); // Assuming 'bank' is boolean for payment method

        foreach ($servicePayments as $sp) {
            if ($sp->bank) {
                $totalBankCollected += (float) $sp->amount_paid;
            } else {
                $totalCashCollected += (float) $sp->amount_paid;
            }
        }

        // Cash/Bank from Lab Requests
        $labPayments = LabRequest::query()
            ->whereHas('doctorVisit', fn($dvq) => $dvq->where('shift_id', $shift->id))
            ->where('is_paid', true)
            ->get(['amount_paid', 'is_bankak']); // Assuming 'is_bankak'

        foreach ($labPayments as $lp) {
            if ($lp->is_bankak) {
                $totalBankCollected += (float) $lp->amount_paid;
            } else {
                $totalCashCollected += (float) $lp->amount_paid;
            }
        }

        // Get shift's recorded expenses (already on shift model)
        $totalExpenses = (float) $shift->expenses;

        return response()->json([
            'data' => [
                'shift_id' => $shift->id,
                'is_closed' => (bool) $shift->is_closed,
                'closed_at' => $shift->closed_at?->toIso8601String(),
                'total_net_income' => round($totalNetIncome, 2),
                'total_discount_applied' => round(($serviceNetIncome + $labNetIncome) - ($totalCashCollected + $totalBankCollected - $totalExpenses), 2), //This needs to be calculated based on actual discount fields from services/lab requests if available or make totalIncome be from price before discount
                'total_cash_collected' => round($totalCashCollected, 2),
                'total_bank_collected' => round($totalBankCollected, 2),
                'total_collected' => round($totalCashCollected + $totalBankCollected, 2),
                'recorded_expenses' => round($totalExpenses, 2),
                'expected_cash_in_drawer' => round($totalCashCollected - $totalExpenses, 2), // Simple calculation
                'shift_total_recorded' => (float) $shift->total, // From shift closure
                'shift_bank_recorded' => (float) $shift->bank,   // From shift closure
            ]
        ]);
    }
    /**
     * Display the specified shift.
     */
    public function show(Shift $shift)
    {
        // Load related data if needed for a detailed view
        // $shift->load(['patients', 'doctorShifts', 'costs']);
        return new ShiftResource($shift);
    }



    /**
     * Close an open clinic shift.
     * This action will also close any associated active doctor shifts.
     */
    // public function closeShift(Request $request, Shift $shift)
    // {

    //       if (!Auth::user()->can('close financials_shift')) {   
    //         return response()->json(['message' => 'ليس لديك صلاحية لإغلاق وردية عمل.'], 403);
    //       }

    //     // Permission Check: e.g., can('close clinic_shifts')
    //     // if (!Auth::user()->can('close clinic_shifts')) {
    //     //     return response()->json(['message' => 'Unauthorized'], 403);
    //     // }

    //     if ($shift->is_closed) {
    //         return response()->json(['message' => 'وردية العمل هذه مغلقة بالفعل.'], 400);
    //     }


    //     DB::beginTransaction();
    //     try {
    //         $closingTime = Carbon::now();
    //         $closingUserId = Auth::id(); // User performing the close action

    //         // 1. Update and close the main clinic shift
    //         $shift->update([
    //             'user_closed' => Auth::id(),

    //             'is_closed' => true,
    //             'closed_at' => $closingTime,
    //             // 'user_id_closed' => $closingUserId, // If you track this
    //         ]);

    //         // 2. Find and close all open DoctorShift records associated with this general Shift
    //         $openDoctorShifts = DoctorShift
    //                                       ::where('status', true) // Only active ones
    //                                       ->get();

    //         foreach ($openDoctorShifts as $doctorShift) {
    //             $doctorShift->update([
    //                 'status' => false,
    //                 'end_time' => $closingTime, // Set their end time to the general shift's closing time
    //                 // Optionally, if a different user is closing, or log who closed it.
    //                 // This assumes the DoctorShift model doesn't have a specific 'closed_by_user_id'.
    //                 // If it does, you might want to update that too.
    //             ]);
    //         }

    //         // TODO: Add logic for financial reconciliation here if needed.
    //         // E.g., compare system calculated totals with manually entered totals.
    //         // The 'touched' flag could be set if there's a discrepancy or manual override.

    //         // TODO: Potentially trigger events, like generating end-of-shift reports.
    //         // event(new ClinicShiftClosed($shift));

    //         DB::commit();

    //         return new ShiftResource($shift->loadMissing(['doctorShifts', /* other relations for resource */]));

    //     } catch (\Exception $e) {
    //         DB::rollBack();
    //         // Log the error: Log::error('Failed to close clinic shift and associated doctor shifts: ' . $e->getMessage());
    //         return response()->json(['message' => 'حدث خطأ أثناء إغلاق الوردية.', 'error' => $e->getMessage()], 500);
    //     }
    // }
    /**
     * Close an open clinic shift.
     * This action will only proceed if all associated doctor shifts for THIS general shift are closed.
     * If doctor shifts (for this general shift) are open, it will return an error.
     * It will also close any *unrelated* active doctor shifts if that's the intended system behavior upon general shift close.
     */
    public function closeShift(Request $request, Shift $shift)
    {
        if (!Auth::user()->can('close financials_shift')) {
            // return response()->json(['message' => 'ليس لديك صلاحية لإغلاق وردية عمل.'], 403);
        }

        if ($shift->is_closed) {
            return response()->json(['message' => 'وردية العمل هذه مغلقة بالفعل.'], 400);
        }
        
        // Enforce minimum 6 hours since this shift was opened
        $openedAt = $shift->created_at;
        $hoursOpen = $openedAt ? Carbon::parse($openedAt)->diffInHours(Carbon::now()) : 0;
        if ($hoursOpen < 6) {
            $remainingHours = 6 - $hoursOpen;
            return response()->json([
                'message' => "لا يمكن إغلاق الوردية قبل مرور 6 ساعات من فتحها. الوقت المتبقي: {$remainingHours} ساعة.",
                'hours_remaining' => $remainingHours,
                'opened_at' => $openedAt?->toDateTimeString(),
            ], 409);
        }


        // Check for any open DoctorShift records specifically associated with THIS general Shift ($shift)
        $openDoctorShiftsForThisGeneralShift = DoctorShift::where('shift_id', $shift->id)
            ->where('status', true) // Active
            ->with('doctor:id,name') // Load doctor names for the message
            ->get();

        //if current user has role superadmin skip this check
        if (!Auth::user()->hasRole('admin')) {


            if ($openDoctorShiftsForThisGeneralShift->isNotEmpty()) {
                $doctorNames = $openDoctorShiftsForThisGeneralShift->pluck('doctor.name')->filter()->implode(', ');
                return response()->json([
                    'message' => "$doctorNames \n  لا يمكن إغلاق الوردية العامة. لا تزال هناك ورديات أطباء مفتوحة مرتبطة بهذه الوردية.",
                    'open_doctor_shifts' => 'الأطباء الذين لديهم ورديات مفتوحة حالياً: ' . $doctorNames,
                    // Optionally return the list of open shifts for frontend handling
                    // 'data' => $openDoctorShiftsForThisGeneralShift->map(fn($ds) => ['doctor_id' => $ds->doctor_id, 'doctor_name' => $ds->doctor?->name, 'doctor_shift_id' => $ds->id])
                ], 409); // 409 Conflict
            }
        }

        // Additional consideration: What about doctor shifts that might be open but NOT tied to this specific $shift->id,
        // but perhaps tied to older general shifts that were never properly closed?
        // The original code `DoctorShift::where('status', true)->get()` would close ALL active doctor shifts regardless
        // of which general shift they belonged to. This might be desired if closing the current general shift
        // implies ending ALL doctor activity.
        // If you ONLY want to ensure doctor shifts for *this* general shift are closed, the check above is sufficient.
        // If you want to close ALL globally active doctor shifts upon closing THIS general shift:
        $allGloballyOpenDoctorShifts = DoctorShift::where('status', true)->get();


        DB::beginTransaction();
        try {
            $closingTime = Carbon::now();
            $closingUserId = Auth::id();

            // 1. Close all globally open Doctor Shifts (if this is the desired behavior)
            // If you only cared about doctor shifts associated with $shift->id, this loop is not needed
            // because the check above would have already ensured they are closed.
            if ($allGloballyOpenDoctorShifts->isNotEmpty()) {
                foreach ($allGloballyOpenDoctorShifts as $doctorShift) {
                    $doctorShift->update([
                        'status' => false,
                        'end_time' => $closingTime,
                        // 'user_id_closed_by_general_shift' => $closingUserId, // Optional: if you want to track this
                    ]);
                }
            }

            // 2. Update and close the main clinic shift
            $shiftDataToUpdate = [
                'is_closed' => true,
                'closed_at' => $closingTime,
                'user_closed' => $closingUserId, // Assuming user_id_closed column exists on shifts table
            ];

            // Financial reconciliation fields if provided (ensure validation if they are optional or required)
            if ($request->has('total')) {
                $shiftDataToUpdate['total'] = $request->input('total');
            }
            if ($request->has('bank')) {
                $shiftDataToUpdate['bank'] = $request->input('bank');
            }
            if ($request->has('expenses')) {
                $shiftDataToUpdate['expenses'] = $request->input('expenses');
            }
            if ($request->has('touched')) {
                $shiftDataToUpdate['touched'] = $request->boolean('touched');
            }
            // $shift->user_closed = $closingUserId;

            $shift->update($shiftDataToUpdate);

            DB::commit();

            return new ShiftResource($shift->loadMissing(['userClosed', 'doctorShifts']));

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Failed to close clinic shift {$shift->id}: " . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'حدث خطأ أثناء إغلاق الوردية.', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Update financial details of a shift (e.g., by an accountant after review).
     * This might be a separate action from 'closeShift' if review happens later.
     */
    public function updateFinancials(Request $request, Shift $shift)
    {
        // if (!Auth::user()->can('manage shift_financials')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $validatedData = $request->validate([
            'total' => 'sometimes|required|numeric|min:0',
            'bank' => 'sometimes|required|numeric|min:0',
            'expenses' => 'sometimes|required|numeric|min:0',
            'touched' => 'sometimes|required|boolean',
        ]);

        $shift->update($validatedData);
        return new ShiftResource($shift);
    }

    // A standard update and destroy might not be typical for shifts.
    // Shifts are usually opened and then closed (which is an update).
    // Deleting a shift might have serious data integrity implications.
    // If you need a generic update for other fields, you can add it.
    // public function update(Request $request, Shift $shift) { ... }
    // public function destroy(Shift $shift) { ... }
}