<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmployeeExpense;
use App\Models\Shift;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\Pdf\EmployeeExpensesReport;
use Carbon\Carbon;

class EmployeeExpenseController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', Carbon::today()->toDateString());
        
        $expenses = EmployeeExpense::with(['employee', 'recordedBy:id,name'])
            ->whereDate('date', $date)
            ->orderBy('created_at', 'desc')
            ->get();
            
        return response()->json($expenses);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'amount' => 'required|numeric|min:0',
            'cash_amount' => 'required|numeric|min:0',
            'bank_amount' => 'required|numeric|min:0',
            'date' => 'required|date',
        ]);

        $expense = EmployeeExpense::create([
            ...$validated,
            'shift_id' => $request->user()->current_shift_id ?? Shift::where('is_closed', false)->first()?->id,
            'user_id' => $request->user()->id,
        ]);

        return response()->json($expense->load('employee'), 201);
    }

    public function destroy(EmployeeExpense $employeeExpense)
    {
        $employeeExpense->delete();
        return response()->json(['message' => 'Expense deleted successfully']);
    }

    public function printPdf(Request $request, EmployeeExpensesReport $report)
    {
        try {
            $pdfContent = $report->generate($request);
            return response($pdfContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'inline; filename="EmployeeExpenses_Report.pdf"');
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }
}
