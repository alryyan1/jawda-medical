<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index()
    {
        return response()->json(Employee::where('is_active', true)->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'job_title' => 'nullable|string|max:255',
            'department' => 'nullable|string|max:255',
            'fixed_amount' => 'required|numeric|min:0',
        ]);

        $employee = Employee::create($validated);

        return response()->json($employee, 201);
    }

    public function update(Request $request, Employee $employee)
    {
        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'job_title' => 'sometimes|nullable|string|max:255',
            'department' => 'sometimes|nullable|string|max:255',
            'fixed_amount' => 'sometimes|required|numeric|min:0',
            'is_active' => 'sometimes|required|boolean',
        ]);

        $employee->update($validated);

        return response()->json($employee);
    }

    public function destroy(Employee $employee)
    {
        $employee->update(['is_active' => false]);
        return response()->json(['message' => 'Employee deactivated successfully']);
    }
}
