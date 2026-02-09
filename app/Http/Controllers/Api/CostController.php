<?php
// app/Http/Controllers/Api/CostController.php
// php artisan make:controller Api/CostController --api --model=Cost
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cost;
use App\Models\CostCategory; // For dropdown
use App\Models\Shift;      // To ensure shift is open
use Illuminate\Http\Request;
use App\Http\Resources\CostResource; // Create this
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CostController extends Controller
{
    public function __construct()
    {
        // $this->middleware('can:record costs')->only('store');
        // $this->middleware('can:list costs')->only('index');
    }

    // For dropdown in the dialog
    public function getCostCategories()
    {
        // $this->authorize('view cost_categories');
        return \App\Http\Resources\CostCategoryResource::collection(CostCategory::orderBy('name')->get());
    }

    public function store(Request $request)
    {
        // $this->authorize('create', Cost::class);
        $validated = $request->validate([
            'shift_id' => ['required', 'integer', 'exists:shifts,id', Rule::exists('shifts', 'id')->where('is_closed', false)],
            'cost_category_id' => 'nullable|integer|exists:cost_categories,id',
            'doctor_shift_id' => 'nullable|integer|exists:doctor_shifts,id',
            'description' => 'required|string|max:255',
            'comment' => 'nullable|string|max:255',
            'amount_cash_input' => 'required_without:amount_bank|nullable|numeric|min:0', // Amount from cash
            'amount_bank_input' => 'required_without:amount_cash|nullable|numeric|min:0', // Amount from bank
            'doctor_shift_id_for_sub_cost' => 'nullable|integer|exists:doctor_shifts,id',
            'sub_service_cost_id' => 'nullable|integer|exists:sub_service_costs,id',
        ]);
    
        // Ensure at least one amount is provided and not both zero if one is required
        if (($validated['amount_cash_input'] ?? 0) <= 0 && ($validated['amount_bank_input'] ?? 0) <= 0) {
            throw ValidationException::withMessages(['amount_cash_input' => 'At least one amount (cash or bank) must be greater than zero.']);
        }
    
        $cost = Cost::create([
            'shift_id' => $validated['shift_id'],
            'user_cost' => Auth::id(),
            'cost_category_id' => $validated['cost_category_id'] ?? null,
            'doctor_shift_id' => $validated['doctor_shift_id'] ?? null,
            'doctor_shift_id_for_sub_cost' => $validated['doctor_shift_id_for_sub_cost'] ?? null,
            'sub_service_cost_id' => $validated['sub_service_cost_id'] ?? null,
            'description' => $validated['description'],
            'comment' => $validated['comment'] ?? null,
            'amount' => $validated['amount_cash_input'] ?? 0,       // Store cash portion
            'amount_bankak' => $validated['amount_bank_input'] ?? 0, // Store bank portion
        ]);
        return new CostResource($cost->load(['costCategory', 'userCost:id,name']));
    }
    public function index(Request $request)
    {
        // Permission check: e.g., can('list costs') or can('view cost_report')
        // if (!Auth::user()->can('list costs')) { ... }

        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'cost_category_id' => 'nullable|integer|exists:cost_categories,id',
            'user_cost_id' => 'nullable|integer|exists:users,id', // User who recorded
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'doctor_shift_id_for_sub_cost' => 'nullable|integer|exists:doctor_shifts,id',
            'payment_method' => 'nullable|string|in:cash,bank', // 'cash' or 'bank'
            'search_description' => 'nullable|string|max:255',
            'sort_by' => 'nullable|string|in:created_at,amount,description',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = Cost::with(['costCategory:id,name', 'userCost:id,name', 'shift:id', 'doctorShift.doctor:id,name']); // Eager load

        // Apply all filters to the main query
        if ($request->filled('date_from')) { $query->whereDate('created_at', '>=', Carbon::parse($request->date_from)->startOfDay()); }
        if ($request->filled('date_to')) { $query->whereDate('created_at', '<=', Carbon::parse($request->date_to)->endOfDay()); }
        if ($request->filled('cost_category_id')) { $query->where('cost_category_id', $request->cost_category_id); }
        if ($request->filled('user_cost_id')) { $query->where('user_cost', $request->user_cost_id); }
        if ($request->filled('shift_id')) { $query->where('shift_id', $request->shift_id); }
        if ($request->filled('doctor_shift_id_for_sub_cost')) { $query->where('doctor_shift_id_for_sub_cost', $request->doctor_shift_id_for_sub_cost); }
        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            if ($request->payment_method === 'cash') { $query->where('amount', '>', 0)->where('amount_bankak', '=', 0); }
            elseif ($request->payment_method === 'bank') { $query->where('amount_bankak', '>', 0)->where('amount', '=', 0); }
            elseif ($request->payment_method === 'mixed') { $query->where('amount', '>', 0)->where('amount_bankak', '>', 0); }
        }
        if ($request->filled('search_description')) { $query->where('description', 'LIKE', '%' . $request->search_description . '%'); }

        if ($request->input('sort_by') === 'total_cost') {
            $query->orderByRaw('(amount + amount_bankak) ' . ($request->input('sort_direction', 'desc')));
        } else {
            $query->orderBy($request->input('sort_by', 'created_at'), $request->input('sort_direction', 'desc'));
        }

        $perPage = $request->input('per_page', 15);
        $costs = $query->paginate($perPage);

        // Corrected Summary Calculation - Clone the main query for summary
        $summaryQuery = clone $query;
        $summaryQuery->getQuery()->orders = null; // Remove ordering for summary calculation


        $summaryTotals = $summaryQuery->selectRaw('SUM(amount) as total_cash_paid, SUM(amount_bankak) as total_bank_paid, SUM(amount + amount_bankak) as grand_total_paid')
                                 ->first();

        return CostResource::collection($costs)->additional(['meta' => [
            'summary' => [
                'total_cash_paid' => (float)($summaryTotals->total_cash_paid ?? 0),
                'total_bank_paid' => (float)($summaryTotals->total_bank_paid ?? 0),
                'grand_total_paid' => (float)($summaryTotals->grand_total_paid ?? 0),
            ]
        ]]);
    }
    
    public function destroy($id)
    {
        $cost = Cost::findOrFail($id);
        
        // Check if user can delete this cost (optional authorization)
        // $this->authorize('delete', $cost);
        
        $cost->delete();
        
        return response()->json(['message' => 'Cost deleted successfully'], 200);
    }

    /**
     * Get costs grouped by day for a given month/year.
     */
    public function costsByDay(Request $request)
    {
        $data = $this->getCostsByDayData($request);
        return response()->json($data);
    }

    /**
     * Helper method to get costs by day data.
     */
    private function getCostsByDayData(Request $request)
    {
        $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2020|max:2100',
        ]);

        $month = $request->input('month');
        $year = $request->input('year');

        // Get start and end dates for the month
        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();

        // Get costs grouped by day
        $dailyCosts = Cost::selectRaw('
                DATE(created_at) as date,
                SUM(amount) as total_cash,
                SUM(amount_bankak) as total_bank,
                SUM(amount + amount_bankak) as total_cost,
                COUNT(*) as transactions_count
            ')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Calculate month totals
        $monthTotals = [
            'total_cash' => $dailyCosts->sum('total_cash'),
            'total_bank' => $dailyCosts->sum('total_bank'),
            'total_cost' => $dailyCosts->sum('total_cost'),
            'transactions_count' => $dailyCosts->sum('transactions_count'),
        ];

        return [
            'data' => $dailyCosts,
            'summary' => $monthTotals,
            'report_period' => [
                'month' => $month,
                'year' => $year,
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
        ];
    }

    /**
     * Generate PDF for daily costs report.
     */
    public function costsByDayPdf(Request $request)
    {
        $data = $this->getCostsByDayData($request);
        
        $arabicMonths = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $monthName = $arabicMonths[$data['report_period']['month'] - 1];
        $year = $data['report_period']['year'];

        $pdf = new \App\Services\Pdf\Pdf('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Jawda Medical System');
        $pdf->SetAuthor('Jawda');
        $pdf->SetTitle("تقرير المصروفات اليومية - {$monthName} {$year}");
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->AddPage();

        // Title
        $pdf->SetFont('dejavusans', 'B', 16);
        $pdf->Cell(0, 10, "تقرير المصروفات اليومية", 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 12);
        $pdf->Cell(0, 8, "{$monthName} {$year}", 0, 1, 'C');
        $pdf->Ln(5);

        // Table header
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetFillColor(41, 128, 185);
        $pdf->SetTextColor(255, 255, 255);
        
        $colWidths = [25, 50, 35, 35, 35];
        $headers = ['اليوم', 'التاريخ', 'إجمالي المصروفات', 'كاش', 'بنك'];
        
        foreach ($headers as $i => $header) {
            $pdf->Cell($colWidths[$i], 8, $header, 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Table body
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetTextColor(0, 0, 0);
        $fill = false;
        
        foreach ($data['data'] as $row) {
            $pdf->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
            $dayNum = Carbon::parse($row->date)->day;
            $dateFormatted = Carbon::parse($row->date)->format('Y-m-d');
            
            $pdf->Cell($colWidths[0], 7, $dayNum, 1, 0, 'C', true);
            $pdf->Cell($colWidths[1], 7, $dateFormatted, 1, 0, 'C', true);
            $pdf->Cell($colWidths[2], 7, number_format($row->total_cost, 2), 1, 0, 'C', true);
            $pdf->Cell($colWidths[3], 7, number_format($row->total_cash, 2), 1, 0, 'C', true);
            $pdf->Cell($colWidths[4], 7, number_format($row->total_bank, 2), 1, 0, 'C', true);
            $pdf->Ln();
            $fill = !$fill;
        }

        // Table footer (totals)
        $pdf->SetFont('dejavusans', 'B', 10);
        $pdf->SetFillColor(200, 200, 200);
        $pdf->Cell($colWidths[0] + $colWidths[1], 8, 'الإجمالي', 1, 0, 'C', true);
        $pdf->Cell($colWidths[2], 8, number_format($data['summary']['total_cost'], 2), 1, 0, 'C', true);
        $pdf->Cell($colWidths[3], 8, number_format($data['summary']['total_cash'], 2), 1, 0, 'C', true);
        $pdf->Cell($colWidths[4], 8, number_format($data['summary']['total_bank'], 2), 1, 0, 'C', true);

        return response($pdf->Output('S'), 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"Daily_Costs_{$year}_{$data['report_period']['month']}.pdf\"");
    }

    /**
     * Generate Excel for daily costs report.
     */
    public function costsByDayExcel(Request $request)
    {
        $data = $this->getCostsByDayData($request);
        
        $arabicMonths = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
        $monthName = $arabicMonths[$data['report_period']['month'] - 1];
        $year = $data['report_period']['year'];
        $month = $data['report_period']['month'];

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);

        // Title
        $sheet->setCellValue('A1', "تقرير المصروفات اليومية - {$monthName} {$year}");
        $sheet->mergeCells('A1:E1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Headers
        $headers = ['اليوم', 'التاريخ', 'إجمالي المصروفات', 'كاش', 'بنك'];
        $col = 'A';
        foreach ($headers as $header) {
            $sheet->setCellValue($col . '3', $header);
            $sheet->getStyle($col . '3')->getFont()->setBold(true);
            $sheet->getStyle($col . '3')->getFill()
                ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                ->getStartColor()->setARGB('FF2980B9');
            $sheet->getStyle($col . '3')->getFont()->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle($col . '3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $col++;
        }

        // Data rows
        $row = 4;
        foreach ($data['data'] as $item) {
            $dayNum = Carbon::parse($item->date)->day;
            $dateFormatted = Carbon::parse($item->date)->format('Y-m-d');
            
            $sheet->setCellValue('A' . $row, $dayNum);
            $sheet->setCellValue('B' . $row, $dateFormatted);
            $sheet->setCellValue('C' . $row, $item->total_cost);
            $sheet->setCellValue('D' . $row, $item->total_cash);
            $sheet->setCellValue('E' . $row, $item->total_bank);
            
            // Center align all cells
            $sheet->getStyle("A{$row}:E{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $row++;
        }

        // Totals row
        $sheet->setCellValue('A' . $row, 'الإجمالي');
        $sheet->mergeCells("A{$row}:B{$row}");
        $sheet->setCellValue('C' . $row, $data['summary']['total_cost']);
        $sheet->setCellValue('D' . $row, $data['summary']['total_cash']);
        $sheet->setCellValue('E' . $row, $data['summary']['total_bank']);
        $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $sheet->getStyle("A{$row}:E{$row}")->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFC8C8C8');
        $sheet->getStyle("A{$row}:E{$row}")->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);

        // Auto-size columns
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Add borders
        $lastRow = $row;
        $sheet->getStyle("A3:E{$lastRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(\PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN);

        // Format number columns
        $sheet->getStyle("C4:E{$lastRow}")->getNumberFormat()
            ->setFormatCode('#,##0.00');

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $filename = "Daily_Costs_{$year}_{$month}.xlsx";

        ob_start();
        $writer->save('php://output');
        $content = ob_get_clean();

        return response($content, 200)
            ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
            ->header('Content-Disposition', "attachment; filename=\"{$filename}\"");
    }

}
