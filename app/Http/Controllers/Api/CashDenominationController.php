<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deno;
use App\Models\DenoUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CashDenominationController extends Controller
{
    /**
     * Fetch all denominations and any existing counts for the user's current shift.
     */
    public function getDenominationsForShift(Request $request)
    {
        $request->validate(['shift_id' => 'required|integer|exists:shifts,id']);
        $shiftId = $request->shift_id;
        $userId = Auth::id();

        $denominations = Deno::orderBy('id','desc')
            ->whereNotIn('name', [10, 20, 50])
            ->get();

        // Get existing counts for this user and shift
        $existingCounts = DenoUser::where('user_id', $userId)
            ->where('shift_id', $shiftId)
            ->pluck('count', 'deno_id'); // Keyed by deno_id for easy lookup

        $data = $denominations->map(function ($deno) use ($existingCounts) {
            return [
                'id' => $deno->id,
                'name' => $deno->name,
                'count' => $existingCounts->get($deno->id, 0), // Default to 0 if not found
            ];
        });

        return response()->json(['data' => $data]);
    }

    /**
     * Save or update the denomination counts for a user's shift.
     */
    public function saveDenominationCounts(Request $request)
    {
        $validated = $request->validate([
            'shift_id' => 'required|integer|exists:shifts,id',
            'counts' => 'required|array',
            'counts.*.id' => 'required|integer|exists:denos,id', // This is deno_id
            'counts.*.count' => 'required|integer|min:0',
        ]);

        $shiftId = $validated['shift_id'];
        $userId = Auth::id();
        
        DB::beginTransaction();
        try {
            foreach ($validated['counts'] as $item) {
                DenoUser::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'shift_id' => $shiftId,
                        'deno_id' => $item['id'],
                    ],
                    [
                        'count' => $item['count'],
                    ]
                );
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Failed to save denomination counts.'], 500);
        }

        return response()->json(['message' => 'Denomination counts saved successfully.']);
    }
}