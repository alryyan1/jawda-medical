<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionDose;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdmissionDoseController extends Controller
{
    /**
     * Display a listing of doses for an admission.
     */
    public function index(Request $request, $admissionId)
    {
        $admission = Admission::findOrFail($admissionId);
        $query = $admission->doses()->with(['doctor', 'user']);

        // Filter by active status if requested
        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $doses = $query->orderBy('start_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['data' => $doses]);
    }

    /**
     * Store a newly created dose.
     */
    public function store(Request $request, $admissionId)
    {
        $admission = Admission::findOrFail($admissionId);

        $validatedData = $request->validate([
            'medication_name' => 'required|string|max:255',
            'dosage' => 'nullable|string|max:255',
            'frequency' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'instructions' => 'nullable|string',
            'notes' => 'nullable|string',
            'doctor_id' => 'nullable|exists:doctors,id',
            'is_active' => 'nullable|boolean',
        ]);

        $validatedData['admission_id'] = $admissionId;
        $validatedData['user_id'] = Auth::id();

        if (!isset($validatedData['is_active'])) {
            $validatedData['is_active'] = true;
        }

        if (empty($validatedData['start_date'])) {
            $validatedData['start_date'] = now()->toDateString();
        }

        $dose = AdmissionDose::create($validatedData);

        return response()->json(['data' => $dose->load(['doctor', 'user'])], 201);
    }

    /**
     * Display the specified dose.
     */
    public function show($admissionId, $id)
    {
        $dose = AdmissionDose::where('admission_id', $admissionId)
            ->findOrFail($id);

        return response()->json(['data' => $dose->load(['doctor', 'user'])]);
    }

    /**
     * Update the specified dose.
     */
    public function update(Request $request, $admissionId, $id)
    {
        $dose = AdmissionDose::where('admission_id', $admissionId)
            ->findOrFail($id);

        $validatedData = $request->validate([
            'medication_name' => 'sometimes|required|string|max:255',
            'dosage' => 'nullable|string|max:255',
            'frequency' => 'nullable|string|max:255',
            'route' => 'nullable|string|max:255',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'instructions' => 'nullable|string',
            'notes' => 'nullable|string',
            'doctor_id' => 'nullable|exists:doctors,id',
            'is_active' => 'nullable|boolean',
        ]);

        $dose->update($validatedData);

        return response()->json(['data' => $dose->load(['doctor', 'user'])]);
    }

    /**
     * Remove the specified dose.
     */
    public function destroy($admissionId, $id)
    {
        $dose = AdmissionDose::where('admission_id', $admissionId)
            ->findOrFail($id);

        $dose->delete();

        return response()->json(['message' => 'تم حذف الجرعة بنجاح']);
    }
}
