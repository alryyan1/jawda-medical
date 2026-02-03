<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Admission;
use App\Models\AdmissionNursingAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdmissionNursingAssignmentController extends Controller
{
    /**
     * Display a listing of nursing assignments for an admission.
     */
    public function index(Request $request, $admissionId)
    {
        $admission = Admission::findOrFail($admissionId);
        $query = $admission->nursingAssignments()->with(['user', 'assignedBy']);

        // Filter by status if requested
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $assignments = $query->orderBy('due_date', 'asc')
            ->orderBy('due_time', 'asc')
            ->get();

        return response()->json(['data' => $assignments]);
    }

    /**
     * Store a newly created assignment.
     */
    public function store(Request $request, $admissionId)
    {
        $admission = Admission::findOrFail($admissionId);

        $validatedData = $request->validate([
            'assignment_description' => 'required|string',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i:s',
            'notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id', // الممرض المسؤول
        ]);

        $validatedData['admission_id'] = $admissionId;
        $validatedData['assigned_by_user_id'] = Auth::id();

        // If user_id not provided, use current user
        if (empty($validatedData['user_id'])) {
            $validatedData['user_id'] = Auth::id();
        }

        if (empty($validatedData['priority'])) {
            $validatedData['priority'] = 'medium';
        }

        if (empty($validatedData['status'])) {
            $validatedData['status'] = 'pending';
        }

        $assignment = AdmissionNursingAssignment::create($validatedData);

        return response()->json(['data' => $assignment->load(['user', 'assignedBy'])], 201);
    }

    /**
     * Display the specified assignment.
     */
    public function show($admissionId, $id)
    {
        $assignment = AdmissionNursingAssignment::where('admission_id', $admissionId)
            ->findOrFail($id);

        return response()->json(['data' => $assignment->load(['user', 'assignedBy'])]);
    }

    /**
     * Update the specified assignment.
     */
    public function update(Request $request, $admissionId, $id)
    {
        $assignment = AdmissionNursingAssignment::where('admission_id', $admissionId)
            ->findOrFail($id);

        $validatedData = $request->validate([
            'assignment_description' => 'sometimes|required|string',
            'priority' => 'nullable|in:low,medium,high',
            'status' => 'nullable|in:pending,in_progress,completed,cancelled',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable|date_format:H:i:s',
            'completed_date' => 'nullable|date',
            'completed_time' => 'nullable|date_format:H:i:s',
            'notes' => 'nullable|string',
            'completion_notes' => 'nullable|string',
            'user_id' => 'nullable|exists:users,id',
        ]);

        // If status is being set to completed, set completion date/time
        if (isset($validatedData['status']) && $validatedData['status'] === 'completed') {
            if (empty($validatedData['completed_date'])) {
                $validatedData['completed_date'] = now()->toDateString();
            }
            if (empty($validatedData['completed_time'])) {
                $validatedData['completed_time'] = now()->format('H:i:s');
            }
        }

        $assignment->update($validatedData);

        return response()->json(['data' => $assignment->load(['user', 'assignedBy'])]);
    }

    /**
     * Remove the specified assignment.
     */
    public function destroy($admissionId, $id)
    {
        $assignment = AdmissionNursingAssignment::where('admission_id', $admissionId)
            ->findOrFail($id);

        $assignment->delete();

        return response()->json(['message' => 'تم حذف المهمة التمريضية بنجاح']);
    }
}
