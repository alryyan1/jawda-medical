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
    public function index(Request $request)
    {
        $query = Shift::latest(); // Default order by most recent

        if ($request->has('is_closed') && $request->is_closed !== '') {
            $query->where('is_closed', (bool)$request->is_closed);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $shifts = $query->paginate($request->get('per_page', 15));
        return ShiftResource::collection($shifts);
    }

    /**
     * Get the current open shift, if any.
     * This is useful for the frontend to know which shift operations are happening under.
     */
    public function getCurrentOpenShift()
    {
        $openShift = Shift::open()->latest('created_at')->first();
        if ($openShift) {
            return new ShiftResource($openShift);
        }
        return response()->json(['message' => 'لا توجد وردية عمل مفتوحة حالياً.'], 404);
    }


    /**
     * Open a new clinic shift.
     */
    public function openShift(Request $request)
    {
        //check if user has permission to open shift
        if (!Auth::user()->can('open financials_shift')) {
            return response()->json(['message' => 'ليس لديك صلاحية لفتح وردية عمل.'], 403);
        }

       
        $user = Auth::user();
        // Check if there's already an open shift to prevent multiple open shifts if that's your rule
        $existingOpenShift = Shift::open()->exists();
        if ($existingOpenShift) {
            return response()->json(['message' => 'يوجد وردية عمل مفتوحة بالفعل. يرجى إغلاقها أولاً.'], 409); // Conflict
        }

        $validatedData = $request->validate([
            // 'name' => 'nullable|string|max:255', // If you add a name field
            'pharmacy_entry' => 'nullable|boolean',
            // 'user_id_opened' => 'required|exists:users,id', // If tracking who opened
        ]);
        
        $shift = Shift::create([
            'is_closed' => false,
            'touched' => false,
            'pharamacy_entry' => $request->input('pharmacy_entry', null),
            // 'user_id_opened' => Auth::id(), // Or passed in request
            // 'name' => $validatedData['name'] ?? 'Shift - ' . Carbon::now()->toDateTimeString(),
            'total' => 0, // Initial values
            'bank' => 0,
            'expenses' => 0,
            'user_id'=> $user->id, // Assuming the user is the one opening the shift
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

        foreach($servicePayments as $sp) {
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

        foreach($labPayments as $lp) {
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
                'total_discount_applied' => round(($serviceNetIncome + $labNetIncome) - ($totalCashCollected + $totalBankCollected - $totalExpenses),2), //This needs to be calculated based on actual discount fields from services/lab requests if available or make totalIncome be from price before discount
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
    public function closeShift(Request $request, Shift $shift)
    {

          if (!Auth::user()->can('close financials_shift')) {   
            return response()->json(['message' => 'ليس لديك صلاحية لإغلاق وردية عمل.'], 403);
          }

        // Permission Check: e.g., can('close clinic_shifts')
        // if (!Auth::user()->can('close clinic_shifts')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        if ($shift->is_closed) {
            return response()->json(['message' => 'وردية العمل هذه مغلقة بالفعل.'], 400);
        }

        // Validate financials provided at closing time
        $validatedData = $request->validate([
            'total' => 'required|numeric|min:0',
            'bank' => 'required|numeric|min:0',
            'expenses' => 'required|numeric|min:0',
            'touched' => 'sometimes|boolean',
            // 'user_id_closed' => 'nullable|exists:users,id', // Optional: if user closing is different
        ]);

        DB::beginTransaction();
        try {
            $closingTime = Carbon::now();
            $closingUserId = Auth::id(); // User performing the close action

            // 1. Update and close the main clinic shift
            $shift->update([
                'total' => $validatedData['total'],
                'bank' => $validatedData['bank'],
                'expenses' => $validatedData['expenses'],
                'touched' => $request->input('touched', $shift->touched),
                'is_closed' => true,
                'closed_at' => $closingTime,
                // 'user_id_closed' => $closingUserId, // If you track this
            ]);

            // 2. Find and close all open DoctorShift records associated with this general Shift
            $openDoctorShifts = DoctorShift::where('shift_id', $shift->id)
                                          ->where('status', true) // Only active ones
                                          ->get();

            foreach ($openDoctorShifts as $doctorShift) {
                $doctorShift->update([
                    'status' => false,
                    'end_time' => $closingTime, // Set their end time to the general shift's closing time
                    // Optionally, if a different user is closing, or log who closed it.
                    // This assumes the DoctorShift model doesn't have a specific 'closed_by_user_id'.
                    // If it does, you might want to update that too.
                ]);
            }
            
            // TODO: Add logic for financial reconciliation here if needed.
            // E.g., compare system calculated totals with manually entered totals.
            // The 'touched' flag could be set if there's a discrepancy or manual override.

            // TODO: Potentially trigger events, like generating end-of-shift reports.
            // event(new ClinicShiftClosed($shift));

            DB::commit();

            return new ShiftResource($shift->loadMissing(['doctorShifts', /* other relations for resource */]));

        } catch (\Exception $e) {
            DB::rollBack();
            // Log the error: Log::error('Failed to close clinic shift and associated doctor shifts: ' . $e->getMessage());
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