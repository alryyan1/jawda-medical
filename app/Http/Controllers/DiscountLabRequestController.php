<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DiscountLabRequestController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'patient_id' => 'required|exists:patients,id',
        ]);

        $discountLabRequest = \App\Models\DiscountLabRequest::create([
            'patient_id' => $validated['patient_id'],
            'is_approved' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Discount lab request created successfully',
            'data' => $discountLabRequest
        ], 201);
    }
}
