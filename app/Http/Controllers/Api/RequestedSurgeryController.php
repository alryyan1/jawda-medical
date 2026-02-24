<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\RequestedSurgery;
use App\Models\RequestedSurgeryFinance;
use App\Models\SurgicalOperation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RequestedSurgeryController extends Controller
{
    public function index(Admission $admission)
    {
        $surgeries = $admission->requestedSurgeries()
            ->with([
                'surgery',
                'doctor',
                'user',
                'finances.financeCharge',
            ])
            ->get();

        return response()->json($surgeries);
    }

    public function store(Request $request, Admission $admission)
    {
        $validated = $request->validate([
            'surgery_id' => 'required|exists:surgical_operations,id',
            'doctor_id'  => 'nullable|exists:doctors,id',
        ]);

        /** @var SurgicalOperation $surgicalOperation */
        $surgicalOperation = SurgicalOperation::with('charges')->findOrFail($validated['surgery_id']);

        DB::beginTransaction();
        try {
            // 1. Create the requested surgery record
            $requestedSurgery = RequestedSurgery::create([
                'admission_id' => $admission->id,
                'surgery_id'   => $surgicalOperation->id,
                'price'        => $surgicalOperation->price,
                'doctor_id'    => $validated['doctor_id'] ?? null,
                'user_id'      => Auth::id(),
            ]);

            // 2. Resolve and insert finance charges
            $charges = $surgicalOperation->charges;

            // Build a map of charge_id -> calculated amount so percentage-of-charge works
            $calculatedAmounts = [];

            // First, process fixed & percentage-of-total charges
            foreach ($charges as $charge) {
                if ($charge->type === 'fixed') {
                    $calculatedAmounts[$charge->id] = $charge->amount;
                } elseif ($charge->type === 'percentage' && $charge->reference_type === 'total') {
                    $calculatedAmounts[$charge->id] = ($charge->amount / 100) * $surgicalOperation->price;
                }
                // percentage-of-charge charges are processed in a second pass
            }

            // Second pass: percentage-of-charge (depends on another charge amount)
            foreach ($charges as $charge) {
                if ($charge->type === 'percentage' && $charge->reference_type === 'charge') {
                    $refId = $charge->reference_charge_id;
                    $refAmount = $calculatedAmounts[$refId] ?? 0;
                    $calculatedAmounts[$charge->id] = ($charge->amount / 100) * $refAmount;
                }
            }

            // Insert all finance records
            foreach ($charges as $charge) {
                RequestedSurgeryFinance::create([
                    'requested_surgery_id' => $requestedSurgery->id,
                    'admission_id'         => $admission->id,
                    'surgery_id'           => $surgicalOperation->id,
                    'finance_charge_id'    => $charge->id,
                    'amount'               => $calculatedAmounts[$charge->id] ?? 0,
                ]);
            }

            DB::commit();

            return response()->json(
                $requestedSurgery->load(['surgery', 'doctor', 'user', 'finances.financeCharge']),
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'حدث خطأ أثناء طلب العملية', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Admission $admission, RequestedSurgery $requestedSurgery)
    {
        if ($requestedSurgery->admission_id !== $admission->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $requestedSurgery->delete();
        return response()->json(null, 204);
    }

    public function updateFinance(Request $request, RequestedSurgeryFinance $requestedSurgeryFinance)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        $requestedSurgeryFinance->update(['amount' => $validated['amount']]);

        return response()->json($requestedSurgeryFinance->load('financeCharge'));
    }
}
