<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabRequest;
use App\Models\ReturnedLabRequest;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReturnedLabRequestController extends Controller
{
    /**
     * Record a refund for a lab request.
     */
    public function store(Request $request, LabRequest $labrequest)
    {
        if (! Auth::id()) {
            return response()->json(['message' => 'يجب تسجيل الدخول لتسجيل الاسترداد.'], 401);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'returned_payment_method' => 'required|in:cash,bank',
            'return_reason' => 'required|string|max:255',
        ]);

        $amountPaid = (float) $labrequest->amount_paid;
        $totalRefunded = (float) $labrequest->returnedRefunds()->sum('amount');
        $maxRefundable = $amountPaid - $totalRefunded;

        if ($validated['amount'] > $maxRefundable) {
            return response()->json([
                'message' => 'المبلغ المسترد يتجاوز المبلغ المدفوع المتاح للاسترداد. (المتاح: ' . round($maxRefundable, 2) . ')',
            ], 422);
        }

        $currentShift = Shift::open()->latest('created_at')->first();

        $refund = ReturnedLabRequest::create([
            'lab_request_id' => $labrequest->id,
            'amount' => $validated['amount'],
            'returned_payment_method' => $validated['returned_payment_method'],
            'return_reason' => $validated['return_reason'] ?? null,
            'user_id' => Auth::id(),
            'shift_id' => $currentShift?->id,
        ]);

        return response()->json([
            'message' => 'تم تسجيل الاسترداد بنجاح',
            'data' => $refund->load('user:id,name'),
        ], 201);
    }

    /**
     * Update a refund for a lab request.
     */
    public function update(Request $request, ReturnedLabRequest $returnedLabRequest)
    {
        if (! Auth::id()) {
            return response()->json(['message' => 'يجب تسجيل الدخول لتعديل الاسترداد.'], 401);
        }

        $validated = $request->validate([
            'returned_payment_method' => 'required|in:cash,bank',
        ]);

        $returnedLabRequest->update([
            'returned_payment_method' => $validated['returned_payment_method'],
        ]);

        return response()->json([
            'message' => 'تم تحديث الاسترداد بنجاح',
            'data' => $returnedLabRequest->load('user:id,name'),
        ]);
    }
}
