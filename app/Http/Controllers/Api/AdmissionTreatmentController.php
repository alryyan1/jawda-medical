<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionTreatment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdmissionTreatmentController extends Controller
{
    /**
     * Display a listing of treatments for an admission.
     */
    public function index(Request $request, $admissionId)
    {
        $admission = Admission::findOrFail($admissionId);
        $treatments = $admission->treatments()->with('user')->orderBy('treatment_date', 'desc')
            ->orderBy('treatment_time', 'desc')
            ->get();

        return response()->json(['data' => $treatments]);
    }

    /**
     * Store a newly created treatment.
     */
    public function store(Request $request, $admissionId)
    {
        $admission = Admission::findOrFail($admissionId);

        $validatedData = $request->validate([
            'treatment_plan' => 'nullable|string',
            'treatment_details' => 'nullable|string',
            'notes' => 'nullable|string',
            'treatment_date' => 'nullable|date',
            'treatment_time' => 'nullable|date_format:H:i:s',
        ]);

        $validatedData['admission_id'] = $admissionId;
        $validatedData['user_id'] = Auth::id();

        if (empty($validatedData['treatment_date'])) {
            $validatedData['treatment_date'] = now()->toDateString();
        }

        $treatment = AdmissionTreatment::create($validatedData);

        return response()->json(['data' => $treatment->load('user')], 201);
    }

    /**
     * Display the specified treatment.
     */
    public function show($admissionId, $id)
    {
        $treatment = AdmissionTreatment::where('admission_id', $admissionId)
            ->findOrFail($id);

        return response()->json(['data' => $treatment->load('user')]);
    }

    /**
     * Update the specified treatment.
     */
    public function update(Request $request, $admissionId, $id)
    {
        $treatment = AdmissionTreatment::where('admission_id', $admissionId)
            ->findOrFail($id);

        $validatedData = $request->validate([
            'treatment_plan' => 'nullable|string',
            'treatment_details' => 'nullable|string',
            'notes' => 'nullable|string',
            'treatment_date' => 'nullable|date',
            'treatment_time' => 'nullable|date_format:H:i:s',
        ]);

        $treatment->update($validatedData);

        return response()->json(['data' => $treatment->load('user')]);
    }

    /**
     * Remove the specified treatment.
     */
    public function destroy($admissionId, $id)
    {
        $treatment = AdmissionTreatment::where('admission_id', $admissionId)
            ->findOrFail($id);

        $treatment->delete();

        return response()->json(['message' => 'تم حذف بيانات العلاج بنجاح']);
    }
}
