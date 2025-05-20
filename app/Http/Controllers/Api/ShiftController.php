<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Shift;
use Illuminate\Http\Request;
use App\Http\Resources\ShiftResource;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
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
            'pharmacy_entry' => $request->input('pharmacy_entry', null),
            // 'user_id_opened' => Auth::id(), // Or passed in request
            // 'name' => $validatedData['name'] ?? 'Shift - ' . Carbon::now()->toDateTimeString(),
            'total' => 0, // Initial values
            'bank' => 0,
            'expenses' => 0,
        ]);

        return new ShiftResource($shift);
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
     * This action would typically involve financial reconciliation.
     */
    public function closeShift(Request $request, Shift $shift)
    {
        if ($shift->is_closed) {
            return response()->json(['message' => 'وردية العمل هذه مغلقة بالفعل.'], 400);
        }

        // Validate financials provided at closing time
        $validatedData = $request->validate([
            'total' => 'required|numeric|min:0',
            'bank' => 'required|numeric|min:0',
            'expenses' => 'required|numeric|min:0',
            'touched' => 'sometimes|boolean',
            // 'user_id_closed' => 'required|exists:users,id', // If tracking who closed
        ]);

        // TODO: Add logic for financial reconciliation here if needed.
        // E.g., compare system calculated totals with manually entered totals.
        // The 'touched' flag could be set if there's a discrepancy or manual override.

        $shift->update([
            'total' => $validatedData['total'],
            'bank' => $validatedData['bank'],
            'expenses' => $validatedData['expenses'],
            'touched' => $request->input('touched', $shift->touched), // Keep existing if not provided
            'is_closed' => true,
            'closed_at' => Carbon::now(),
            // 'user_id_closed' => Auth::id(), // Or passed in request
        ]);

        // TODO: Potentially trigger events, like generating end-of-shift reports.

        return new ShiftResource($shift);
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