<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class StandardSurgicalChargeController extends Controller
{
    public function index()
    {
        return \App\Models\StandardSurgicalCharge::all();
    }

    public function import(\App\Models\SurgicalOperation $surgicalOperation)
    {
        $standardCharges = \App\Models\StandardSurgicalCharge::all();

        foreach ($standardCharges as $standardCharge) {
            // Check if charge already exists by name for this operation to avoid duplicates if needed
            // But usually import means bring them all.
            $surgicalOperation->charges()->create([
                'name' => $standardCharge->name,
                'beneficiary' => $standardCharge->type, // Map 'type' from standard to 'beneficiary' in operation charges
                'type' => 'fixed', // Default type for imported charges
                'amount' => 0, // Default amount for imported charges
            ]);
        }

        return response()->json($surgicalOperation->charges()->get());
    }
}
