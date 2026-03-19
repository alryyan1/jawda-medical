<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index()
    {
        return Employee::with('department')->where('is_active', true)->orderBy('name')->get();
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'fixed_amount' => 'required|numeric',
            'job_title' => 'nullable|string',
            'department_id' => 'nullable|exists:departments,id',
        ]);

        $employee = Employee::create($validated);
        return response()->json($employee->load('department'), 201);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string',
            'job_title' => 'sometimes|nullable|string',
            'department_id' => 'sometimes|nullable|exists:departments,id',
            'fixed_amount' => 'sometimes|required|numeric',
            'is_active' => 'sometimes|required|boolean',
        ]);

        $employee->update($validated);

        return response()->json($employee->load('department'));
    }

    public function destroy(Employee $employee)
    {
        $employee->update(['is_active' => false]);
        return response()->json(['message' => 'Employee deactivated successfully']);
    }
}
