<?php

namespace App\Http\Controllers\Api;

// app/Http/Controllers/Api/ReportController.php

use App\Http\Controllers\Controller;
use App\Http\Resources\LabTestStatisticResource;
use App\Models\Service;
use App\Models\Package;
use App\Models\ServiceGroup; // For filter
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // For aggregate functions
use Carbon\Carbon;
// You might create a specific Resource for this report item if needed
use App\Http\Resources\ServiceResource; // Can be adapted or a new one created
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Cost;
use App\Models\Doctor;
use App\Models\DoctorShift;
use App\Models\DoctorVisit;
use App\Models\LabRequest;
use App\Models\MainTest;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Mypdf\Pdf;

use App\Services\Pdf\MyCustomTCPDF;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;
use App\Models\CostCategory;
use App\Models\Holiday;
use App\Models\Patient;
use App\Models\RequestedServiceCost;
use App\Models\RequestedServiceDeposit;
use App\Models\ShiftDefinition;
use App\Models\SubServiceCost;
use App\Models\Deno;
use App\Models\DenoUser;
use Illuminate\Support\Facades\Auth;
use App\Services\UltramsgService;
use App\Services\Pdf\LabResultReport;
use App\Services\Pdf\CashReconciliationReport;

class ReportController extends Controller
{
    public function generateLabInvoicePdf(\App\Models\DoctorVisit $visit)
    {
        // Ensure lab requests exist
        $visit->load(['patient', 'labRequests.mainTest']);
        $labRequests = $visit->labRequests;
        if ($labRequests->count() === 0) {
            return response()->json(['message' => 'لا توجد طلبات مختبر لهذه الزيارة'], 404);
        }

        $tests = [];
        foreach ($labRequests as $req) {
            $tests[] = [
                'name' => $req->mainTest->main_test_name ?? ('Test #' . $req->id),
                'price' => (float) $req->price,
            ];
        }

        $patientName = $visit->patient->name ?? 'Unknown';
        $hospitalName = config('app.name', 'Jawda Medical');
        $totalPaid = (float) $labRequests->sum('amount_paid');

        $invoice = new \App\Services\Pdf\LabInvoice($tests, $patientName, $hospitalName, $totalPaid);
        $pdfContent = $invoice->generate();

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="lab-invoice.pdf"'
        ]);
    }
    // ... (other report methods) ...
    protected UltramsgService $ultramsgService;
    public function __construct(UltramsgService $ultramsgService)
    {
        $this->ultramsgService = $ultramsgService;
    }
    public function serviceStatistics(Request $request)
    {
        // Permission check: e.g., can('view service_statistics_report')
        // if (!auth()->user()->can('view service_statistics_report')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'service_group_id' => 'nullable|integer|exists:service_groups,id',
            'search_service_name' => 'nullable|string|max:255',
            'sort_by' => 'nullable|string|in:name,request_count',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = Service::query()->with('serviceGroup:id,name') // Eager load service group for display
            ->select([
                'services.id',
                'services.name',
                'services.price', // Include price for context
                'services.service_group_id',
                'services.activate',
                // Count requested_services entries
                DB::raw('COUNT(requested_services.id) as request_count'),
                // Optionally, sum total revenue from this service
                // DB::raw('SUM(requested_services.price * requested_services.count) as total_revenue')
            ])
            ->leftJoin('requested_services', 'services.id', '=', 'requested_services.service_id');

        // Date range filter for requested_services
        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            // Apply date filter on the join condition or a subquery for accuracy
            // For simplicity, applying on requested_services.created_at directly in WHERE
            // This means services with no requests in the date range might still appear with count 0
            // If you only want services requested in the date range, the join condition is better.
            $query->where(function ($q) use ($dateFrom) {
                $q->where('requested_services.created_at', '>=', $dateFrom)
                    ->orWhereNull('requested_services.created_at'); // Include services with no requests at all
            });
        }
        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
            $query->where(function ($q) use ($dateTo) {
                $q->where('requested_services.created_at', '<=', $dateTo)
                    ->orWhereNull('requested_services.created_at');
            });
        }

        // Filter by service group
        if ($request->filled('service_group_id')) {
            $query->where('services.service_group_id', $request->service_group_id);
        }

        // Filter by service name (search)
        if ($request->filled('search_service_name')) {
            $query->where('services.name', 'LIKE', '%' . $request->search_service_name . '%');
        }

        $query->groupBy('services.id', 'services.name', 'services.price', 'services.service_group_id', 'services.activate'); // Must group by all selected non-aggregated columns

        // Sorting
        $sortBy = $request->input('sort_by', 'request_count'); // Default sort by request_count
        $sortDirection = $request->input('sort_direction', 'desc');
        if ($sortBy === 'name') {
            $query->orderBy('services.name', $sortDirection);
        } else { // Default to request_count or if explicitly chosen
            $query->orderBy('request_count', $sortDirection);
        }
        $query->orderBy('services.name', 'asc'); // Secondary sort by name


        $perPage = $request->input('per_page', 15);
        $statistics = $query->paginate($perPage);

        // The 'request_count' (and 'total_revenue' if added) will be available as attributes on each service model in the collection
        // We can use a simple collection resource or adapt ServiceResource if needed.
        // Using a custom transformation here for clarity.
        return $statistics;
    }

    public function doctorShiftsReportPdf(Request $request)
    {
        // if (!Auth::user()->can('print doctor_shift_reports')) { /* ... */ }

        try {
            $doctorShiftsReport = new \App\Services\Pdf\DoctorShiftsReport();
            $pdfContent = $doctorShiftsReport->generate($request);
            
            $pdfFileName = '' . date('Ymd_His') . '.pdf';
            return response($pdfContent, 200)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    private function getMonthlyAttendanceSummaryData(Request $request): array
    {
        $validated = $request->validate([
            'year' => 'required|integer|digits:4',
            'month' => 'required|integer|min:1|max:12',
            'shift_definition_id' => 'nullable|integer|exists:shifts_definitions,id',
        ]);

        $year = $validated['year'];
        $month = $validated['month'];
        $shiftDefinitionId = $validated['shift_definition_id'] ?? null;

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = $startDate->copy()->endOfMonth();

        // 1. Get all distinct user IDs that have attendance in the period/shift
        $userIdsWithAttendanceQuery = Attendance::query()
            ->whereBetween('attendance_date', [$startDate, $endDate]);

        if ($shiftDefinitionId) {
            $userIdsWithAttendanceQuery->where('shift_definition_id', $shiftDefinitionId);
        }
        
        $userIdsWithAttendance = $userIdsWithAttendanceQuery->distinct()->pluck('user_id');

        if ($userIdsWithAttendance->isEmpty()) {
            // No users had any attendance, return empty data structure
            $shiftName = $shiftDefinitionId ? ShiftDefinition::find($shiftDefinitionId)?->name : null;
            return [
                'data' => [],
                'meta' => [
                    'year' => (int)$year,
                    'month' => (int)$month,
                    'month_name' => $startDate->translatedFormat('F Y'),
                    'shift_definition_id' => $shiftDefinitionId ? (int)$shiftDefinitionId : null,
                    'shift_name' => $shiftName,
                    'total_working_days_in_month' => $this->calculateWorkingDaysInMonth($startDate, $endDate), // Helper for this
                ]
            ];
        }

        // 2. Fetch User models for these IDs
        $users = User::whereIn('id', $userIdsWithAttendance)
                     ->with('defaultShifts') // Eager load default shifts if needed for display
                     ->orderBy('name')
                     ->get(['id', 'name', 'is_supervisor']); // Select only needed columns

        $holidaysInMonth = Holiday::whereBetween('holiday_date', [$startDate, $endDate])
            ->pluck('holiday_date')->map(fn($d) => $d->format('Y-m-d'));

        $totalWorkingDaysInMonth = $this->calculateWorkingDaysInMonth($startDate, $endDate, $holidaysInMonth->all());
        
        $summaryData = [];
        foreach ($users as $user) {
            // Fetch attendance records specifically for this user and period/shift
            $userAttendanceQuery = Attendance::where('user_id', $user->id)
                ->whereBetween('attendance_date', [$startDate, $endDate]);

            if ($shiftDefinitionId) {
                $userAttendanceQuery->where('shift_definition_id', $shiftDefinitionId);
            }
            
            $userAttendanceRecords = $userAttendanceQuery->get();

            $present_days = $userAttendanceRecords->where('status', 'present')->count();
            $late_present_days = $userAttendanceRecords->where('status', 'late_present')->count();
            // According to your previous frontend, late_present also counts towards present_days for display
            $total_present_for_display = $present_days + $late_present_days;
            
            $absent_days = $userAttendanceRecords->where('status', 'absent')->count();
            $on_leave_days = $userAttendanceRecords->where('status', 'on_leave')->count();
            $sick_leave_days = $userAttendanceRecords->where('status', 'sick_leave')->count();
            $early_leave_days = $userAttendanceRecords->where('status', 'early_leave')->count();
            
            // Calculate holidays that fell on workdays for *this user*
            // This requires a more complex check against the user's actual working pattern.
            // For simplicity, if we assume all users have the same Mon-Fri pattern:
            $userHolidaysOnWorkdays = 0;
            foreach (CarbonPeriod::create($startDate, $endDate) as $dateInPeriod) {
                if (!$dateInPeriod->isWeekend() && $holidaysInMonth->contains($dateInPeriod->format('Y-m-d'))) {
                    // If this user was *not* on leave or absent for another reason on this holiday, count it.
                    // This depends on how you want to report it.
                    // If a user was on leave on a public holiday, does it count as leave or holiday?
                    // A simple approach: count holidays that were potential workdays.
                    $userHolidaysOnWorkdays++;
                }
            }
            
            // Scheduled days: total working days in month minus holidays that were workdays
            // This is a general calculation. For individual scheduled days, it would need their work pattern.
            $scheduledDays = $totalWorkingDaysInMonth; // Start with total business days
            // If a user took 'on_leave' or 'sick_leave', those are not absences from scheduled days,
            // but rather excused absences.
            // A more accurate 'total_scheduled_days' would subtract non-working days for THIS user
            // and holidays that fell on THEIR workdays.
            // The 'absent_days' would then be total_scheduled_days - present_days - leave_days - sick_days.

            $summaryData[] = [
                'user_id' => $user->id,
                'user_name' => $user->name,
                'is_supervisor' => (bool) $user->is_supervisor,
                'default_shift_label' => $user->defaultShifts->first()?->shift_label,
                'total_scheduled_days' => $scheduledDays, // This needs careful definition
                'present_days' => $total_present_for_display,
                'absent_days' => $absent_days, // Could be calculated as scheduled - (present + leaves)
                'late_present_days' => $late_present_days,
                'early_leave_days' => $early_leave_days,
                'on_leave_days' => $on_leave_days,
                'sick_leave_days' => $sick_leave_days,
                'holidays_on_workdays' => $userHolidaysOnWorkdays, // Count of holidays falling on their workdays
            ];
        }
        
        $shiftName = $shiftDefinitionId ? ShiftDefinition::find($shiftDefinitionId)?->name : null;

        return [
            'data' => $summaryData,
            'meta' => [
                'year' => (int)$year,
                'month' => (int)$month,
                'month_name' => $startDate->translatedFormat('F Y'),
                'shift_definition_id' => $shiftDefinitionId ? (int)$shiftDefinitionId : null,
                'shift_name' => $shiftName,
                'total_working_days_in_month' => $totalWorkingDaysInMonth,
            ]
        ];
    }

    // Helper function to calculate working days in a month, excluding weekends and provided holidays
    private function calculateWorkingDaysInMonth(Carbon $startDate, Carbon $endDate, array $holidayDates = []): int
    {
        $workingDays = 0;
        $period = CarbonPeriod::create($startDate, $endDate);
        foreach ($period as $date) {
            if (!$date->isWeekend() && !in_array($date->format('Y-m-d'), $holidayDates)) {
                $workingDays++;
            }
        }
        return $workingDays;
    }

    // Make sure getMonthlyAttendanceSummary calls the private helper
    public function getMonthlyAttendanceSummary(Request $request)
    {
        // if (!Auth::user()->can('view monthly_attendance_report')) { /* ... */ }
        $data = $this->getMonthlyAttendanceSummaryData($request);
        return response()->json($data);
    }

    // And generateMonthlyAttendancePdf also calls the private helper
    public function generateMonthlyAttendancePdf(Request $request)
    {
        // if (!Auth::user()->can('print monthly_attendance_report')) { /* ... */ }
        $reportContent = $this->getMonthlyAttendanceSummaryData($request);
        
        $summaryList = $reportContent['data'];
        $meta = $reportContent['meta'];

        if (empty($summaryList)) {
            // For PDF, we can generate an empty report or a message
            // For API response, a 404 or empty data is fine
             return response()->json(['message' => 'No attendance data to generate PDF for the selected criteria.'], 404);
        }

        // ... (rest of your PDF generation logic using $summaryList and $meta) ...
        $reportTitle = 'Monthly Staff Attendance Summary'; // Translate as needed
        $filterCriteria = "For: {$meta['month_name']}";
        if ($meta['shift_name']) {
            $filterCriteria .= " | Shift: {$meta['shift_name']}";
        }

        $pdf = new MyCustomTCPDF($reportTitle, $filterCriteria, 'L', 'mm', 'A4'); // Landscape
        $pdf->AddPage();
        $pdf->SetFont('helvetica', '', 8);

        $headers = [ /* ... Your headers ... */
            '#', 'Employee Name', 'Scheduled', 'Present', 'Late', 'Early Leave', 'Absent', 'On Leave', 'Sick Leave', 'Holidays'
        ];
        // ... (Define $colWidths and $aligns for these headers) ...
        // This is just an example, adjust to your needs
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [8, 55, 20, 20, 20, 22, 20, 25, 25, 0]; 
        $colWidths[count($colWidths)-1] = $pageWidth - array_sum(array_slice($colWidths,0,-1));
        $aligns = ['C', 'L', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'];


        $pdf->DrawTableHeader($headers, $colWidths, $aligns, 7);

        $fill = false;
        foreach ($summaryList as $idx => $summary) {
            $rowData = [
                $idx + 1,
                $summary['user_name'],
                $summary['total_scheduled_days'],
                $summary['present_days'],
                $summary['late_present_days'],
                $summary['early_leave_days'],
                $summary['absent_days'],
                $summary['on_leave_days'],
                $summary['sick_leave_days'],
                $summary['holidays_on_workdays'],
            ];
            $pdf->DrawTableRow($rowData, $colWidths, $aligns, $fill, 6);
            $fill = !$fill;
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
        
        // ... (Footer, output logic as before) ...
        $pdfFileName = "MonthlyAttendance_{$meta['year']}-{$meta['month']}" . ($meta['shift_name'] ? '_' . str_replace(' ', '_', $meta['shift_name']) : '') . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)->header('Content-Type', 'application/pdf')->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
    public function generatePriceListPdf(Request $request)
    {
        // Permission Check
        // if (!Auth::user()->can('print lab_price_list')) { // Or 'view lab_price_list'
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $request->validate([
            'search_service_name' => 'nullable|string|max:255',
            // Add other filters if your price list page supports them and you want them in PDF
            // 'service_group_id' => 'nullable|integer|exists:service_groups,id',
            // 'available' => 'nullable|boolean',
        ]);

        // --- Fetch Data ---
        $query = MainTest::query()
            ->where('available', true) // Typically, price lists are for available tests
            ->orderBy('main_test_name');

        $filterCriteria = [];

        if ($request->filled('search_service_name')) {
            $searchTerm = $request->search_service_name;
            $query->where('main_test_name', 'LIKE', '%' . $searchTerm . '%');
            $filterCriteria[] = "بحث: " . $searchTerm;
        }
        // Add other filters here if applicable
        // if ($request->filled('service_group_id')) { ... }

        $mainTests = $query->get(['id', 'main_test_name', 'price']); // Select only necessary columns

        if ($mainTests->isEmpty()) {
            return response()->json(['message' => 'لا توجد فحوصات لعرضها في قائمة الأسعار بناءً على الفلاتر.'], 404);
        }

        $filterCriteriaString = !empty($filterCriteria) ? implode(' | ', $filterCriteria) : "جميع الفحوصات المتاحة";

        // --- PDF Generation ---
        $pdf = new MyCustomTCPDF(
            'قائمة أسعار الفحوصات المخبرية', // Report Title
            $filterCriteriaString,             // Filters applied
            'P',                               // Orientation: Portrait for a list
            'mm',
            'A4',
            true,
            'UTF-8',
            false
        );

        $pdf->AddPage();
        $pdf->SetLineWidth(0.1);

        // --- Table Content ---
        // Define column widths for Portrait A4 (width ~210mm - margins ~20mm = ~190mm usable)
        // For a 2-column layout (Test Name | Price) repeated
        $numColumnsOnPage = 2; // Number of "Test | Price" blocks per row
        $itemBlockWidth = ($pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - (($numColumnsOnPage - 1) * 5)) / $numColumnsOnPage; // 5mm gap between blocks
        $testNameWidth = $itemBlockWidth * 0.70; // 70% for name
        $priceWidth = $itemBlockWidth * 0.30;    // 30% for price

        $cellHeight = 6;
        $headerFont = 'helvetica'; // Or $pdf->defaultFontBold
        $dataFont = 'helvetica';

        $pdf->SetFont($headerFont, 'B', 9);
        $pdf->SetFillColor(230, 230, 230);

        $currentX = $pdf->GetX();
        $currentY = $pdf->GetY();
        $columnCounter = 0;

        foreach ($mainTests as $index => $test) {
            if ($columnCounter == 0) { // Start of a new visual row of blocks
                $currentY = $pdf->GetY(); // Get Y for the start of this visual row
                $currentX = $pdf->getMargins()['left'];
                // Draw top border for the "row" of blocks if it's not the first item overall
                if ($index > 0) {
                    // $this->Line($this->getX(), $currentY, $this->getPageWidth() - $this->getMargins()['right'], $currentY);
                }
            } else {
                $currentX += $itemBlockWidth + 5; // Move to next block position with gap
            }

            // Store Y before drawing this item block to reset for next block in same visual row
            $yForItemBlock = $currentY;

            // --- Draw one item block (Test Name & Price) ---
            // Header for this item block (optional, could be just data)
            // $pdf->SetXY($currentX, $yForItemBlock);
            // $pdf->Cell($testNameWidth, $cellHeight, 'الفحص', 1, 0, 'C', true);
            // $pdf->Cell($priceWidth, $cellHeight, 'السعر', 1, 1, 'C', true); // ln=1 to ensure Y is updated
            // $yForItemBlock = $pdf->GetY();


            $pdf->SetFont($dataFont, '', 8);

            // Test Name Cell
            $pdf->SetXY($currentX, $yForItemBlock);
            $pdf->MultiCell($testNameWidth, $cellHeight, $test->main_test_name, 1, 'R', false, 0, '', '', true, 0, false, true, $cellHeight, 'M');

            // Price Cell
            $pdf->SetXY($currentX + $testNameWidth, $yForItemBlock);
            $pdf->MultiCell($priceWidth, $cellHeight, number_format((float) $test->price, 2), 1, 'C', false, 0, '', '', true, 0, false, true, $cellHeight, 'M'); // ln=0 if more blocks in this row

            $columnCounter++;

            if ($columnCounter >= $numColumnsOnPage) {
                $pdf->Ln($cellHeight); // Move to next line after completing a visual row of blocks
                $columnCounter = 0;
                // Check for page break
                if ($pdf->GetY() + $cellHeight > ($pdf->getPageHeight() - $pdf->getBreakMargin())) {
                    $pdf->AddPage(); // TCPDF will call Header()
                    $currentY = $pdf->GetY(); // Reset Y for new page
                    $pdf->SetFont($headerFont, 'B', 9); // Reset font for potential headers if you had them per block
                    $pdf->SetFillColor(230, 230, 230);
                }
            }
        }

        // If the last row was not full, ensure we move down
        if ($columnCounter != 0) {
            $pdf->Ln($cellHeight);
        }

        // --- Output PDF ---
        $pdfFileName = 'lab_price_list_' . date('Ymd_His') . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
    public function generateCompanyServiceContractPdf(Request $request, Company $company)
    {
        // Permission Check: e.g., can('print company_contracts') or can('view company_contracts')
        // if (!auth()->user()->can('print company_contracts')) { ... }

        $request->validate(['search' => 'nullable|string|max:255']);
        $searchTerm = $request->search;

        // Fetch contracted services with pivot data
        $query = $company->contractedServices()->with('serviceGroup')->orderBy('services.name'); // Order by service name
        if ($searchTerm) {
            $query->where('services.name', 'LIKE', "%{$searchTerm}%");
        }
        $contractedServices = $query->get();

        if ($contractedServices->isEmpty()) {
            return response()->json(['message' => 'لا توجد خدمات متعاقد عليها لهذه الشركة لإنشاء التقرير.'], 404);
        }

        $reportTitle = 'تقرير عقد الخدمات لشركة: ' . $company->name;
        $filterCriteriaString = $searchTerm ? "بحث: " . $searchTerm : "جميع الخدمات المتعاقد عليها";

        $pdf = new MyCustomTCPDF($reportTitle, null, 'P', 'mm', 'A4',true, 'UTF-8', false,false,$filterCriteriaString);
        $pdf->AddPage();

        $headers = ['اسم الخدمة', 'المجموعة', 'سعر العقد', 'تحمل الشركة', 'موافقة'];
        // A4 Portrait width ~190mm usable
        $colWidths = [70, 40, 25, 35, 20];
        $colWidths[count($colWidths) - 1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($colWidths, 0, -1));

        $alignments = ['R', 'R', 'C', 'C', 'C'];
        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        $fill = false;
        foreach ($contractedServices as $service) {
            $enduranceText = $service->pivot->use_static
                ? number_format((float) $service->pivot->static_endurance, 2) . ' (ثابت)'
                : number_format((float) $service->pivot->percentage_endurance, 1) . '%';

            $rowData = [
                $service->name,
                $service->serviceGroup?->name ?? '-',
                number_format((float) $service->pivot->price, 2),
                $enduranceText,
                $service->pivot->approval ? 'نعم' : 'لا',
            ];
            $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill);
            $fill = !$fill;
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());


        $pdfFileName = 'company_service_contracts_' . $company->id . '_' . date('Ymd_His') . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }

    public function generateCompanyMainTestContractPdf(Request $request, Company $company)
    {
        // Permission Check
        // if (!auth()->user()->can('print company_contracts')) { ... }

        $request->validate(['search' => 'nullable|string|max:255']);
        $searchTerm = $request->search;

        $query = $company->contractedMainTests()->with('container')->orderBy('main_tests.main_test_name');
        if ($searchTerm) {
            $query->where('main_tests.main_test_name', 'LIKE', "%{$searchTerm}%");
        }
        $contractedTests = $query->get();

        if ($contractedTests->isEmpty()) {
            return response()->json(['message' => 'لا توجد فحوصات متعاقد عليها لهذه الشركة لإنشاء التقرير.'], 404);
        }

        $reportTitle = 'تقرير عقد الفحوصات لشركة: ' . $company->name;
        $filterCriteriaString = $searchTerm ? "بحث: " . $searchTerm : "جميع الفحوصات المتعاقد عليها";

        $pdf = new MyCustomTCPDF($reportTitle, null, 'P', 'mm', 'A4',true, 'UTF-8', false,false,$filterCriteriaString);
        $pdf->AddPage();

        $headers = ['اسم الفحص', 'نوع العينة', 'سعر العقد', 'تحمل الشركة', 'موافقة'];
        $colWidths = [70, 35, 25, 35, 20];
        $colWidths[count($colWidths) - 1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($colWidths, 0, -1));

        $alignments = ['R', 'R', 'C', 'C', 'C'];
        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        $fill = false;
        foreach ($contractedTests as $test) {
            $enduranceText = $test->pivot->use_static
                ? number_format((float) $test->pivot->endurance_static, 0) . ' (ثابت)' // Assuming static endurance for tests might be integer
                : number_format((float) $test->pivot->endurance_percentage, 1) . '%';

            $rowData = [
                $test->main_test_name,
                $test->container?->container_name ?? '-',
                number_format((float) $test->pivot->price, 2),
                $enduranceText,
                $test->pivot->approve ? 'نعم' : 'لا',
            ];
            $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill);
            $fill = !$fill;
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());


        $pdfFileName = 'company_test_contracts_' . $company->id . '_' . date('Ymd_His') . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }


    // public function generateMonthlyLabIncomePdf(Request $request)
    // {
    //     // Permission Check: e.g., can('view monthly_lab_income_report')
    //     // if (!auth()->user()->can('view monthly_lab_income_report')) { ... }

    //     $validated = $request->validate([
    //         'month' => 'required|integer|min:1|max:12',
    //         'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
    //     ]);

    //     $year = $validated['year'];
    //     $month = $validated['month'];

    //     $startDate = Carbon::create($year, $month, 1)->startOfDay();
    //     $endDate = $startDate->copy()->endOfMonth()->endOfDay();
    //     $period = CarbonPeriod::create($startDate, $endDate);

    //     $reportTitle = 'تقرير إيرادات المختبر الشهري';
    //     $filterCriteria = "لشهر: {$startDate->translatedFormat('F Y')} ( {$startDate->format('Y-m-d')} - {$endDate->format('Y-m-d')} )";

    //     // --- Data Aggregation ---
    //     $dailyData = [];
    //     $grandTotals = [
    //         'income' => 0,
    //         'discount' => 0,
    //         'cash' => 0,
    //         'bank' => 0,
    //     ];

    //     // Fetch all relevant lab requests for the month for efficiency
    //     // Eager load patient for company check
    //     $labRequestsForMonth = LabRequest::with('patient')
    //         ->whereBetween('created_at', [$startDate, $endDate]) // Filter by request creation date
    //         // Or filter by payment date if that's more relevant for "income"
    //         // ->whereHas('payments', function($q) use ($startDate, $endDate) { // If using a payments relation
    //         //     $q->whereBetween('payment_date', [$startDate, $endDate]);
    //         // })
    //         ->get();

    //     // Group lab requests by creation date (day)
    //     $requestsByDate = $labRequestsForMonth->groupBy(function ($request) {
    //         return Carbon::parse($request->created_at)->format('Y-m-d');
    //     });


    //     foreach ($period as $date) {
    //         $currentDateStr = $date->format('Y-m-d');
    //         $dailyIncome = 0;
    //         $dailyDiscount = 0;
    //         $dailyCash = 0;
    //         $dailyBank = 0;

    //         if ($requestsByDate->has($currentDateStr)) {
    //             foreach ($requestsByDate[$currentDateStr] as $lr) {
    //                 $price = (float) ($lr->price ?? 0);
    //                 $count = (int) ($lr->count ?? 1);
    //                 $itemSubTotal = $price * $count;

    //                 $discountAmount = ($itemSubTotal * ((int) ($lr->discount_per ?? 0) / 100));
    //                 // Add fixed discount if you have it: + (float)($lr->fixed_discount_amount ?? 0);

    //                 $enduranceAmount = (float) ($lr->endurance ?? 0);
    //                 $isCompanyPatient = !!$lr->patient?->company_id;

    //                 $netPayableByPatient = $itemSubTotal - $discountAmount - ($isCompanyPatient ? $enduranceAmount : 0);

    //                 // Income is based on the net amount the patient is supposed to pay for services rendered ON this day
    //                 // This assumes 'created_at' of LabRequest signifies the service rendering day for income recognition
    //                 $dailyIncome += $netPayableByPatient;
    //                 $dailyDiscount += $discountAmount; // Summing calculated discount for the day

    //                 // For cash/bank, we sum what was ACTUALLY collected for requests of this day
    //                 // This assumes labrequests.is_paid and labrequests.amount_paid reflect collection for that request.
    //                 // If payments are separate, this logic needs to change.
    //                 if ($lr->is_paid || $lr->amount_paid > 0) {
    //                     // This is tricky: amount_paid might be partial.
    //                     // For simplicity of this report based on current LabRequest model:
    //                     // If you want actual collected cash/bank for THIS DAY, you need a payment date on LabRequest
    //                     // or join with a payment/deposit table filtered by payment_date.
    //                     // Let's assume if it's paid, the amount_paid is the collected amount for that item on its creation day.
    //                     $collectedAmountForItem = (float) $lr->amount_paid;

    //                     if ($lr->is_bankak) { // Or your field for bank payment
    //                         $dailyBank += $collectedAmountForItem;
    //                     } else {
    //                         $dailyCash += $collectedAmountForItem;
    //                     }
    //                 }
    //             }
    //         }

    //         $dailyData[$currentDateStr] = [
    //             'date' => $currentDateStr,
    //             'income' => $dailyIncome,
    //             'discount' => $dailyDiscount,
    //             'cash' => $dailyCash,
    //             'bank' => $dailyBank,
    //         ];

    //         $grandTotals['income'] += $dailyIncome;
    //         $grandTotals['discount'] += $dailyDiscount;
    //         $grandTotals['cash'] += $dailyCash;
    //         $grandTotals['bank'] += $dailyBank;
    //     }


    //     // --- PDF Generation ---
    //     $pdf = new MyCustomTCPDF($reportTitle, $filterCriteria, 'L', 'mm', 'A4'); // Landscape
    //     $pdf->AddPage();
    //     $pdf->SetLineWidth(0.1);

    //     // Table Header
    //     $headers = ['التاريخ', 'إجمالي الإيراد (الصافي)', 'إجمالي الخصومات', 'المحصل نقداً', 'المحصل بنك/شبكة'];
    //     // A4 Landscape width ~277mm usable
    //     $colWidths = [40, 60, 50, 60, 0];
    //     $colWidths[count($colWidths) - 1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($colWidths, 0, -1));
    //     $alignments = ['C', 'C', 'C', 'C', 'C'];
    //     $pdf->DrawTableHeader($headers, $colWidths, $alignments);

    //     // Table Body
    //     $fill = false;
    //     $pdf->SetFont('helvetica', '', 8);
    //     foreach ($dailyData as $dayData) {
    //         if ($dayData['income'] == 0 && $dayData['cash'] == 0 && $dayData['bank'] == 0 && $dayData['discount'] == 0) {
    //             // Optionally skip days with no activity to make report shorter
    //             // continue; 
    //         }
    //         $rowData = [
    //             Carbon::parse($dayData['date'])->format('Y-m-d (D)'), // Format date with day name
    //             number_format($dayData['income'], 2),
    //             number_format($dayData['discount'], 2),
    //             number_format($dayData['cash'], 2),
    //             number_format($dayData['bank'], 2),
    //         ];
    //         $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill);
    //         $fill = !$fill;
    //     }
    //     $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY()); // Bottom line for table
    //     $pdf->Ln(5);

    //     // Grand Totals Section
    //     $pdf->SetFont('helvetica', 'B', 10);
    //     $pdf->Cell(0, 8, 'ملخص إجمالي للشهر', 0, 1, $pdf->getRTL() ? 'R' : 'L');

    //     $pdf->SetFont('helvetica', '', 9);
    //     $totalLabelWidth = 60;
    //     $totalValueWidth = 50;

    //     $pdf->Cell($totalLabelWidth, 7, 'إجمالي الإيرادات (الصافي):', 'LTRB', 0, 'R');
    //     $pdf->Cell($totalValueWidth, 7, number_format($grandTotals['income'], 2), 'LTRB', 1, 'C');
    //     $pdf->Cell($totalLabelWidth, 7, 'إجمالي الخصومات الممنوحة:', 'LTRB', 0, 'R');
    //     $pdf->Cell($totalValueWidth, 7, number_format($grandTotals['discount'], 2), 'LTRB', 1, 'C');
    //     $pdf->Cell($totalLabelWidth, 7, 'إجمالي المحصل نقداً:', 'LTRB', 0, 'R');
    //     $pdf->Cell($totalValueWidth, 7, number_format($grandTotals['cash'], 2), 'LTRB', 1, 'C');
    //     $pdf->Cell($totalLabelWidth, 7, 'إجمالي المحصل بنك/شبكة:', 'LTRB', 0, 'R');
    //     $pdf->Cell($totalValueWidth, 7, number_format($grandTotals['bank'], 2), 'LTRB', 1, 'C');
    //     $pdf->Ln(2);
    //     $pdf->SetFont('helvetica', 'B', 9);
    //     $pdf->Cell($totalLabelWidth, 7, 'إجمالي صافي الدخل المحصل:', 'LTRB', 0, 'R');
    //     $pdf->Cell($totalValueWidth, 7, number_format($grandTotals['cash'] + $grandTotals['bank'], 2), 'LTRB', 1, 'C');


    //     // --- Output PDF ---
    //     $pdfFileName = 'monthly_lab_income_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';
    //     $pdfContent = $pdf->Output($pdfFileName, 'S');
    //     return response($pdfContent, 200)
    //         ->header('Content-Type', 'application/pdf')
    //         ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    // }
    public function clinicReport(Request $request, DoctorShift $doctorShift)
    {

        if ($request->get('doctor_shift_id')) {
            $doctorShift = DoctorShift::find($request->get('doctor_shift_id'));
        }

        // $userId = $request->get('user'); // Not used in your original code for filtering DoctorShift
        $doctorShift->load([
            'user:id,username',
            'doctor:id,name,cash_percentage,company_percentage,static_wage', // Load percentages
            'visits.patient.company:id,name', // Load patient and their company for each visit
            'visits.requestedServices.service:id,name', // Load services for each visit
            'visits.patientLabRequests.mainTest:id,main_test_name', // Load lab tests for each visit
        ]);

        if (!$doctorShift) {
            return response()->json(['message' => 'لم يتم العثور على مناوبة الطبيب المحددة.'], 404);
        }
        if (!$doctorShift->doctor) {
            return response()->json(['message' => 'لم يتم العثور على بيانات الطبيب لهذه المناوبة.'], 404);
        }

        // Prepare filter criteria string for PDF header
        $filterCriteria = "مناوبة الطبيب: " . ($doctorShift->doctor->name ?? 'غير محدد') .
            " (#" . $doctorShift->id . ")" .
            " | بتاريخ: " . $doctorShift->start_time?->format('Y-m-d');

        // --- PDF Generation ---
        // Using your MyCustomTCPDF class
        $pdf = new MyCustomTCPDF(
            'التقرير الخاص بمناوبة الطبيب', // Report Title
            null,                 // Filters
            'L',                             // Orientation: Landscape
            'mm',
            'A4',
            true,
            'UTF-8',
            false,
            false,
            $filterCriteria
        );

        // Attempt to add Arial (ensure arial.ttf is in a TCPDF accessible font path or public_path if using that)
        // This path needs to be correct or TCPDF needs to find it in its font dirs.
        // $fontPath = public_path('fonts/arial.ttf'); // Example if in public/fonts
        // For TCPDF internal fonts like dejavusans, this is not needed.
        // If using a custom TTF font, ensure it's correctly added via TCPDF_FONTS::addTTFfont
        // For now, relying on MyCustomTCPDF's default font (dejavusans)
        // 'arial' = 'helvetica'; // Use the default from your custom class
        $fontBold = $pdf->SetFont('arial', 'B'); // Get bold variant if defaultFontBold is set in MyCustomTCPDF

        $pdf->AddPage();
        $pdf->setRTL(true);
        $page_width = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];


        // Header section in your PDF logic
        $pdf->SetFont('arial', 'B', 16); // Slightly smaller than your 22
        // $pdf->Cell($page_width, 5, 'التقرير الخاص', 0, 1, 'C'); // Title is now in TCPDF Header
        // $pdf->Ln(5); // Space after title
        $pdf->SetFont('arial', 'B', 10); // Smaller font for sub-headers

        $pdf->SetFillColor(220, 220, 220); // Light grey for headers
        $table_col_width_third = $page_width / 3; // For 3-column layout
        $table_col_width_sixth = $page_width / 6; // For 6-column layout

        // First header row
        $pdf->Cell($table_col_width_sixth, 7, 'التاريخ', 1, 0, 'C', 1);
        $pdf->Cell($table_col_width_sixth, 7, $doctorShift->start_time ? $doctorShift->start_time->format('Y/m/d') : '-', 1, 0, 'C', 1);
        $pdf->Cell($table_col_width_sixth * 2, 7, ' ', 0, 0, 'C'); // Spacer
        $pdf->Cell($table_col_width_sixth, 7, 'المستخدم', 1, 0, 'C', 1);
        $pdf->Cell($table_col_width_sixth, 7, $doctorShift->user->username ?? '-', 1, 1, 'C'); // ln=1

        // Second header row
        $pdf->Cell($table_col_width_sixth, 7, 'الطبيب', 1, 0, 'C', 1);
        $pdf->MultiCell($table_col_width_sixth, 7, $doctorShift->doctor->name ?? '-', 1, 'C', false, 0, $pdf->GetX(), $pdf->GetY(), true, 0, false, true, 7, 'M');
        $pdf->Cell($table_col_width_sixth * 2, 7, '', 0, 0, 'C'); // Spacer
        $pdf->Cell($table_col_width_sixth, 7, 'زمن فتح المناوبة', 1, 0, 'C', 1);
        $pdf->Cell($table_col_width_sixth, 7, $doctorShift->start_time ? $doctorShift->start_time->format('h:i A') : '-', 1, 1, 'C');
        $pdf->Ln(3);

        // Financial Summary Row
        $pdf->SetFont('arial', 'B', 9);
        $sectionWidth = ($page_width / 3) - 5; // Approx width for each financial section

        $pdf->Cell($sectionWidth, 7, 'إجمالي المرضى: ' . $doctorShift->visits->where('only_lab', 0)->count(), 1, 0, 'C');
        $pdf->Cell($sectionWidth, 7, 'استحقاق نقدي: ' . number_format($doctorShift->doctor_credit_cash(), 1), 1, 0, 'C');
        $pdf->Cell($sectionWidth, 7, 'استحقاق تأمين: ' . number_format($doctorShift->doctor_credit_company(), 1), 1, 1, 'C');
        $pdf->Ln(5);

        // Table for patient visits
        $pdf->SetFont('arial', 'B', 9);
        // Adjust widths based on landscape and content
        $h_widths = [15, 55, 40, 25, 25, 25, 30, 0]; // ID, Name, Company, Total, Cash, Bank, Doc Share, Services
        $h_widths[count($h_widths) - 1] = $page_width - array_sum(array_slice($h_widths, 0, -1));
        $h_aligns = ['C', 'R', 'R', 'C', 'C', 'C', 'C', 'R'];
        $headerTexts = ['رقم', 'اسم المريض', 'الشركة', 'إجمالي', 'نقداً', 'بنك', 'حصة الطبيب', 'الخدمات*'];

        $pdf->DrawTableHeader($headerTexts, $h_widths, $h_aligns); // Using your helper

        $pdf->SetFont('arial', '', 8);
        $index = 1;
        $visits = $doctorShift->visits->reverse()->filter(fn(DoctorVisit $visit) => $visit->only_lab == 0);

        $safi_total_placeholder = 0; // This variable wasn't clearly used for a grand total in your original

        foreach ($visits as $doctorvisit) {
            $isCompanyPatient = !!$doctorvisit->patient?->company_id;
            if ($isCompanyPatient) {
                $pdf->SetTextColor(200, 0, 0); // Red for company patients
            }

            $rowData = [
                $doctorvisit->number ?? $index, // Use visit number or sequence
                $doctorvisit->patient->name ?? '-',
                $doctorvisit->patient?->company?->name ?? '-',
                number_format($doctorvisit->total_services(), 1), // Using model method
                number_format($doctorvisit->total_paid_services($doctorShift->doctor) - $doctorvisit->bankak_service(), 1), // Cash
                number_format($doctorvisit->bankak_service(), 1), // Bank
                number_format($doctorShift->doctor->doctor_credit($doctorvisit), 1),
                $doctorvisit->services_concatinated() // This will use MultiCell via DrawTableRow
            ];
            $pdf->DrawTableRow($rowData, $h_widths, $h_aligns, ($index % 2 != 0)); // Alternating fill

            $pdf->SetTextColor(0, 0, 0); // Reset text color
            $index++;
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
        $pdf->Ln(2);

        // Footer Totals for the patient table
        $pdf->SetFont('arial', 'B', 9);
        $pdf->Cell($h_widths[0], 7, 'الإجمالي', 1, 0, 'C', true); // 'الإجمالي'
        $pdf->Cell($h_widths[1], 7, '', 1, 0, 'C', true); // Empty
        $pdf->Cell($h_widths[2], 7, '', 1, 0, 'C', true); // Empty
        $pdf->Cell($h_widths[3], 7, number_format($doctorShift->total_services(), 1), 1, 0, 'C', true);
        $pdf->Cell($h_widths[4], 7, number_format($doctorShift->total_paid_services() - $doctorShift->total_bank(), 1), 1, 0, 'C', true);
        $pdf->Cell($h_widths[5], 7, number_format($doctorShift->total_bank(), 1), 1, 0, 'C', true);
        $pdf->Cell($h_widths[6], 7, number_format($doctorShift->doctor_credit_cash() + $doctorShift->doctor_credit_company(), 1), 1, 0, 'C', true);
        $pdf->Cell($h_widths[7], 7, '', 1, 1, 'C', true); // Empty for services
        $pdf->Ln(5);


        // --- Service Costs Section --- (If data is available)
        $shiftServiceCosts = $doctorShift->shift_service_costs(); // Assumes this returns an array
        if (!empty($shiftServiceCosts)) {
            $pdf->AddPage(); // Start service costs on a new page for clarity
            $pdf->SetFont('arial', 'B', 14);
            $pdf->Cell($page_width, 10, 'مصروفات الخدمات للوردية', 0, 1, 'C');
            $pdf->Ln(2);

            $pdf->SetFont('arial', 'B', 10);
            $cost_col_widths = [$page_width * 0.6, $page_width * 0.4];
            $cost_aligns = ['R', 'C'];
            $pdf->DrawTableHeader(['بيان مصروف الخدمة', 'الإجمالي'], $cost_col_widths, $cost_aligns);

            $pdf->SetFont('arial', '', 9);
            $fillCost = false;
            foreach ($shiftServiceCosts as $cost) {
                $pdf->DrawTableRow([$cost['name'], number_format($cost['amount'], 1)], $cost_col_widths, $cost_aligns, $fillCost);
                $fillCost = !$fillCost;
            }
            $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
            $pdf->Ln(5);
        }

        // --- Costs Table Section (from your second table example) ---
        // This seems to list visits again, but with service_cost_name. This needs clarification.
        // For now, I'll comment this out as its data source (total_services_cost, services_cost_name)
        // needs very specific logic in DoctorVisit model.

        /*
        $pdf->AddPage(); // Or continue on current page if space
        $pdf->SetFont('arial', 'B', 14);
        $pdf->Cell($page_width, 10, 'تفصيل تكاليف الخدمات للمرضى', 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont('arial', 'B', 9);
        $cost_detail_widths = [15, 50, 40, 25, 40, 0]; // ID, Name, Company, Total Services Value, Total Service Costs, Cost Names
        $cost_detail_widths[count($cost_detail_widths)-1] = $page_width - array_sum(array_slice($cost_detail_widths, 0, -1));
        $cost_detail_aligns = ['C','R','R','C','C','R'];
        $pdf->DrawTableHeader(['رقم','اسم المريض','الشركة','إجمالي الخدمات','إجمالي التكاليف','بيان التكاليف*'], $cost_detail_widths, $cost_detail_aligns);

        $pdf->SetFont('arial', '', 8);
        $fillCostDetail = false;
        foreach ($visits as $doctorvisit) { // Using the same $visits collection
             $rowData = [
                $doctorvisit->number ?? $index,
                $doctorvisit->patient->name ?? '-',
                $doctorvisit->patient?->company?->name ?? '-',
                number_format($doctorvisit->calculateTotalServiceValue(), 1),
                number_format($doctorvisit->total_services_cost(), 1),
                $doctorvisit->services_cost_name()
             ];
             $pdf->DrawTableRow($rowData, $cost_detail_widths, $cost_detail_aligns, $fillCostDetail);
             $fillCostDetail = !$fillCostDetail;
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
        */


        // --- Output PDF ---
        $pdfFileName = 'clinic_report_doctorshift_' . $doctorShift->id . '_' . date('Ymd_His') . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
    public function clinicReport_old(Request $request)
    {


        $user_id = $request->get('user');
        $doctor_shift_id = $request->get(key: 'doctor_shift_id');
        $doctorShift = DoctorShift::find($doctor_shift_id);


        $pdf = new MyCustomTCPDF('تقرير الخاص', '', 'L', 'mm', 'A4');

        $lg = array();
        $lg['a_meta_charset'] = 'UTF-8';
        $lg['a_meta_dir'] = 'rtl';
        $lg['a_meta_language'] = 'fa';
        $lg['w_page'] = 'page';
        $pdf->setLanguageArray($lg);
        $pdf->setCreator(PDF_CREATOR);
        $pdf->setAuthor('Nicola Asuni');
        $pdf->setTitle('التقرير الخاص');
        $pdf->setSubject('TCPDF Tutorial');
        $pdf->setKeywords('TCPDF, PDF, example, test, guide');
        $pdf->setHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
        $pdf->setHeaderFont(array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
        $pdf->setFooterFont(array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
        $pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->setMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->setHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->setFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->setAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
        $pdf->setFont('times', 'BI', 12);
        $pdf->AddPage();
        $page_width = $pdf->getPageWidth() - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT;
        $pdf->setFont('arial', 'b', 22);

        $pdf->Cell($page_width, 5, 'التقرير الخاص', 0, 1, 'C');
        $pdf->Ln();
        $pdf->setFont('arial', 'b', 16);

        $pdf->setFillColor(200, 200, 200);
        $table_col_widht = $page_width / 6;
        $pdf->Cell($table_col_widht, 5, 'التاريخ ', 1, 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht, 5, $doctorShift->created_at->format('Y/m/d'), 1, 0, 'C');
        $pdf->Cell($table_col_widht, 5, ' ', 0, 0, 'C', fill: 0);
        $pdf->Cell($table_col_widht, 5, ' ', 0, 0, 'C', fill: 0);

        $pdf->Cell($table_col_widht, 5, 'المستخدم ', 1, 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht, 5, $doctorShift->user->username, 1, 1, 'C');

        $table_col_widht = ($page_width - 20) / 7;
        $pdf->Ln();
        $pdf->setFont('arial', 'b', 14);
        $table_col_widht = ($page_width) / 6;

        $pdf->Cell($table_col_widht, 5, 'الطبيب', 1, 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht, 5, $doctorShift->doctor->name, 1, 0, 'C', fill: 0, stretch: 1);
        $pdf->Cell($table_col_widht, 5, '', 0, 0, 'C', fill: 0);
        $pdf->Cell($table_col_widht, 5, '', 0, 0, 'C', fill: 0);
        $pdf->Cell($table_col_widht, 5, 'زمن فتح العياده', 1, 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht, 5, $doctorShift->created_at->format('h:i A'), 1, 1, 'C', fill: 0);
        $pdf->Ln();
        $pdf->Cell('30', 5, 'المرضي', 1, 0, 'C', fill: 0);
        $y = $pdf->getY();
        $pdf->setXY(160, $y);
        $pdf->Cell('30', 5, 'الاستحقاق النقدي', 1, 0, 'C', fill: 0);
        $pdf->Cell('30', 5, number_format($doctorShift->doctor_credit_cash(), 1), 1, 0, 'C', fill: 0);
        $pdf->Cell('10', 5, ' ', 0, 0, 'C', fill: 0);

        $pdf->Cell('30', 5, 'استحقاق التامين', 1, 0, 'C', fill: 0);
        $pdf->Cell('30', 5, number_format(+$doctorShift->doctor_credit_company(), 1), 1, 0, 'C', fill: 0);
        $pdf->Ln(5);
        $pdf->Ln(5);
        $table_col_widht = ($page_width) / 9;

        $pdf->Cell($table_col_widht / 2, 5, 'رقم', 'TB', 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht * 2, 5, 'اسم', 'TB', 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht, 5, 'الشركه', 'TB', 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht / 1.3, 5, 'اجمالي', 'TB', 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht / 1.3, 5, 'نقدا', 'TB', 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht / 1.3, 5, 'بنك', 'TB', 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht / 1.3, 5, '  استحقاق الطبيب ', 'TB', 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht * 2, 5, 'الخدمات *', 'TB', 1, 'C', fill: 1);
        $pdf->Ln();
        $pdf->setFont('arial', '', 11);
        $index = 1;
        /** @var Doctorvisit $doctorvisit */
        $visits = $doctorShift->visits->reverse()->filter(function (Doctorvisit $visit) {
            return $visit->only_lab == 0;
        });
        $safi_total = 0;
        foreach ($visits as $doctorvisit) {
            $y = $pdf->GetY();
            $pdf->Line(PDF_MARGIN_LEFT, $y, $page_width + PDF_MARGIN_RIGHT, $y);

            if ($doctorvisit->patient->company) {
                $pdf->setTextColor(200, 0, 0);
            }
            $pdf->Cell($table_col_widht / 2, 5, $doctorvisit->number, 0, 0, 'C', fill: 0);
            $pdf->Cell($table_col_widht * 2, 5, $doctorvisit->patient->name, 0, 0, 'C', fill: 0);
            $pdf->Cell($table_col_widht, 5, $doctorvisit->patient?->company?->name, 0, 0, 'C', fill: 0);
            $pdf->Cell($table_col_widht / 1.3, 5, number_format($doctorvisit->total_services($doctorShift->doctor), 1), 0, 0, 'C', fill: 0);
            $pdf->Cell($table_col_widht / 1.3, 5, number_format($doctorvisit->total_paid_services() - $doctorvisit->bankak_service(), 1), 0, 0, 'C', fill: 0);
            $pdf->Cell($table_col_widht / 1.3, 5, number_format($doctorvisit->bankak_service(), 1), 0, 0, 'C', fill: 0);
            $pdf->Cell($table_col_widht, 5, number_format($doctorvisit->doctorShift->doctor->doctor_credit($doctorvisit), 1), 0, 0, 'C', fill: 0);

            $safi_total += $doctorvisit->hospital_credit();
            // $pdf->Cell($table_col_widht / 1.3, 5, number_format($doctorvisit->hospital_credit(), 1), 0, 0, 'C', fill: 0);
            $pdf->MultiCell($table_col_widht * 2, 5, $doctorvisit->services_concatinated(), 0, 'R', false, stretch: 1);
            $y = $pdf->GetY();
            $index++;
            $pdf->setTextColor(0, 0, 0);

            $pdf->Line(PDF_MARGIN_LEFT, $y, $page_width + PDF_MARGIN_RIGHT, $y);
        }
        $pdf->Ln();


        $pdf->Cell($table_col_widht / 2, 5, '', 'TB', 0, 'C', fill: 0);
        $pdf->Cell($table_col_widht * 2, 5, '', 'TB', 0, 'C', fill: 0);

        $pdf->Cell($table_col_widht, 5, '', 'TB', 0, 'C', fill: 0);
        $pdf->setTextColor(0, 100, 0);

        $pdf->Cell($table_col_widht / 1.3, 5, number_format($doctorShift->total_services(), 1), 'TB', 0, 'C', fill: 0);
        $pdf->Cell($table_col_widht / 1.3, 5, number_format($doctorShift->total_paid_services() - $doctorShift->total_bank(), 1), 'TB', 0, 'C', fill: 0);
        $pdf->Cell($table_col_widht / 1.3, 5, number_format($doctorShift->total_bank(), 1), 'TB', 0, 'C', fill: 0);
        $pdf->Cell($table_col_widht, 5, number_format($doctorShift->doctor_credit_cash() + $doctorShift->doctor_credit_company(), 1), 'TB', 0, 'C', fill: 0);

        $pdf->Cell($table_col_widht * 2, 5, ' ', 0, 1, 'C', fill: 0);

        $pdf->AddPage();


        $col = $page_width / 2;
        $pdf->Ln();
        $pdf->Cell($page_width, 5, 'مصروف الخدمات', 0, 1, 'C', fill: 0);
        $pdf->Cell($col, 5, 'مصروف الخدمه', 0, 0, 'C', fill: 1);
        $pdf->Cell($col, 5, 'الاجمالي', 0, 1, 'C', fill: 1);
        foreach ($doctorShift->shift_service_costs() as $cost) {
            $pdf->Cell($col, 5, $cost['name'], 0, 0, 'C', fill: 0);
            $pdf->Cell($col, 5, number_format($cost['amount'], 1), 0, 1, 'C', fill: 0);
        }
        $pdf->Ln();

        $table_col_widht = $page_width / 6;
        $pdf->Cell($table_col_widht / 2, 5, 'رقم', 'TB', 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht, 5, 'اسم', 'TB', 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht, 5, 'الشركه', 'TB', 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht / 2, 5, 'الاجمالي', 'TB', 0, 'C', fill: 1);

        $pdf->Cell($table_col_widht, 5, 'اجمالي مصروفات', 'TB', 0, 'C', fill: 1);
        $pdf->Cell($table_col_widht * 2, 5, 'مصروفات *', 'TB', 1, 'C', fill: 1);
        $pdf->Ln();
        $pdf->setFont('arial', '', 11);
        $index = 1;
        /** @var Doctorvisit $doctorvisit */
        $visits = $doctorShift->visits->filter(function (Doctorvisit $visit) {
            return $visit->only_lab == 0;
        });

        foreach ($visits as $doctorvisit) {
            $y = $pdf->GetY();
            $pdf->Line(PDF_MARGIN_LEFT, $y, $page_width + PDF_MARGIN_RIGHT, $y);

            $pdf->Cell($table_col_widht / 2, 5, $doctorvisit->number, 0, 0, 'C', fill: 0);
            $pdf->Cell($table_col_widht, 5, $doctorvisit->patient->name, 0, 0, 'C', fill: 0);
            $pdf->Cell($table_col_widht, 5, $doctorvisit->patient?->company?->name, 0, 0, 'C', fill: 0);
            $pdf->Cell($table_col_widht / 2, 5, number_format($doctorvisit->total_services($doctorShift->doctor), 1), 0, 0, 'C', fill: 0);

            $pdf->Cell($table_col_widht, 5, number_format($doctorvisit->total_services_cost(), 1), 0, 0, 'C', fill: 0);
            $pdf->MultiCell($table_col_widht * 2, 5, $doctorvisit->services_cost_name(), 0, 'R', false, stretch: 1);
            $y = $pdf->GetY();
            $index++;

            $pdf->Line(PDF_MARGIN_LEFT, $y, $page_width + PDF_MARGIN_RIGHT, $y);
        }
        $pdf->Ln();


        $pdf->Ln();

        // Generate PDF content and return as response
        $pdfFileName = 'clinic_report_' . date('Ymd_His') . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
    public function allclinicsReportNew(Request $request)
    {
        // --- Data Retrieval ---
        if ($request->has('shift')) {
            $shift = Shift::find($request->get('shift'));
            if (!$shift) {
                // Handle case where requested shift doesn't exist, e.g., return error or default
                return response()->json(['error' => 'Shift not found'], 404);
            }
        } else {
            $shift = Shift::orderByDesc('id')->first();
            if (!$shift) {
                // Handle case where no shifts exist
                return response()->json(['error' => 'No shifts available'], 404);
            }
        }

        $doctorShiftsQuery = DoctorShift::with(['doctor.specialist', 'visits']) // Eager load specialist
            ->where('shift_id', $shift->id);

        if ($request->has('user')) {
            $user_id = $request->get('user');
            $doctorShiftsQuery->where('user_id', $user_id)->where('status', 1); // Assuming status 1 means active/relevant
        }
        // You might want to add an ->orderBy() here, e.g., by doctor's name or specialist
        $doctor_shifts = $doctorShiftsQuery->get();

        // --- PDF Initialization and Configuration ---
        // $pdf = new MYCustomPDF('P', PDF_UNIT, 'A4', true, 'UTF-8', false); // 'P' for Portrait, A4 for standard size
        $pdf = new MyCustomTCPDF('تقرير العام', '', 'p', 'mm', 'A4');

        // Document Information
        $pdf->SetCreator(config('app.name', 'Your Application Name'));
        $pdf->SetAuthor(config('app.name', 'Your Application Name'));
        $pdf->SetTitle('التقرير العام للعيادات');
        $pdf->SetSubject('ملخص الوردية المالي و استحقاقات الأطباء');
        $pdf->SetKeywords('تقرير, عيادات, مالي, أطباء, وردية');

        // Header and Footer
        // Replace with your actual logo path and header title/string
        $logoPath = public_path('path/to/your/logo.png'); // IMPORTANT: Update this path
        $headerLogoWidth = 15; // Adjust as needed
        $headerTitle = config('app.name', 'Your Clinic Name'); // Or a more specific title
        $headerString = "التقرير المالي للوردية رقم: " . $shift->id . "\n" .
            "تاريخ: " . $shift->created_at->format('Y/m/d') . " - " .
            "الوقت: " . $shift->created_at->format('H:i A');

        if (file_exists($logoPath)) {
            $pdf->SetHeaderData($logoPath, $headerLogoWidth, $headerTitle, $headerString);
        } else {
            // Fallback if logo is not found
            $pdf->SetHeaderData('', 0, $headerTitle, $headerString);
        }

        $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);

        // Margins
        $pdf->SetMargins(15, 25, 15); // Left, Top, Right (adjust top for header)
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);

        // Auto Page Breaks
        $pdf->SetAutoPageBreak(true, 20); // Enable, bottom margin

        // Language Settings for RTL
        $lg = [];
        $lg['a_meta_charset'] = 'UTF-8';
        $lg['a_meta_dir'] = 'rtl';
        $lg['a_meta_language'] = 'ar'; // Use 'ar' for Arabic
        $lg['w_page'] = 'صفحة';
        $pdf->setLanguageArray($lg);
        $pdf->setPrintHeader(true); // Ensure header is printed
        $pdf->setPrintFooter(true); // Ensure footer is printed

        // Font
        // Ensure 'arial.ttf' exists in public/fonts/ or adjust path

        $pdf->SetFont('arial', '', 10); // Default font size

        // --- First Page: Financial Summary (Consider if this fits well in portrait) ---
        $pdf->AddPage();
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];

        // Report Title
        $pdf->SetFont('arial', 'B', 18);
        $pdf->Cell(0, 10, 'التقرير المالي العام للوردية', 0, 1, 'C');
        $pdf->Ln(2);

        // Shift Information Table
        $pdf->SetFont('arial', 'B', 11);
        $infoTableWidth = $pageWidth / 2;
        $pdf->Cell($infoTableWidth, 7, "التاريخ: " . $shift->created_at->format('Y/m/d'), 'LTR', 0, 'R');
        $pdf->Cell($infoTableWidth, 7, "رقم الوردية المالي: " . $shift->id, 'LTR', 1, 'R');
        $pdf->Cell($infoTableWidth, 7, "الوقت: " . $shift->created_at->format('H:i A'), 'LBR', 0, 'R');
        $pdf->Cell($infoTableWidth, 7, "المستخدم المسؤول (إذا وجد): " . ($request->has('user') ? User::find($request->get('user'))->name ?? 'غير محدد' : 'جميع المستخدمين'), 'LBR', 1, 'R');
        $pdf->Ln(5);


        // --- Section 1: Collections by User (المتحصلون) ---
        // This section might become cramped in portrait if there are many users or details.
        // Consider if a summary or different layout is better for portrait.
        $pdf->SetFont('arial', 'B', 14);
        $pdf->Cell(0, 8, 'ملخص المتحصلات حسب المستخدم', 0, 1, 'C');
        $pdf->SetFillColor(220, 220, 220); // Light grey for headers
        $pdf->SetTextColor(0);
        $pdf->SetDrawColor(128, 128, 128); // Border color
        $pdf->SetLineWidth(0.2);

        $users = User::all(); // Consider filtering users who actually had transactions in this shift
        $userCollectionsPresented = false;

        foreach ($users as $user) {
            $totalPaid =  $shift->totalPaidService($user->id);
            $totalBank = $shift->totalPaidServiceBank($user->id);
            // Costs specific to this user within this shift (if applicable)
            $totalCostForUser = $shift->totalCost($user->id); // Ensure this method exists and is relevant
            $totalCostBankForUser = $shift->totalCostBank($user->id);
            $totalCost = $shift->totalCost($user->id);
            $totalCostBank = $shift->totalCostBank($user->id);
            $totalCash = $totalPaid - $totalBank;
            $totalCostCash = $totalCost - $totalCostBank;
            $netCash = $totalCash - $totalCostCash;

            if ($totalPaid == 0 && $totalBank == 0) {
                continue;
            }
            $userCollectionsPresented = true;

            $pdf->SetFont('arial', 'B', 11);
            $pdf->Cell(0, 7, 'المستخدم: ' . $user->name == '' ? $user->username : $user->name, 'B', 1, 'R');
            $pdf->Ln(2);

            $pdf->SetFont('arial', 'B', 10);
            $headerColWidth = $pageWidth / 6;
            $pdf->Cell($headerColWidth, 6, 'البيان', 1, 0, 'C', 1);
            $pdf->Cell($headerColWidth, 6, 'إجمالي المتحصلات', 1, 0, 'C', 1);
            $pdf->Cell($headerColWidth, 6, 'بنكك', 1, 0, 'C', 1);
            $pdf->Cell($headerColWidth, 6, 'نقدي', 1, 0, 'C', 1);
            $pdf->Cell($headerColWidth, 6, 'صاف بنكك', 1, 0, 'C', 1);
            $pdf->Cell($headerColWidth, 6, 'صافي النقديه', 1, 1, 'C', 1);

            $pdf->SetFont('arial', '', 10);
            $pdf->Cell($headerColWidth, 6, 'إجمالي الإيرادات', 1, 0, 'R');
            $pdf->Cell($headerColWidth, 6, number_format($totalPaid, 2), 1, 0, 'C');
            $pdf->Cell($headerColWidth, 6, number_format($totalBank, 2), 1, 0, 'C');
            $pdf->Cell($headerColWidth, 6, number_format($totalPaid - $totalBank, 2), 1, 0, 'C');
            $pdf->Cell($headerColWidth, 6, number_format($totalBank - $totalCostBank, 2), 1, 0, 'C');
            $pdf->Cell($headerColWidth, 6, number_format($netCash, 2), 1, 0, 'C');
            $pdf->Ln(5);
        }
        if (!$userCollectionsPresented) {
            $pdf->SetFont('arial', '', 10);
            $pdf->Cell(0, 7, 'لا توجد متحصلات لعرضها للمستخدمين المحددين في هذه الوردية.', 0, 1, 'C');
            $pdf->Ln(5);
        }


        // --- Section 2: Expenses (المصروفات) ---
        $pdf->SetFont('arial', 'B', 14);
        $pdf->Cell(0, 8, 'ملخص المصروفات', 0, 1, 'C');
        $pdf->SetFont('arial', 'B', 10);
        $expenseColWidth = $pageWidth / 4;
        $pdf->Cell($expenseColWidth, 6, 'وصف المصروف', 1, 0, 'C', 1);
        $pdf->Cell($expenseColWidth, 6, 'القيمة الإجمالية', 1, 0, 'C', 1);
        $pdf->Cell($expenseColWidth, 6, 'نقدي', 1, 0, 'C', 1);
        $pdf->Cell($expenseColWidth, 6, 'بنكك', 1, 1, 'C', 1);

        $pdf->SetFont('arial', '', 10);
        $totalExpenses = 0;
        $totalCashExpenses = 0;
        $totalBankExpenses = 0;
        if ($shift->cost->count() > 0) {
            foreach ($shift->cost as $c) {
                $cashAmount = $c->amount - $c->amount_bankak;
                $pdf->Cell($expenseColWidth, 6, $c->description, 1, 0, 'R');
                $pdf->Cell($expenseColWidth, 6, number_format($c->amount, 2), 1, 0, 'C');
                $pdf->Cell($expenseColWidth, 6, number_format($cashAmount, 2), 1, 0, 'C');
                $pdf->Cell($expenseColWidth, 6, number_format($c->amount_bankak, 2), 1, 1, 'C');
                $totalExpenses += $c->amount;
                $totalCashExpenses += $cashAmount;
                $totalBankExpenses += $c->amount_bankak;
            }
            // Total Expenses Row
            $pdf->SetFont('arial', 'B', 10);
            $pdf->Cell($expenseColWidth, 6, 'الإجمالي', 1, 0, 'C', 1);
            $pdf->Cell($expenseColWidth, 6, number_format($totalExpenses, 2), 1, 0, 'C', 1);
            $pdf->Cell($expenseColWidth, 6, number_format($totalCashExpenses, 2), 1, 0, 'C', 1);
            $pdf->Cell($expenseColWidth, 6, number_format($totalBankExpenses, 2), 1, 1, 'C', 1);
        } else {
            $pdf->Cell(0, 7, 'لا توجد مصروفات مسجلة لهذه الوردية.', 'LRB', 1, 'C');
        }
        $pdf->Ln(5);

        // --- Add New Page for Doctors' Dues ---
        $pdf->AddPage();

        // Report Title for Doctors' Dues
        $pdf->SetFont('arial', 'B', 18);
        $pdf->Cell(0, 10, 'كشف استحقاق الأطباء', 0, 1, 'C');
        $pdf->Ln(2);

        // Shift Information (repeated for context on new page, or use header)
        // $pdf->SetFont('arial', 'B', 11);
        // $pdf->Cell(0, 7, "للوردية رقم: " . $shift->id . " - تاريخ: " . $shift->created_at->format('Y/m/d'), 0, 1, 'C');
        // $pdf->Ln(3);


        $pdf->SetFont('arial', 'B', 9); // Adjusted font size for more columns
        $colCount = 6;
        $tableColWidth = $pageWidth / $colCount;

        // Table Headers for Doctors' Dues
        $pdf->Cell($tableColWidth, 7, 'التخصص', 1, 0, 'C', 1);
        $pdf->Cell($tableColWidth, 7, 'الطبيب', 1, 0, 'C', 1);
        $pdf->Cell($tableColWidth, 7, 'إجمالي مدفوع', 1, 0, 'C', 1);
        $pdf->Cell($tableColWidth, 7, 'مستحق نقدي', 1, 0, 'C', 1); // Renamed for clarity
        $pdf->Cell($tableColWidth, 7, 'مستحق تأمين', 1, 0, 'C', 1); // Renamed for clarity
        $pdf->Cell($tableColWidth, 7, 'صافي للمنشأة', 1, 1, 'C', 1);

        $pdf->SetFont('arial', '', 9); // Adjusted font size

        $grandTotalPaid = 0;
        $grandTotalDoctorCash = 0;
        $grandTotalDoctorInsurance = 0;
        $grandTotalHospitalShare = 0;

        if ($doctor_shifts->count() > 0) {
            foreach ($doctor_shifts as $doctor_shift) {
                $doctorName = $doctor_shift->doctor->name ?? 'N/A';
                $specialistName = $doctor_shift->doctor->specialist->name ?? 'N/A';

                $totalPaidForDoctor = $doctor_shift->total_paid_services();
                $doctorCashDue = $doctor_shift->doctor_credit_cash();
                $doctorInsuranceDue = $doctor_shift->doctor_credit_company();
                $hospitalShare = $doctor_shift->hospital_credit();

                $pdf->Cell($tableColWidth, 6, $specialistName, 1, 0, 'R');
                // Making doctor name clickable if a link is intended
                // $doctorReportLink = url('doctor/report?doctorshift=' . $doctor_shift->id); // Generate full URL
                // $pdf->Cell($tableColWidth, 6, $doctorName, 1, 0, 'R', 0, $doctorReportLink);
                $pdf->Cell($tableColWidth, 6, $doctorName, 1, 0, 'R');

                $pdf->Cell($tableColWidth, 6, number_format($totalPaidForDoctor, 2), 1, 0, 'C');
                $pdf->Cell($tableColWidth, 6, number_format($doctorCashDue, 2), 1, 0, 'C');
                $pdf->Cell($tableColWidth, 6, number_format($doctorInsuranceDue, 2), 1, 0, 'C');
                $pdf->Cell($tableColWidth, 6, number_format($hospitalShare, 2), 1, 1, 'C');

                $grandTotalPaid += $totalPaidForDoctor;
                $grandTotalDoctorCash += $doctorCashDue;
                $grandTotalDoctorInsurance += $doctorInsuranceDue;
                $grandTotalHospitalShare += $hospitalShare;
            }

            // Grand Totals Row for Doctors' Dues
            $pdf->SetFont('arial', 'B', 9);
            $pdf->Cell($tableColWidth * 2, 7, 'الإجمالي العام', 1, 0, 'C', 1);
            $pdf->Cell($tableColWidth, 7, number_format($grandTotalPaid, 2), 1, 0, 'C', 1);
            $pdf->Cell($tableColWidth, 7, number_format($grandTotalDoctorCash, 2), 1, 0, 'C', 1);
            $pdf->Cell($tableColWidth, 7, number_format($grandTotalDoctorInsurance, 2), 1, 0, 'C', 1);
            $pdf->Cell($tableColWidth, 7, number_format($grandTotalHospitalShare, 2), 1, 1, 'C', 1);
        } else {
            $pdf->SetFont('arial', '', 10);
            $pdf->Cell(0, 7, 'لا توجد بيانات أطباء لعرضها لهذه الوردية.', 1, 1, 'C');
        }
        $pdf->Ln(5);

        // Note about net calculation
        $pdf->SetFont('arial', 'I', 9); // Italic for note
        $pdf->MultiCell(0, 5, '*** ملاحظة: صافي المنشأة من خدمات الأطباء يتم احتسابه بعد خصم مستحقات الطبيب. المصروفات العامة للعيادة تخصم من إجمالي إيرادات الوردية.', 0, 'R', 0, 1);
        $pdf->Ln(5);


        // --- Section 3: Clinic Service Costs (مصروف الخدمات) ---
        $pdf->SetFont('arial', 'B', 14);
        $pdf->Cell(0, 8, 'تفاصيل مصروفات الخدمات للعيادة', 0, 1, 'C');

        $pdf->SetFont('arial', 'B', 10);
        $serviceCostColWidth = $pageWidth / 2;
        $pdf->Cell($serviceCostColWidth, 6, 'بند مصروف الخدمة', 1, 0, 'C', 1);
        $pdf->Cell($serviceCostColWidth, 6, 'المبلغ', 1, 1, 'C', 1);

        $pdf->SetFont('arial', '', 10);
        $totalServiceCosts = 0;
        $clinicServiceCosts = $shift->shiftClinicServiceCosts(); // Assuming this returns an array like ['name' => ..., 'amount' => ...]

        if (count($clinicServiceCosts) > 0) {
            foreach ($clinicServiceCosts as $cost) {
                $pdf->Cell($serviceCostColWidth, 6, $cost['name'], 1, 0, 'R');
                $pdf->Cell($serviceCostColWidth, 6, number_format($cost['amount'], 2), 1, 1, 'C');
                $totalServiceCosts += $cost['amount'];
            }
            // Total Service Costs Row
            $pdf->SetFont('arial', 'B', 10);
            $pdf->Cell($serviceCostColWidth, 6, 'إجمالي مصروفات الخدمات', 1, 0, 'C', 1);
            $pdf->Cell($serviceCostColWidth, 6, number_format($totalServiceCosts, 2), 1, 1, 'C', 1);
        } else {
            $pdf->Cell(0, 7, 'لا توجد مصروفات خدمات مسجلة لهذه الوردية.', 1, 1, 'C');
        }
        $pdf->Ln(10);

        // --- Final Summary Section (Optional, but good for a professional report) ---
        $pdf->SetFont('arial', 'B', 14);
        $pdf->Cell(0, 8, 'الملخص المالي النهائي للوردية', 0, 1, 'C');
        $pdf->SetFont('arial', 'B', 10);

        $summaryColWidth = $pageWidth / 2;
        $totalRevenueAllUsers = 0;
        foreach (User::all() as $u) { // Recalculate or use stored if already available
            $totalRevenueAllUsers += ($shift->paidLab($u->id) + $shift->totalPaidService($u->id));
        }

        $netCash = ($totalRevenueAllUsers - $totalBank) - ($totalExpenses - $totalBankExpenses) - ($totalServiceCosts); // This net needs careful calculation based on your business logic
        // Example: (Total Cash Revenue) - (Total Cash Expenses including service costs)
        // Or (Total Revenue) - (Total Expenses) - (Total Service Costs)

        $data = [
            'إجمالي الإيرادات (جميع المستخدمين)' => number_format($totalRevenueAllUsers, 2),
            'إجمالي المصروفات العامة' => number_format($totalExpenses, 2),
            'إجمالي مصروفات الخدمات' => number_format($totalServiceCosts, 2),
            'صافي الدخل للوردية (قبل توزيع مستحقات الأطباء من خدماتهم)' => number_format($totalRevenueAllUsers - $totalExpenses - $totalServiceCosts, 2),
            'إجمالي مستحقات الأطباء النقدية (من خدماتهم)' => number_format($grandTotalDoctorCash, 2),
            'إجمالي مستحقات الأطباء التأمين (من خدماتهم)' => number_format($grandTotalDoctorInsurance, 2),
            'صافي دخل المنشأة من خدمات الأطباء' => number_format($grandTotalHospitalShare, 2),
            // Add more relevant summary points
        ];

        $pdf->SetFillColor(240, 240, 240); // Slightly different fill for summary
        foreach ($data as $label => $value) {
            $pdf->SetFont('arial', 'B', 10);
            $pdf->Cell($summaryColWidth + 20, 7, $label, 1, 0, 'R', 1);
            $pdf->SetFont('arial', '', 10);
            $pdf->Cell($summaryColWidth - 20, 7, $value, 1, 1, 'C', 0);
        }
        $pdf->Ln(5);
        $pdf->SetFont('arial', '', 8);
        $pdf->Cell(0, 5, 'تم إنشاء هذا التقرير بواسطة: ' . config('app.name', 'نظام إدارة العيادات'), 0, 1, 'L');
        $pdf->Cell(0, 5, 'تاريخ الإنشاء: ' . now()->format('Y/m/d H:i:s'), 0, 1, 'L');


        // Output
        $fileName = 'AllClinicsReport_Shift_' . $shift->id . '_' . now()->format('Ymd_His') . '.pdf';
        $pdfContent = $pdf->Output($fileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$fileName}\"");
    }
    public function generateThermalServiceReceipt(Request $request, DoctorVisit $visit)
    {
        if ($visit->requestedServices()->count() === 0) {
            return response()->json(['message' => 'لا توجد خدمات لإنشاء إيصال لها في هذه الزيارة.'], 404);
        }
    
        $report = new \App\Services\Pdf\ThermalServiceReceiptReport($visit);
        $pdfContent = $report->generate();
        
        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $visit->patient->name);
        $filename = 'ServiceReceipt_Visit_' . $visit->id . '_' . $patientNameSanitized . '.pdf';
    
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$filename}\"");
    }

    protected function drawThermalTotalRow(MyCustomTCPDF $pdf, string $label, float $value, float $pageUsableWidth, bool $isBoldValue = false, string $valueClass = '')
    {
        $fontName = 'helvetica';
        $currentFontSize = $pdf->getFontSizePt();
        $currentStyle = $pdf->getFontStyle();

        $labelWidth = $pageUsableWidth * 0.60;
        $valueWidth = $pageUsableWidth * 0.40;

        if ($isBoldValue)
            $pdf->SetFont($fontName, 'B', $currentFontSize + 0.5); // Slightly larger if bold

        $pdf->Cell($labelWidth, 4, $label, 0, 0, $pdf->getRTL() ? 'R' : 'L');
        $pdf->Cell($valueWidth, 4, number_format($value, 2), 0, 1, $pdf->getRTL() ? 'L' : 'R');

        if ($isBoldValue)
            $pdf->SetFont($fontName, $currentStyle, $currentFontSize); // Reset
    }

    // // Helper for drawing total rows in thermal receipt style
    // protected function drawThermalTotalRow(MyCustomTCPDF $pdf, string $label, float $value, float $pageUsableWidth, bool $isBoldValue = false, bool $isReduction = false)
    // {
    //     $labelWidth = $pageUsableWidth * 0.60; // Adjust ratio for thermal
    //     $valueWidth = $pageUsableWidth * 0.40;
    //     $currentStyle = $pdf->getFontStyle();
    //     $currentFontFamily = $pdf->getFontFamily();
    //     $currentFontSize = $pdf->getFontSizePt();


    //     $pdf->Cell($labelWidth, 4, $label, 0, 0, 'R'); // Label aligned right
    //     if ($isBoldValue) $pdf->SetFont($currentFontFamily, 'B', $currentFontSize);

    //     $valueString = ($isReduction && $value > 0 ? '-' : '') . number_format($value, 2);
    //     $pdf->Cell($valueWidth, 4, $valueString, 0, 1, 'L'); // Value aligned left

    //     if ($isBoldValue) $pdf->SetFont($currentFontFamily, $currentStyle, $currentFontSize); // Reset font
    // }

    // Helper for drawing total rows
    protected function drawTotalRow(MyCustomTCPDF $pdf, string $label, float $value, float $pageUsableWidth, bool $isBoldValue = false)
    {
        $labelWidth = $pageUsableWidth * 0.65;
        $valueWidth = $pageUsableWidth * 0.35;
        $currentFont = $pdf->getFontFamily(); // TCPDF doesn't have getFontStyle easily
        $currentStyle = $pdf->getFontStyle();

        $pdf->Cell($labelWidth, 5, $label, 0, 0, 'R');
        if ($isBoldValue)
            $pdf->SetFont($currentFont, 'B', $pdf->getFontSizePt());
        $pdf->Cell($valueWidth, 5, number_format($value, 2), 0, 1, 'L'); // Align value to left for numbers
        if ($isBoldValue)
            $pdf->SetFont($currentFont, $currentStyle, $pdf->getFontSizePt()); // Reset
    }
    public function generateCostsReportPdf(Request $request)
    {
        // Permission Check: e.g., can('print cost_report')
        // if (!Auth::user()->can('print cost_report')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Validation (same as CostController@index for filters)
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'cost_category_id' => 'nullable|integer|exists:cost_categories,id',
            'user_cost_id' => 'nullable|integer|exists:users,id', // User who recorded
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'payment_method' => 'nullable|string|in:cash,bank,mixed,all',
            'search_description' => 'nullable|string|max:255',
            'sort_by' => 'nullable|string|in:created_at,total_cost,description', // total_cost derived
            'sort_direction' => 'nullable|string|in:asc,desc',
        ]);

        // --- Fetch Data (same logic as CostController@index) ---
        $query = Cost::with(['costCategory:id,name', 'userCost:id,name', 'shift:id', /* 'doctorShift.doctor:id,name' */]);
        $filterCriteria = []; // To build a string for PDF header

        if ($request->filled('date_from')) {
            $from = Carbon::parse($request->date_from)->startOfDay();
            $query->whereDate('created_at', '>=', $from);
            $filterCriteria[] = "From: " . $from->format('d-M-Y');
        }
        if ($request->filled('date_to')) {
            $to = Carbon::parse($request->date_to)->endOfDay();
            $query->whereDate('created_at', '<=', $to);
            $filterCriteria[] = "To: " . $to->format('d-M-Y');
        }
        if ($request->filled('cost_category_id')) {
            $query->where('cost_category_id', $request->cost_category_id);
            if ($cat = CostCategory::find($request->cost_category_id))
                $filterCriteria[] = "Category: " . $cat->name;
        }
        if ($request->filled('user_cost_id')) {
            $query->where('user_cost', $request->user_cost_id); // Assuming 'user_cost' is the FK column name to users.id
            if ($user = User::find($request->user_cost_id))
                $filterCriteria[] = "User: " . $user->name;
        }
        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
            if ($shift = Shift::find($request->shift_id))
                $filterCriteria[] = "Shift: #" . ($shift->name ?? $shift->id);
        }
        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $method = $request->payment_method;
            if ($method === 'cash') {
                $query->where('amount', '>', 0)->where('amount_bankak', '=', 0);
                $filterCriteria[] = "Payment: Cash";
            } elseif ($method === 'bank') {
                $query->where('amount_bankak', '>', 0)->where('amount', '=', 0);
                $filterCriteria[] = "Payment: Bank";
            } elseif ($method === 'mixed') {
                $query->where('amount', '>', 0)->where('amount_bankak', '>', 0);
                $filterCriteria[] = "Payment: Mixed";
            }
        }
        if ($request->filled('search_description')) {
            $query->where('description', 'LIKE', '%' . $request->search_description . '%');
            $filterCriteria[] = "Desc: " . $request->search_description;
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        if ($sortBy === 'total_cost') {
            $query->orderByRaw('(amount + amount_bankak) ' . $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }
        $costs = $query->get(); // Get all filtered records for PDF

        if ($costs->isEmpty()) {
            return response()->json(['message' => 'No cost data found for the selected filters to generate PDF.'], 404);
        }

        // Calculate Summary Totals (for the filtered dataset)
        $totalCashPaid = $costs->sum('amount');
        $totalBankPaid = $costs->sum('amount_bankak');
        $grandTotalPaid = $costs->sum(fn($cost) => $cost->amount + $cost->amount_bankak);

        // --- PDF Generation ---
        $appSettings = Setting::instance(); // For letterhead details
        $reportTitle = 'تقرير المصروفات'; // "Costs Report"
        $filterCriteriaString = !empty($filterCriteria) ? implode(' | ', $filterCriteria) : "جميع المصروفات";

        $pdf = new MyCustomTCPDF(
            $reportTitle,
            null,
            'L', // Landscape for more columns
            'mm',
            'A4',
            true,
            'utf-8',
            false,
            false,
            $filterCriteriaString
        );
        $pdf->AddPage();
        $pdf->SetLineWidth(0.1);
        $defaultFont = 'helvetica'; // From MyCustomTCPDF
        $isRTL = $pdf->getRTL();

        // --- Overall Summary Section at the Top ---
        $pdf->SetFont($defaultFont, 'B', 10);
        $pdf->Cell(0, 7, 'ملخص المصروفات الإجمالي للفترة المحددة', 0, 1, 'C');
        $pdf->Ln(1);
        $pdf->SetFont($defaultFont, '', 9);
        $summaryCellWidth = ($pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right']) / 3;
        $pdf->Cell($summaryCellWidth, 6, 'إجمالي المصروفات النقدية: ' . number_format($totalCashPaid, 2), 1, 0, 'C');
        $pdf->Cell($summaryCellWidth, 6, 'إجمالي المصروفات البنكية: ' . number_format($totalBankPaid, 2), 1, 0, 'C');
        $pdf->Cell($summaryCellWidth, 6, 'إجمالي المصروفات الكلي: ' . number_format($grandTotalPaid, 2), 1, 1, 'C');
        $pdf->Ln(4);

        // --- Detailed Costs Table ---
        $pdf->SetFont($defaultFont, 'B', 9);
        $pdf->Cell(0, 7, 'تفاصيل المصروفات', 0, 1, 'C'); // "Detailed Costs"
        $pdf->Ln(1);

        // Define headers, widths, and alignments for the table
        $headers = ['التاريخ', 'الوصف', 'الفئة', 'المستخدم', 'طريقة الدفع', 'نقداً', 'بنك/شبكة', 'الإجمالي'];
        // Landscape A4 width ~277mm usable.
        $colWidths = [35, 70, 30, 30, 25, 25, 25, 0];
        $colWidths[count($colWidths) - 1] = ($pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right']) - array_sum(array_slice($colWidths, 0, -1));
        $alignments = ['C', $isRTL ? 'R' : 'L', 'C', 'C', 'C', 'R', 'R', 'R'];

        $pdf->SetTableDefinition($headers, $colWidths, $alignments); // Store for potential re-drawing on new page
        $pdf->DrawTableHeader(); // Draw the header

        $pdf->SetFont($defaultFont, '', 8);
        $fill = false;
        foreach ($costs as $cost) {
            $totalCostForRow = $cost->amount + $cost->amount_bankak;
            $paymentMethodDisplay = '-';
            if ($cost->amount > 0 && $cost->amount_bankak > 0)
                $paymentMethodDisplay = 'مختلط';
            else if ($cost->amount > 0)
                $paymentMethodDisplay = 'نقداً';
            else if ($cost->amount_bankak > 0)
                $paymentMethodDisplay = 'بنك';

            $rowData = [
                Carbon::parse($cost->created_at)->format('Y-m-d H:i'),
                $cost->description,
                $cost->costCategory?->name ?? '-',
                $cost->userCost?->name ?? '-', // Ensure userCost relation is loaded
                $paymentMethodDisplay,
                number_format($cost->amount, 2),
                number_format($cost->amount_bankak, 2),
                number_format($totalCostForRow, 2),
            ];
            $pdf->DrawTableRow($rowData, null, null, $fill, 6); // Use stored widths/alignments, base height 6
            $fill = !$fill;
        }
        // Draw final line under the table data
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        // Grand Total Row for the table (optional, as summary is at top)
        // You can use $pdf->DrawSummaryRow here if you defined it in MyCustomTCPDF


        // --- Output PDF ---
        $pdfFileName = 'Costs_Report_' . ($request->date_from ?? 'all') . '_to_' . ($request->date_to ?? 'all') . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S'); // 'S' returns as string

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
    public function monthlyServiceDepositsIncome(Request $request)
    {
        // $this->authorize('view monthly_service_income_report'); // Permission check

        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            // Add other filters if needed, e.g., specific user who processed deposits
            // 'user_id' => 'nullable|integer|exists:users,id',
        ]);

        $year = $validated['year'];
        $month = $validated['month'];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $period = CarbonPeriod::create($startDate, '1 day', $endDate);

        $dailyData = [];
        $grandTotals = [
            'total_deposits' => 0,
            'total_cash_deposits' => 0,
            'total_bank_deposits' => 0,
            'total_costs_for_days_with_deposits' => 0, // Costs on days that had deposits
            'net_total_income' => 0, // total_deposits - total_costs_for_days_with_deposits
            'net_cash_flow' => 0,    // total_cash_deposits - cash_costs_on_deposit_days
            'net_bank_flow' => 0,    // total_bank_deposits - bank_costs_on_deposit_days
        ];

        // Fetch all relevant deposits and costs for the month once for efficiency
        $allDepositsForMonth = RequestedServiceDeposit::with('shift') // Eager load shift if needed for context
            ->whereBetween('created_at', [$startDate, $endDate])
            // ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->get();

        $allCostsForMonth = Cost::query()
            ->whereBetween('created_at', [$startDate, $endDate]) // Assuming costs are recorded on their occurrence date
            // ->when($request->filled('user_id'), fn($q) => $q->where('user_cost', $request->user_id)) // If filtering costs by user too
            ->get();

        foreach ($period as $date) {
            $currentDateStr = $date->format('Y-m-d');

            $depositsOnThisDay = $allDepositsForMonth->filter(function ($deposit) use ($currentDateStr) {
                return Carbon::parse($deposit->created_at)->format('Y-m-d') === $currentDateStr;
            });

            $costsOnThisDay = $allCostsForMonth->filter(function ($cost) use ($currentDateStr) {
                return Carbon::parse($cost->created_at)->format('Y-m-d') === $currentDateStr;
            });

            if ($depositsOnThisDay->isEmpty() && $costsOnThisDay->isEmpty() && !$request->input('show_empty_days', false)) {
                continue; // Skip days with no activity unless explicitly requested
            }

            $dailyTotalDeposits = $depositsOnThisDay->sum('amount');
            $dailyCashDeposits = $depositsOnThisDay->where('is_bank', false)->sum('amount');
            $dailyBankDeposits = $depositsOnThisDay->where('is_bank', true)->sum('amount');

            $dailyTotalCosts = $costsOnThisDay->sum('amount');
            $dailyBankCosts = $costsOnThisDay->sum('amount_bankak');
            $dailyCashCosts = $dailyTotalCosts - $dailyBankCosts;

            $dailyNetIncome = $dailyTotalDeposits - $dailyTotalCosts;
            $dailyNetCash = $dailyCashDeposits - $dailyCashCosts;
            $dailyNetBank = $dailyBankDeposits - $dailyBankCosts;

            $dailyData[] = [
                'date' => $currentDateStr,
                'total_income' => (float) $dailyTotalDeposits,
                'total_cash_income' => (float) $dailyCashDeposits,
                'total_bank_income' => (float) $dailyBankDeposits,
                'total_cost' => (float) $dailyTotalCosts,
                'net_cash' => (float) $dailyNetCash,
                'net_bank' => (float) $dailyNetBank,
                'net_income_for_day' => (float) $dailyNetIncome,
            ];

            $grandTotals['total_deposits'] += $dailyTotalDeposits;
            $grandTotals['total_cash_deposits'] += $dailyCashDeposits;
            $grandTotals['total_bank_deposits'] += $dailyBankDeposits;
            $grandTotals['total_costs_for_days_with_deposits'] += $dailyTotalCosts; // Summing all costs within the period
            $grandTotals['net_cash_flow'] += $dailyNetCash;
            // $grandTotals['net_bank_flow'] += $dailyNetBank;

            // These will be calculated at the end from summed totals
        }

        $grandTotals['net_total_income'] = $grandTotals['total_deposits'] - $grandTotals['total_costs_for_days_with_deposits'];
        // $grandTotals['net_cash_flow'] = $grandTotals['total_cash_deposits'] - $allCostsForMonth->sum('amount'); // Total cash costs for month
        $grandTotals['net_bank_flow'] = $grandTotals['total_bank_deposits'] - $allCostsForMonth->sum('amount_bankak'); // Total bank costs for month


        return response()->json([
            'daily_data' => $dailyData,
            'summary' => $grandTotals,
            'report_period' => [
                'month_name' => $startDate->translatedFormat('F Y'), // Localized month name
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ]
        ]);
    }
    /**
     * Helper function to get monthly service deposit income data.
     * This will be used by both the JSON API endpoint and the export functions.
     */
    private function getMonthlyServiceDepositsIncomeData(Request $request): array
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            // 'user_id' => 'nullable|integer|exists:users,id', // Optional filter
            'show_empty_days' => 'nullable|boolean', // For PDF/Excel, you might always want to show all days
        ]);

        $year = $validated['year'];
        $month = $validated['month'];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $period = CarbonPeriod::create($startDate, '1 day', $endDate);

        $dailyData = [];
        $grandTotals = [
            'total_deposits' => 0,
            'total_cash_deposits' => 0,
            'total_bank_deposits' => 0,
            'total_costs_for_days_with_activity' => 0, // Costs on days that had deposits OR costs
            'net_cash_flow' => 0,
        ];

        $allDepositsForMonth = RequestedServiceDeposit::whereBetween('created_at', [$startDate, $endDate])
            // ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->get();

        $allCostsForMonth = Cost::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            // ->when($request->filled('user_id'), fn($q) => $q->where('user_cost', $request->user_id))
            ->get();

        foreach ($period as $date) {
            $currentDateStr = $date->format('Y-m-d');

            $depositsOnThisDay = $allDepositsForMonth->filter(fn($d) => Carbon::parse($d->created_at)->isSameDay($date));
            $costsOnThisDay = $allCostsForMonth->filter(fn($c) => Carbon::parse($c->created_at)->isSameDay($date));

            if ($depositsOnThisDay->isEmpty() && $costsOnThisDay->isEmpty() && !$request->input('show_empty_days', true)) { // Default to true for reports
                continue;
            }

            $dailyTotalDeposits = $depositsOnThisDay->sum('amount');
            $dailyCashDeposits = $depositsOnThisDay->where('is_bank', false)->sum('amount');
            $dailyBankDeposits = $depositsOnThisDay->where('is_bank', true)->sum('amount');

            $dailyCosts = $costsOnThisDay->sum('amount');
            $dailyBankCosts = $costsOnThisDay->sum('amount_bankak');
            $dailyCashCosts = $dailyCosts - $dailyBankCosts;
            $dailyTotalCosts = $dailyCosts;

            $dailyData[] = [
                'date_obj' => $date, // Keep Carbon instance for PDF formatting
                'date' => $currentDateStr,
                'total_income' => (float) $dailyTotalDeposits,
                'total_cash_income' => (float) $dailyCashDeposits,
                'total_bank_income' => (float) $dailyBankDeposits,
                'total_cost' => (float) $dailyTotalCosts,
                'net_cash' => (float) ($dailyCashDeposits - $dailyCashCosts),
                'net_bank' => (float) ($dailyBankDeposits - $dailyBankCosts),
                'net_income_for_day' => (float) ($dailyTotalDeposits - $dailyTotalCosts),
                'net_income_for_day_cash' => (float) ($dailyCashDeposits - $dailyCashCosts),
            ];

            $grandTotals['total_deposits'] += $dailyTotalDeposits;
            $grandTotals['total_cash_deposits'] += $dailyCashDeposits;
            $grandTotals['total_bank_deposits'] += $dailyBankDeposits;
            $grandTotals['total_costs_for_days_with_activity'] += $dailyTotalCosts;
            $grandTotals['net_cash_flow'] += ($dailyCashDeposits - $dailyCashCosts);
        }

        $grandTotals['net_total_income'] = $grandTotals['total_deposits'] - $grandTotals['total_costs_for_days_with_activity'];
        // $grandTotals['net_cash_flow'] = $grandTotals['total_cash_deposits'] - $allCostsForMonth->sum('amount');
        $grandTotals['net_bank_flow'] = $grandTotals['total_bank_deposits'] - $allCostsForMonth->sum('amount_bankak');

        return [
            'daily_data' => $dailyData,
            'summary' => $grandTotals,
            'report_period' => [
                'month_name' => $startDate->translatedFormat('F Y'),
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ]
        ];
    }


    public function exportMonthlyServiceDepositsIncomePdf(Request $request)
    {
        // $this->authorize('export monthly_service_income_report');
        $data = $this->getMonthlyServiceDepositsIncomeData(new Request($request->all() + ['show_empty_days' => true])); // Ensure all days for PDF

        $dailyData = $data['daily_data'];
        $summary = $data['summary'];
        $reportPeriod = $data['report_period'];

        if (empty($dailyData)) {
            return response()->json(['message' => 'لا توجد بيانات لإنشاء التقرير.'], 404);
        }

        $reportTitle = 'تقرير الإيرادات الشهرية من الخدمات';
        $filterCriteria = "لشهر: {$reportPeriod['month_name']}";

        $pdf = new MyCustomTCPDF($reportTitle, $filterCriteria, 'L', 'mm', 'A4'); // Landscape
        $pdf->AddPage();
        $pdf->SetLineWidth(0.1);
        //rtl
        $pdf->SetRTL(true);


        // Table Header
        $headers = ['التاريخ', 'إجمالي الإيداعات', 'إيداعات نقدية', 'إيداعات بنكية', 'إجمالي المصروفات', 'صافي النقدية', 'صافي البنك', 'صافي الدخل اليومي'];
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = array_fill(0, count($headers), $pageWidth / count($headers)); // Equal width
        // Or define specific widths:
        // $colWidths = [35, 35, 35, 35, 35, 35, 35, 0]; 
        // $colWidths[count($colWidths)-1] = $pageWidth - array_sum(array_slice($colWidths,0,-1));
        $alignments = array_fill(0, count($headers), 'C');
        $alignments[0] = 'R'; // Date align right

        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        // Table Body
        $pdf->SetFont('arial', '', 8);
        $fill = false;
        foreach ($dailyData as $day) {
            $rowData = [
                Carbon::parse($day['date'])->translatedFormat('D, M j, Y'), // Localized date
                number_format($day['total_income'], 2),
                number_format($day['total_cash_income'], 2),
                number_format($day['total_bank_income'], 2),
                number_format($day['total_cost'], 2),
                number_format($day['net_cash'], 2),
                number_format($day['net_bank'], 2),
                number_format($day['net_income_for_day'], 2),
            ];
            $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill);
            $fill = !$fill;
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        // Summary Footer for Table
        $pdf->SetFont('arial', 'B', 8.5);
        $summaryRow = [
            'الإجمالي الشهري:',
            number_format($summary['total_deposits'], 2),
            number_format($summary['total_cash_deposits'], 2),
            number_format($summary['total_bank_deposits'], 2),
            number_format($summary['total_costs_for_days_with_activity'], 2),
            number_format($summary['net_cash_flow'], 2),
            number_format($summary['net_bank_flow'], 2),
            number_format($summary['net_total_income'], 2),
        ];
        $pdf->DrawTableRow($summaryRow, $colWidths, $alignments, true, 10); // Filled, height 10

        $pdfFileName = 'monthly_service_income_' . $reportPeriod['from'] . '_' . $reportPeriod['to'] . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }

    public function generateDoctorReclaimsPdf(Request $request)
    {
        // $this->authorize('print doctor_reclaims_report'); // Permission

        $validated = $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'user_id_opened' => 'nullable|integer|exists:users,id', // User who opened/managed the DoctorShift
            'doctor_name_search' => 'nullable|string|max:255',
            // 'status' => 'nullable|string|in:0,1,all', // Usually for reclaims, we consider closed shifts or all
        ]);

        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();

        $query = DoctorShift::with([
            'doctor.specialist:id,name', // Eager load doctor and their specialist
            'user:id,name',              // User who managed the shift
            'generalShift:id',      // General shift info
            // For calculating entitlements, we need visits and their financial details
            'visits.patient',
            'visits.requestedServices.service',
            'visits.patientLabRequests.mainTest',
        ])
            ->whereBetween('start_time', [$startDate, $endDate]); // Filter by DoctorShift start time

        $filterCriteria = ["الفترة من: " . $startDate->format('Y-m-d') . " إلى: " . $endDate->format('Y-m-d')];

        if ($request->filled('user_id_opened')) {
            $query->where('user_id', $validated['user_id_opened']);
            $user = User::find($validated['user_id_opened']);
            if ($user)
                $filterCriteria[] = "المستخدم: " . $user->name;
        }
        if ($request->filled('doctor_name_search')) {
            $searchTerm = $validated['doctor_name_search'];
            $query->whereHas('doctor', fn($q) => $q->where('name', 'LIKE', "%{$searchTerm}%"));
            $filterCriteria[] = "بحث عن طبيب: " . $searchTerm;
        }
        // if ($request->filled('status') && $request->status !== 'all') {
        //     $query->where('status', (bool)$validated['status']);
        //     $filterCriteria[] = "الحالة: " . ((bool)$validated['status'] ? 'مفتوحة' : 'مغلقة');
        // }

        $doctorShifts = $query->orderBy('doctors.name', 'asc') // Requires join for sorting by doctor name
            ->join('doctors', 'doctor_shifts.doctor_id', '=', 'doctors.id')
            ->select('doctor_shifts.*') // Ensure we select all from doctor_shifts
            ->orderBy('doctor_shifts.start_time', 'asc')
            ->get();

        if ($doctorShifts->isEmpty()) {
            return response()->json(['message' => 'لا توجد مناوبات أطباء تطابق هذه الفلاتر لإنشاء التقرير.'], 404);
        }

        // --- Augment with Financial Data ---
        $dataForPdf = [];
        $grandTotals = [
            'total_entitlement' => 0,
            'cash_entitlement' => 0,
            'insurance_entitlement' => 0,
        ];

        foreach ($doctorShifts as $ds) {
            if (!$ds->doctor)
                continue; // Skip if doctor somehow not loaded

            // Reuse logic from DoctorShift model or controller financial summary
            // For simplicity, assuming these methods are available on $ds or can be called:
            $cashEntitlement = $ds->doctor_credit_cash(); // From DoctorShift model
            $insuranceEntitlement = $ds->doctor_credit_company(); // From DoctorShift model
            $staticWage = (float) ($ds->status == false ? $ds->doctor->static_wage : 0); // Static wage if shift is closed
            $totalEntitlement = $cashEntitlement + $insuranceEntitlement + $staticWage;

            $dataForPdf[] = [
                'doctor_name' => $ds->doctor->name,
                'specialist_name' => $ds->doctor->specialist?->name ?? '-',
                'shift_id' => $ds->id,
                'start_time' => Carbon::parse($ds->start_time)->format('Y-m-d H:i'),
                'end_time' => $ds->end_time ? Carbon::parse($ds->end_time)->format('Y-m-d H:i') : 'مفتوحة',
                'total_entitlement' => $totalEntitlement,
                'cash_entitlement' => $cashEntitlement,
                'insurance_entitlement' => $insuranceEntitlement,
                'static_wage' => $staticWage, // For potential breakdown in PDF
                'opened_by' => $ds->user?->name ?? '-',
            ];

            $grandTotals['total_entitlement'] += $totalEntitlement;
            $grandTotals['cash_entitlement'] += $cashEntitlement;
            $grandTotals['insurance_entitlement'] += $insuranceEntitlement;
        }


        // --- PDF Generation ---
        $reportTitle = 'تقرير مستحقات الأطباء';
        $filterCriteriaString = implode(' | ', $filterCriteria);

        $pdf = new MyCustomTCPDF($reportTitle, null, 'L', 'mm', 'A4', true, 'utf-8', false, false, $filterCriteriaString); // Landscape
        $pdf->AddPage();
        $pdf->SetLineWidth(0.1);
        $fontname = 'helvetica';

        // Table Header
        $headers = ['الطبيب', 'التخصص', 'إجمالي المستحق', 'مستحق نقدي', 'مستحق تأمين', 'بواسطة'];
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        // Adjust widths: ID, Start, End, By can be smaller. Names, Entitlements larger.
        $colWidths = [45, 35, 30, 25, 25, 0];
        $colWidths[count($colWidths) - 1] = $pageWidth - array_sum(array_slice($colWidths, 0, -1)); // Last column takes remaining
        $alignments = ['C', 'C', 'C', 'C', 'C', 'C'];

        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        // Table Body
        $pdf->SetFont($fontname, '', 15);
        $fill = false;
        foreach ($dataForPdf as $row) {
            $rowData = [
                $row['doctor_name'],
                $row['specialist_name'],
                number_format($row['total_entitlement'], 2),
                number_format($row['cash_entitlement'], 2),
                number_format($row['insurance_entitlement'], 2),
                $row['opened_by'],
            ];
            $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill, 6, 10); // Height 6
            $fill = !$fill;
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        // Summary Footer for Table
        $pdf->SetFont($fontname, 'B', 10.5);
        $summaryRowPdf = [
            'الإجمالي العام:',
            '', // Span first 5 columns or leave empty
            number_format($grandTotals['total_entitlement'], 2),
            number_format($grandTotals['cash_entitlement'], 2),
            number_format($grandTotals['insurance_entitlement'], 2),
            '', // Empty for "Opened By"
        ];
        // For TCPDF, you might need to draw empty cells to align totals correctly
        $totalAlignments = ['C', 'C', 'C', 'C', 'C', 'C'];
        $pdf->DrawTableRow([
            $summaryRowPdf[0],
            $summaryRowPdf[1],
            $summaryRowPdf[2],
            $summaryRowPdf[3],
            $summaryRowPdf[4],
            $summaryRowPdf[5]
        ], $colWidths, $totalAlignments, true, 7);


        $pdfFileName = 'doctor_reclaims_report_' . $validated['date_from'] . '_to_' . $validated['date_to'] . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");

    }
    public function serviceCostBreakdownReport(Request $request)
    {
        // $this->authorize('view service_cost_breakdown_report'); // Permission check

        $validated = $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'sub_service_cost_id' => 'nullable|integer|exists:sub_service_costs,id', // Optional filter by specific cost type
            'service_id' => 'nullable|integer|exists:services,id', // Optional filter by main service
            'doctor_id' => 'nullable|integer|exists:doctors,id', // Optional filter by doctor if relevant
        ]);

        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();

        $query = RequestedServiceCost::with([
            'subServiceCost:id,name', // Name of the cost type
            'requestedService.service:id,name', // Name of the parent service
            'requestedService.doctorVisit.doctor:id,name' // Doctor if filtering or grouping by doctor
        ])
            ->select(
                'sub_service_cost_id',
                DB::raw('SUM(amount) as total_amount')
            )
            ->whereBetween('requested_service_cost.created_at', [$startDate, $endDate]) // Filter by RequestedServiceCost creation date
            ->groupBy('sub_service_cost_id');

        if ($request->filled('sub_service_cost_id')) {
            $query->where('sub_service_cost_id', $validated['sub_service_cost_id']);
        }
        if ($request->filled('service_id')) {
            $query->whereHas('requestedService.service', function ($q) use ($validated) {
                $q->where('id', $validated['service_id']);
            });
        }
        if ($request->filled('doctor_id')) {
            $query->whereHas('requestedService.doctorVisit.doctor', function ($q) use ($validated) {
                $q->where('id', $validated['doctor_id']);
            });
        }

        // Order by sub_service_cost_id to group them if sub_service_cost_id filter is not applied
        // Or order by total_amount
        $results = $query->orderBy('sub_service_cost_id')->get();

        // We need to fetch SubServiceCost names if not directly joinable or already loaded
        // The with('subServiceCost') above should handle this for results that have it.
        // For a cleaner structure, fetch all SubServiceCost names once.
        $allSubServiceCostTypes = SubServiceCost::pluck('name', 'id');

        $reportData = $results->map(function ($item) use ($allSubServiceCostTypes) {
            return [
                'sub_service_cost_id' => $item->sub_service_cost_id,
                'sub_service_cost_name' => $item->subServiceCost?->name ?? $allSubServiceCostTypes->get($item->sub_service_cost_id) ?? 'Unknown Cost Type',
                'total_amount' => (float) $item->total_amount,
            ];
        });

        return response()->json([
            'data' => $reportData,
            'grand_total_cost' => $reportData->sum('total_amount'),
            'report_period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ]
        ]);
    }

    public function exportServiceCostBreakdownPdf(Request $request)
    {
        // $this->authorize('print service_cost_breakdown_report');

        // Re-use the data fetching logic (consider extracting to a private method or service)
        $jsonResponse = $this->serviceCostBreakdownReport($request);
        $responseData = json_decode($jsonResponse->getContent(), true);

        if ($jsonResponse->getStatusCode() !== 200 || empty($responseData['data'])) {
            // Attempt to generate PDF even for empty data to show "No data"
            // Or return a 404 if that's preferred. Here, we'll generate a PDF indicating no data.
        }

        $reportData = $responseData['data'] ?? [];
        $grandTotalCost = $responseData['grand_total_cost'] ?? 0;
        $reportPeriod = $responseData['report_period'] ?? [
            'from' => $request->date_from,
            'to' => $request->date_to
        ];


        $reportTitle = 'تقرير تفصيل تكاليف الخدمات';
        $filterCriteria = "الفترة من: " . $reportPeriod['from'] . " إلى: " . $reportPeriod['to'];
        // Add other active filters to $filterCriteria string if they were applied

        $pdf = new MyCustomTCPDF($reportTitle, $filterCriteria, 'P', 'mm', 'A4'); // Portrait
        $pdf->AddPage();
        $pdf->SetLineWidth(0.1);
        $fontname = 'helvetica';

        // Table Header
        $headers = ['نوع التكلفة الفرعية', 'إجمالي المبلغ'];
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [$pageWidth * 0.7, $pageWidth * 0.3];
        $alignments = ['R', 'C'];

        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        // Table Body
        $pdf->SetFont($fontname, '', 9);
        $fill = false;
        if (empty($reportData)) {
            $pdf->Cell(array_sum($colWidths), 10, 'لا توجد بيانات لهذه الفترة أو الفلاتر المحددة.', 1, 1, 'C', $fill);
        } else {
            foreach ($reportData as $item) {
                $rowData = [
                    $item['sub_service_cost_name'],
                    number_format($item['total_amount'], 2),
                ];
                $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill, 7); // Height 7
                $fill = !$fill;
            }
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        // Summary Footer for Table
        $pdf->SetFont($fontname, 'B', 10);
        $summaryRowPdf = [
            'الإجمالي العام للتكاليف:',
            number_format($grandTotalCost, 2),
        ];
        $pdf->DrawTableRow($summaryRowPdf, $colWidths, $alignments, true, 8); // Filled, height 8

        $pdfFileName = 'service_cost_breakdown_' . $reportPeriod['from'] . '_to_' . $reportPeriod['to'] . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
    public function doctorStatisticsReport(Request $request)
    {
        // $this->authorize('view doctor_statistics_report'); // Permission

        $validated = $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'doctor_id' => 'nullable|integer|exists:doctors,id', // Optional: filter for a specific doctor
            'specialist_id' => 'nullable|integer|exists:specialists,id', // Optional: filter by specialty
            'sort_by' => 'nullable|string|in:patient_count,total_entitlement,doctor_name', // Add more as needed
            'sort_direction' => 'nullable|string|in:asc,desc',
        ]);

        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();

        // Base query for doctors
        $doctorsQuery = Doctor::query()->with('specialist:id,name');

        if ($request->filled('doctor_id')) {
            $doctorsQuery->where('id', $validated['doctor_id']);
        }
        if ($request->filled('specialist_id')) {
            $doctorsQuery->where('specialist_id', $validated['specialist_id']);
        }

        $doctors = $doctorsQuery->get();
        $reportData = [];

        foreach ($doctors as $doctor) {
            // Get visits for this doctor within the date range
            $visits = DoctorVisit::whereHas('patient', function ($query) use ($doctor) {
                $query->where('doctor_id', $doctor->id);
            })
                ->whereBetween('created_at', [$startDate, $endDate])
                ->with(['patient', 'requestedServices']) // Eager load for calculations
                ->get();

            if ($visits->isEmpty() && !$request->filled('doctor_id')) { // Skip if no visits and not filtering for a specific doctor
                continue;
            }

            $patientCount = $visits->pluck('patient_id')->unique()->count();
            $totalIncomeFromVisits = 0;
            $doctorCashEntitlement = 0;
            $doctorInsuranceEntitlement = 0;

            foreach ($visits as $visit) {
                // Calculate total income generated by this visit (Net after discounts & company endurance)
                // This assumes calculateTotalPaid reflects amount collected towards net patient payable
                $totalIncomeFromVisits += $visit->total_services(); // Or calculateTotalServiceValue if that's defined as net revenue

                // Calculate doctor's entitlement from this visit
                // This uses the method on your Doctor model
                if ($visit->patient?->company_id) {
                    $doctorInsuranceEntitlement += $doctor->doctor_credit($visit); // Pass 'company' context if method requires it
                } else {
                    $doctorCashEntitlement += $doctor->doctor_credit($visit); // Pass 'cash' context if method requires it
                }
            }
            // Add static wage if applicable for the period and doctor
            // $staticWageForPeriod = ... logic to determine doctor's static wage for this period ...
            // $doctorCashEntitlement += $staticWageForPeriod; // Or however it's attributed

            $reportData[] = [
                'doctor_id' => $doctor->id,
                'doctor_name' => $doctor->name,
                'specialist_name' => $doctor->specialist?->name ?? '-',
                'patient_count' => $patientCount,
                'total_income_generated' => (float) $totalIncomeFromVisits,
                'cash_entitlement' => (float) $doctorCashEntitlement,
                'insurance_entitlement' => (float) $doctorInsuranceEntitlement,
                'total_entitlement' => (float) ($doctorCashEntitlement + $doctorInsuranceEntitlement /* + $staticWageForPeriod */),
            ];
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'doctor_name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $reportData = collect($reportData)->sortBy($sortBy, SORT_REGULAR, $sortDirection === 'desc')->values()->all();


        return response()->json([
            'data' => $reportData,
            'report_period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ]
        ]);
    }

    public function exportDoctorStatisticsPdf(Request $request)
    {
        // $this->authorize('print doctor_statistics_report');

        $jsonResponse = $this->doctorStatisticsReport($request); // Call the data fetching method
        $responseData = json_decode($jsonResponse->getContent(), true);

        if ($jsonResponse->getStatusCode() !== 200 || empty($responseData['data'])) {
            // Log error or handle gracefully if needed before PDF attempt
        }

        $reportData = $responseData['data'] ?? [];
        $reportPeriod = $responseData['report_period'] ?? [
            'from' => $request->date_from,
            'to' => $request->date_to
        ];

        $reportTitle = 'تقرير إحصائيات الأطباء';
        $filterCriteria = "الفترة من: " . $reportPeriod['from'] . " إلى: " . $reportPeriod['to'];
        // Append other applied filters to filterCriteria string

        $pdf = new MyCustomTCPDF($reportTitle, $filterCriteria, 'L', 'mm', 'A4'); // Landscape
        $pdf->AddPage();
        $fontname = 'helvetica';

        // Table Header
        $headers = ['الطبيب', 'التخصص', 'عدد المرضى', 'إجمالي الدخل المحقق', 'مستحق نقدي', 'مستحق تأمين', 'إجمالي المستحقات'];
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        // Adjust widths
        $colWidths = [50, 35, 25, 40, 30, 30, 0];
        $colWidths[count($colWidths) - 1] = $pageWidth - array_sum(array_slice($colWidths, 0, -1));
        $alignments = ['R', 'R', 'C', 'C', 'C', 'C', 'C'];

        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        // Table Body
        $pdf->SetFont($fontname, '', 8);
        $fill = false;
        $grandTotals = ['patients' => 0, 'income' => 0, 'cash_ent' => 0, 'ins_ent' => 0, 'total_ent' => 0];

        if (empty($reportData)) {
            $pdf->Cell(array_sum($colWidths), 10, 'لا توجد بيانات لهذه الفترة أو الفلاتر المحددة.', 1, 1, 'C', $fill);
        } else {
            foreach ($reportData as $row) {
                $rowData = [
                    $row['doctor_name'],
                    $row['specialist_name'],
                    $row['patient_count'],
                    number_format($row['total_income_generated'], 2),
                    number_format($row['cash_entitlement'], 2),
                    number_format($row['insurance_entitlement'], 2),
                    number_format($row['total_entitlement'], 2),
                ];
                $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill, 7);
                $fill = !$fill;

                $grandTotals['patients'] += $row['patient_count'];
                $grandTotals['income'] += $row['total_income_generated'];
                $grandTotals['cash_ent'] += $row['cash_entitlement'];
                $grandTotals['ins_ent'] += $row['insurance_entitlement'];
                $grandTotals['total_ent'] += $row['total_entitlement'];
            }
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        // Summary Footer for Table
        if (!empty($reportData)) {
            $pdf->SetFont($fontname, 'B', 8.5);
            $summaryRowPdf = [
                'الإجمالي العام:',
                '', // Span 2 columns
                $grandTotals['patients'],
                number_format($grandTotals['income'], 2),
                number_format($grandTotals['cash_ent'], 2),
                number_format($grandTotals['ins_ent'], 2),
                number_format($grandTotals['total_ent'], 2),
            ];
            $pdf->DrawTableRow([
                $summaryRowPdf[0],
                $summaryRowPdf[1],
                $summaryRowPdf[2],
                $summaryRowPdf[3],
                $summaryRowPdf[4],
                $summaryRowPdf[5],
                $summaryRowPdf[6]
            ], $colWidths, $alignments, true, 8);
        }

        $pdfFileName = 'doctor_statistics_report_' . $reportPeriod['from'] . '_to_' . $reportPeriod['to'] . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
    public function companyPerformanceReport(Request $request)
    {
        // $this->authorize('view company_performance_report'); // Permission

        $validated = $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'company_id' => 'nullable|integer|exists:companies,id', // Optional: filter for a specific company
            'sort_by' => 'nullable|string|in:company_name,patient_count,net_income_from_company_patients,total_endurance,net_income',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ]);

        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();

        $companiesQuery = Company::query()->where('status', true); // Typically active companies

        if ($request->filled('company_id')) {
            $companiesQuery->where('id', $validated['company_id']);
        }

        $companies = $companiesQuery->orderBy('name')->get();
        $reportData = [];

        foreach ($companies as $company) {
            // Get visits for patients of this company within the date range
            $visitsForCompany = DoctorVisit::whereHas('patient', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })
                ->whereBetween('visit_date', [$startDate, $endDate])
                ->with(['requestedServices', 'patientLabRequests']) // Eager load for calculations
                ->get();

            if ($visitsForCompany->isEmpty() && !$request->filled('company_id')) {
                continue; // Skip if no visits and not filtering for this specific company
            }

            $patientCount = $visitsForCompany->pluck('patient_id')->unique()->count();
            $totalIncomeFromCompanyPatients = 0; // Total collected (cash + bank) from services/labs for these patients
            $totalCompanyEndurance = 0;       // Total amount covered by the company

            foreach ($visitsForCompany as $visit) {
                // Calculate income from services for this visit
                foreach ($visit->requestedServices as $rs) {
                    // Income here means the full price of service before endurance (what clinic charges)
                    // Or, if you mean "collected income", then sum $rs->amount_paid
                    // Let's assume "total income" means total billable value before endurance for services provided to these patients
                    $itemPrice = (float) $rs->price;
                    $itemCount = (int) ($rs->count ?? 1);
                    $itemSubTotal = $itemPrice * $itemCount;

                    // Subtract discounts if they apply before company endurance
                    $discountFromPercentage = ($itemSubTotal * (intval($rs->discount_per) ?? 0)) / 100;
                    $fixedDiscount = intval($rs->discount) ?? 0;
                    $itemNetAfterDiscount = $itemSubTotal - $discountFromPercentage - $fixedDiscount;

                    $totalIncomeFromCompanyPatients += $itemNetAfterDiscount;
                    $totalCompanyEndurance += ((float) ($rs->endurance ?? 0) * $itemCount); // Endurance is per item
                }

                // Calculate income and endurance from lab requests for this visit
                foreach ($visit->patientLabRequests as $lr) {
                    $labPrice = (float) $lr->price;
                    $labDiscount = ($labPrice * (float) ($lr->discount_per ?? 0)) / 100;
                    $labNetAfterDiscount = $labPrice - $labDiscount;

                    $totalIncomeFromCompanyPatients += $labNetAfterDiscount;
                    $totalCompanyEndurance += (float) ($lr->endurance ?? 0); // Assuming endurance is on LabRequest
                }
            }

            $netIncomeAfterEndurance = $totalIncomeFromCompanyPatients - $totalCompanyEndurance;

            $reportData[] = [
                'company_id' => $company->id,
                'company_name' => $company->name,
                'patient_count' => $patientCount,
                'total_income_generated' => round($totalIncomeFromCompanyPatients, 2), // Total value of services/labs for these patients
                'total_endurance_by_company' => round($totalCompanyEndurance, 2), // What company covered
                'net_income_from_company_patients' => round($netIncomeAfterEndurance, 2), // What patient paid + what clinic gets after company coverage
            ];
        }

        // Sorting
        $sortBy = $validated['sort_by'] ?? 'company_name';
        $sortDirection = $validated['sort_direction'] ?? 'asc';
        $reportData = collect($reportData)->sortBy($sortBy, SORT_REGULAR, $sortDirection === 'desc')->values()->all();

        return response()->json([
            'data' => $reportData,
            'report_period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ]
        ]);
    }

    public function exportCompanyPerformancePdf(Request $request)
    {
        // $this->authorize('print company_performance_report');
        $jsonResponse = $this->companyPerformanceReport($request);
        $responseData = json_decode($jsonResponse->getContent(), true);

        if ($jsonResponse->getStatusCode() !== 200 || empty($responseData['data'])) {
            // Handle no data for PDF
        }

        $reportData = $responseData['data'] ?? [];
        $reportPeriod = $responseData['report_period'] ?? [
            'from' => $request->date_from,
            'to' => $request->date_to
        ];

        $reportTitle = 'تقرير أداء شركات التأمين';
        $filterCriteria = "الفترة من: " . $reportPeriod['from'] . " إلى: " . $reportPeriod['to'];
        if ($request->filled('company_id')) {
            $company = Company::find($request->company_id);
            if ($company)
                $filterCriteria .= " | الشركة: " . $company->name;
        }

        $pdf = new MyCustomTCPDF($reportTitle, $filterCriteria, 'L', 'mm', 'A4');
        $pdf->AddPage();
        $fontname = 'helvetica';

        $headers = ['الشركة', 'عدد المرضى', 'إجمالي الدخل من مرضى الشركة', 'إجمالي تحمل الشركة', 'صافي الدخل (بعد التحمل)'];
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [70, 30, 50, 50, 0];
        $colWidths[count($colWidths) - 1] = $pageWidth - array_sum(array_slice($colWidths, 0, -1));
        $alignments = ['R', 'C', 'C', 'C', 'C'];

        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        $pdf->SetFont($fontname, '', 8);
        $fill = false;
        $grandTotals = ['patients' => 0, 'income' => 0, 'endurance' => 0, 'net' => 0];

        if (empty($reportData)) {
            $pdf->Cell(array_sum($colWidths), 10, 'لا توجد بيانات.', 1, 1, 'C', $fill);
        } else {
            foreach ($reportData as $row) {
                $rowData = [
                    $row['company_name'],
                    $row['patient_count'],
                    number_format($row['total_income_generated'], 2),
                    number_format($row['total_endurance_by_company'], 2),
                    number_format($row['net_income_from_company_patients'], 2),
                ];
                $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill, 7);
                $fill = !$fill;

                $grandTotals['patients'] += $row['patient_count'];
                $grandTotals['income'] += $row['total_income_generated'];
                $grandTotals['endurance'] += $row['total_endurance_by_company'];
                $grandTotals['net'] += $row['net_income_from_company_patients'];
            }
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        if (!empty($reportData)) {
            $pdf->SetFont($fontname, 'B', 8.5);
            $summaryRowPdf = [
                'الإجمالي العام:',
                $grandTotals['patients'],
                number_format($grandTotals['income'], 2),
                number_format($grandTotals['endurance'], 2),
                number_format($grandTotals['net'], 2),
            ];
            $pdf->DrawTableRow($summaryRowPdf, $colWidths, $alignments, true, 8);
        }

        $pdfFileName = 'company_performance_report_' . $reportPeriod['from'] . '_to_' . $reportPeriod['to'] . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
    /**
     * Get doctor's entitlement from insurance companies for a given period.
     */
    private function getDoctorCompanyEntitlementData(Request $request): array
    {
        $validated = $request->validate([
            'doctor_id' => 'required|integer|exists:doctors,id',
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
        ]);

        $doctorId = $validated['doctor_id'];
        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();

        $doctor = Doctor::findOrFail($doctorId); // Ensure doctor exists

        // Fetch doctor shifts for the specified doctor and date range
        // The calculation relies on DoctorShift -> visits -> patient -> company
        // and DoctorShift -> doctor -> doctor_credit() method
        $doctorShifts = DoctorShift::with([
            'doctor', // Needed for doctor_credit method context
            'visits.patient.company', // Crucial for grouping by company and for doctor_credit context
            'visits.requestedServices.service', // Needed if doctor_credit delves into services
            'visits.patientLabRequests.mainTest'   // Needed if doctor_credit delves into labs
        ])
            ->where('doctor_id', $doctorId)
            // Filter shifts that were active *during* any part of the date range
            ->where(function ($query) use ($startDate, $endDate) {
                $query->whereBetween('start_time', [$startDate, $endDate]) // Shifts started within period
                    ->orWhere(function ($q) use ($startDate, $endDate) { // Shifts started before but ended in period or are still open and started before end
                        $q->where('start_time', '<', $startDate)
                            ->where(function ($q2) use ($startDate) {
                            $q2->whereNull('end_time')
                                ->orWhere('end_time', '>=', $startDate); // Corrected: shift ended on or after start of period
                        });
                    });
            })
            ->get();

        $companyTotals = [];

        foreach ($doctorShifts as $shift) {
            // Ensure the doctor context for doctor_credit is the one from the shift
            $currentShiftDoctor = $shift->doctor;
            if (!$currentShiftDoctor)
                continue;

            $visitsForCompanyPatients = $shift->visits->filter(function ($visit) {
                return $visit->patient && $visit->patient->company_id;
            });

            foreach ($visitsForCompanyPatients as $visit) {
                $company = $visit->patient->company; // Company model instance
                if (!$company)
                    continue;

                $companyId = $company->id;
                // The doctor_credit method on Doctor model should calculate entitlement for THIS visit
                $entitlementFromThisVisit = $currentShiftDoctor->doctor_credit($visit); // Pass 'company' if method requires type

                if (!isset($companyTotals[$companyId])) {
                    $companyTotals[$companyId] = [
                        'company_id' => $companyId,
                        'company_name' => $company->name,
                        'total_entitlement' => 0,
                    ];
                }
                $companyTotals[$companyId]['total_entitlement'] += $entitlementFromThisVisit;
            }
        }

        $reportData = array_values($companyTotals);
        // Sort by company name or amount
        usort($reportData, fn($a, $b) => $request->input('sort_by', 'company_name') === 'amount'
            ? ($b['total_entitlement'] <=> $a['total_entitlement']) // Desc by amount
            : ($a['company_name'] <=> $b['company_name'])); // Asc by name

        return [
            'data' => $reportData,
            'doctor_name' => $doctor->name,
            'report_period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
            'grand_total_entitlement' => collect($reportData)->sum('total_entitlement')
        ];
    }

    public function doctorCompanyEntitlementReport(Request $request)
    {
        // $this->authorize('view doctor_company_entitlement_report');
        $data = $this->getDoctorCompanyEntitlementData($request);
        return response()->json($data);
    }

    public function exportDoctorCompanyEntitlementPdf(Request $request)
    {
        // $this->authorize('print doctor_company_entitlement_report');
        $reportContent = $this->getDoctorCompanyEntitlementData($request);

        $dataForPdf = $reportContent['data'];
        $doctorName = $reportContent['doctor_name'];
        $reportPeriod = $reportContent['report_period'];
        $grandTotalEntitlement = $reportContent['grand_total_entitlement'];

        if (empty($dataForPdf)) {
            // Return a message or an empty PDF indicating no data
        }

        $reportTitle = 'تقرير مستحقات الطبيب من شركات التأمين';
        $filterCriteria = "الطبيب: {$doctorName} | الفترة من: {$reportPeriod['from']} إلى: {$reportPeriod['to']}";

        $pdf = new MyCustomTCPDF($reportTitle, $filterCriteria, 'P', 'mm', 'A4');
        $pdf->AddPage();
        $fontname = 'helvetica';

        $headers = ['شركة التأمين', 'إجمالي المستحقات للطبيب'];
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [$pageWidth * 0.65, $pageWidth * 0.35];
        $alignments = ['R', 'C'];

        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        $pdf->SetFont($fontname, '', 9);
        $fill = false;
        if (empty($dataForPdf)) {
            $pdf->Cell(array_sum($colWidths), 10, 'لا توجد مستحقات من شركات لهذا الطبيب خلال الفترة المحددة.', 1, 1, 'C', $fill);
        } else {
            foreach ($dataForPdf as $row) {
                $rowData = [
                    $row['company_name'],
                    number_format($row['total_entitlement'], 2),
                ];
                $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill, 7);
                $fill = !$fill;
            }
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        if (!empty($dataForPdf)) {
            $pdf->SetFont($fontname, 'B', 10);
            $summaryRowPdf = [
                'الإجمالي العام للمستحقات:',
                number_format($grandTotalEntitlement, 2),
            ];
            $pdf->DrawTableRow($summaryRowPdf, $colWidths, $alignments, true, 8);
        }

        $pdfFileName = 'DoctorCompanyEntitlement_' . preg_replace('/[^A-Za-z0-9_]/', '_', $doctorName) . '_' . $reportPeriod['from'] . '_to_' . $reportPeriod['to'] . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
    public function yearlyIncomeComparisonByMonth(Request $request)
    {
        // $this->authorize('view yearly_income_comparison_report'); // Permission

        $validated = $request->validate([
            'year' => 'required|integer|digits:4|min:2000|max:' . (date('Y') + 5),
        ]);

        $year = $validated['year'];

        // Fetch deposits, group by month, sum amounts
        // We use created_at of the deposit for income recognition month
        $monthlyIncomeData = RequestedServiceDeposit::selectRaw('MONTH(created_at) as month, SUM(amount) as total_income')
            ->whereYear('created_at', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Prepare data for chart (ensure all 12 months are present, even if with 0 income)
        $chartData = [];
        $monthNames = []; // For X-axis labels if needed directly from backend

        for ($m = 1; $m <= 12; $m++) {
            $monthData = $monthlyIncomeData->firstWhere('month', $m);
            $monthName = Carbon::create()->month($m)->translatedFormat('F'); // Localized month name

            $chartData[] = [
                'month' => $m, // Could also send month number e.g., 'Jan', 'Feb'
                'month_name' => $monthName,
                'total_income' => $monthData ? (float) $monthData->total_income : 0,
            ];
            $monthNames[] = $monthName; // Collect month names
        }

        // Additionally, you can calculate overall stats for the year
        $totalYearlyIncome = $monthlyIncomeData->sum('total_income');
        $averageMonthlyIncome = $totalYearlyIncome / 12;


        return response()->json([
            'data' => $chartData, // Array of { month_name: 'January', total_income: 12345.67 }
            'meta' => [
                'year' => (int) $year,
                'total_yearly_income' => round($totalYearlyIncome, 2),
                'average_monthly_income' => round($averageMonthlyIncome, 2),
                // 'month_labels_for_chart' => $monthNames, // Frontend can generate this too
            ]
        ]);
    }
    public function yearlyPatientFrequencyByMonth(Request $request)
    {
        // $this->authorize('view yearly_patient_frequency_report'); // Permission

        $validated = $request->validate([
            'year' => 'required|integer|digits:4|min:2000|max:' . (date('Y') + 5),
        ]);

        $year = $validated['year'];

        // Fetch distinct patient counts per month based on visit_date
        $monthlyPatientCounts = DoctorVisit::selectRaw('MONTH(visit_date) as month, COUNT(DISTINCT patient_id) as patient_count')
            ->whereYear('visit_date', $year)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $chartData = [];
        for ($m = 1; $m <= 12; $m++) {
            $monthData = $monthlyPatientCounts->firstWhere('month', $m);
            $monthName = Carbon::create()->month($m)->translatedFormat('F');

            $chartData[] = [
                'month' => $m,
                'month_name' => $monthName,
                'patient_count' => $monthData ? (int) $monthData->patient_count : 0,
            ];
        }

        // Overall stats for the year
        $totalUniquePatientsYearly = DoctorVisit::whereYear('visit_date', $year)->distinct('patient_id')->count('patient_id');
        $averageMonthlyPatients = $totalUniquePatientsYearly > 0 ? round($totalUniquePatientsYearly / 12, 2) : 0;
        // If you want average of monthly sums (different from distinct yearly patients / 12):
        // $averageMonthlyPatientsAlternative = $monthlyPatientCounts->avg('patient_count') ?? 0;


        return response()->json([
            'data' => $chartData, // Array of { month_name: 'January', patient_count: 150 }
            'meta' => [
                'year' => (int) $year,
                'total_unique_patients_yearly' => $totalUniquePatientsYearly,
                'average_monthly_patients' => $averageMonthlyPatients,
            ]
        ]);
    }

    // === LAB RELATED PDFS ===
    public function sendVisitReportViaWhatsApp(Request $request, DoctorVisit $visit)
    {
        // Add permission check: e.g., can('send_whatsapp_reports', $visit)
        $validated = $request->validate([
            'chat_id' => 'required|string', // Expecting raw phone, will be formatted
            'caption' => 'nullable|string|max:1000',
            'report_type' => 'required|string|in:full_lab_report,thermal_lab_receipt',
        ]);

        if (!$this->whatsAppService->isConfigured()) {
            return response()->json(['message' => 'WhatsApp service is not configured on the server.'], 503);
        }

        $patient = $visit->patient;
        if (!$patient) {
            return response()->json(['message' => 'Patient not found for this visit.'], 404);
        }
        
        // Format phone number using your service (it uses default country code from settings)
        $formattedChatId = UltramsgService::formatPhoneNumber($validated['chat_id']);
        if (!$formattedChatId) {
            return response()->json(['message' => 'Invalid phone number format for WhatsApp.'], 422);
        }

        $pdfContentBase64 = null;
        $pdfFileName = 'report.pdf';

        try {
            // Generate PDF based on report type
            if ($validated['report_type'] === 'full_lab_report') {
                // Get the PDF content directly by modifying how generateLabVisitReportPdf works
                $pdfContent = $this->generateLabVisitReportPdfContent($request, $visit);
                $pdfFileName = 'Lab_Report_Visit_' . $visit->id . '.pdf';
            } elseif ($validated['report_type'] === 'thermal_lab_receipt') {
                // Get the PDF content directly by modifying how generateLabThermalReceiptPdf works
                $pdfContent = $this->generateLabThermalReceiptPdfContent($request, $visit);
                $pdfFileName = 'Lab_Receipt_Visit_' . $visit->id . '.pdf';
            } else {
                return response()->json(['message' => 'Invalid report type specified.'], 422);
            }

            if (empty($pdfContent)) {
                Log::error("WhatsApp Send: PDF generation failed or returned empty for visit {$visit->id}, type {$validated['report_type']}.");
                return response()->json(['message' => 'Failed to generate PDF content.'], 500);
            }
            
            $pdfContentBase64 = base64_encode($pdfContent);
        } catch (\Exception $e) {
            Log::error("WhatsApp Send: PDF generation error for visit {$visit->id}, type {$validated['report_type']}: " . $e->getMessage());
            return response()->json(['message' => 'Failed to generate PDF content: ' . $e->getMessage()], 500);
        }

        $caption = $validated['caption'] ?? "Lab results for {$patient->name}";
    

        $result = $this->whatsAppService->sendMediaMessage(
            $formattedChatId,
            $pdfContentBase64,
            $pdfFileName,
            $caption,
            true // asDocument = true for PDFs
        );

        if ($result['success']) {
            // Log successful sending if needed
            // ActivityLog::create([...]);
            return response()->json(['message' => 'Report sent successfully via WhatsApp.', 'data' => $result['data']]);
        } else {
            return response()->json(['message' => $result['error'] ?? 'Failed to send report via WhatsApp.', 'details' => $result['data']], 500);
        }
    }

    /**
     * Generate PDF content for lab visit report (for WhatsApp sending)
     * This is a helper method that returns raw PDF content instead of a Response
     */
    private function generateLabVisitReportPdfContent(Request $request, DoctorVisit $doctorvisit): string
    {
        // Eager load all necessary data
        $doctorvisit->loadDefaultLabReportRelations();

        $labRequestsToReport = $doctorvisit->patientLabRequests->filter(function ($lr) {
            if (!$lr->mainTest)
                return false; // Skip if mainTest relation isn't loaded properly
            return $lr->results->where(fn($r) => $r->result !== null && $r->result !== '')->isNotEmpty() ||
                !$lr->mainTest->divided ||
                $lr->requestedOrganisms->isNotEmpty();
        });

        if ($labRequestsToReport->isEmpty()) {
            throw new \Exception('No results or relevant tests to report for this visit.');
        }

        $appSettings = Setting::instance();

        // Pass the $visit context to MyCustomTCPDF constructor
        $pdf = new MyCustomTCPDF(
            'Lab Result Report', // Title for PDF metadata
            $doctorvisit,              // Visit context for Header/Footer of MyCustomTCPDF
            'P',
            'mm',
            'A4'      // Default orientation, unit, format
        );

        $pdf->AddPage(); // This triggers MyCustomTCPDF::Header()
        $pdf->setRTL(false);
        $firstTestOnPage = true;

        foreach ($labRequestsToReport as $labRequest) {
            $mainTest = $labRequest->mainTest;
            if (!$mainTest)
                continue;

            $estimatedHeight = $this->estimateMainTestBlockHeightForReport($pdf, $labRequest);

            if (
                !$firstTestOnPage &&
                ($mainTest->pageBreak || ($pdf->GetY() + $estimatedHeight > ($pdf->getPageHeight() - $pdf->getBreakMargin())))
            ) {
                $pdf->AddPage(); // This also calls MyCustomTCPDF::Header()
            } elseif (!$firstTestOnPage) {
                $pdf->Ln(3); // Space between main test blocks on the same page
            }

            // Draw the content specific to this MainTest (results, organisms, comments)
            $this->drawMainTestContentBlock($pdf, $labRequest, $appSettings);
            $firstTestOnPage = false;
        }

        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $doctorvisit->patient->name);
        $pdfFileName = 'LabReport_Visit_' . $doctorvisit->id . '_' . $patientNameSanitized . '.pdf';
        return $pdf->Output($pdfFileName, 'S'); // 'S' returns as string
    }

    /**
     * Generate PDF content for lab thermal receipt (for WhatsApp sending)
     * This is a helper method that returns raw PDF content instead of a Response
     */
    private function generateLabThermalReceiptPdfContent(Request $request, DoctorVisit $visit): string
    {
        $visit->load([
            'patient:id,name,phone,company_id',
            'patient.company:id,name',
            'patientLabRequests.mainTest:id,main_test_name',
            'patientLabRequests.depositUser:id,name',
            'user:id,name', // User who created visit
            'doctor:id,name',
        ]);

        $labRequestsToPrint = $visit->patientLabRequests;

        if ($labRequestsToPrint->isEmpty()) {
            throw new \Exception('No paid/partially paid lab requests for this visit to create a receipt.');
        }

        $appSettings = Setting::instance();
        $isCompanyPatient = !empty($visit->patient->company_id);
        $cashierName = Auth::user()?->name ?? $visit->user?->name ?? $labRequestsToPrint->first()?->depositUser?->name ?? 'System';

        $pdf = new MyCustomTCPDF('Lab Receipt', $visit);
        $pdf->SetRightMargin(5);
        $pdf->SetLeftMargin(5);
        $thermalWidth = (float) ($appSettings?->thermal_printer_width ?? 70);
        $pdf->setThermalDefaults($thermalWidth);
        $pdf->AddPage();

        $fontName = 'helvetica';
        $isRTL = $pdf->getRTL();
        $alignStart = $isRTL ? 'R' : 'L';
        $alignCenter = 'C';
        $lineHeight = 3.5;

        // Clinic Header
        $logoData = null;
        if ($appSettings?->logo_base64 && str_starts_with($appSettings->logo_base64, 'data:image')) {
            try {
                $logoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $appSettings->logo_base64));
            } catch (\Exception $e) {
            }
        }
        if ($logoData) {
            $pdf->Image('@' . $logoData, '', $pdf->GetY() + 1, 15, 0, '', '', 'T', false, 300, $alignCenter, false, false, 0);
            $pdf->Ln($logoData ? 10 : 1);
        }
        $pdf->SetFont($fontName, 'B', $logoData ? 8 : 9);
        $pdf->MultiCell(0, $lineHeight, $appSettings?->hospital_name ?: ($appSettings?->lab_name ?: config('app.name')), 0, $alignCenter, false, 1);
        $pdf->SetFont($fontName, '', 6);
        if ($appSettings?->address)
            $pdf->MultiCell(0, $lineHeight - 0.5, $appSettings->address, 0, $alignCenter, false, 1);
        if ($appSettings?->phone)
            $pdf->MultiCell(0, $lineHeight - 0.5, ($isRTL ? "هاتف: " : "Tel: ") . $appSettings->phone, 0, $alignCenter, false, 1);
        if ($appSettings?->vatin)
            $pdf->MultiCell(0, $lineHeight - 0.5, ($isRTL ? "ر.ض: " : "VAT: ") . $appSettings->vatin, 0, $alignCenter, false, 1);
        $pdf->Ln(1);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(1);

        // Receipt Info
        $pdf->SetFont($fontName, '', 6.5);
        $receiptNumber = "LAB-" . $visit->id . "-" . $labRequestsToPrint->first()?->id;
        $pdf->Cell(0, $lineHeight, ($isRTL ? "زيارة رقم: " : "Visit #: ") . $visit->id, 0, 1, $alignStart);
        $pdf->Cell(0, $lineHeight, ($isRTL ? "التاريخ: " : "Date: ") . Carbon::now()->format('Y/m/d H:i A'), 0, 1, $alignStart);
        $pdf->Cell(0, $lineHeight, ($isRTL ? "المريض: " : "Patient: ") . $visit->patient->name, 0, 1, $alignStart);
        if ($visit->patient->phone)
            $pdf->Cell(0, $lineHeight, ($isRTL ? "الهاتف: " : "Phone: ") . $visit->patient->phone, 0, 1, $alignStart);
        if ($isCompanyPatient && $visit->patient->company)
            $pdf->Cell(0, $lineHeight, ($isRTL ? "الشركة: " : "Company: ") . $visit->patient->company->name, 0, 1, $alignStart);
        if ($visit->doctor)
            $pdf->Cell(0, $lineHeight, ($isRTL ? "الطبيب: " : "Doctor: ") . $visit->doctor->name, 0, 1, $alignStart);
        $pdf->Cell(0, $lineHeight, ($isRTL ? "الكاشير: " : "Cashier: ") . $cashierName, 0, 1, $alignStart);

        if ($appSettings?->barcode && $labRequestsToPrint->first()?->id) { /* ... Barcode ... */
        }
        $pdf->Ln(1);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(0.5);

        // Items Table
        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $nameWidth = $pageUsableWidth * 0.50;
        $qtyWidth = $pageUsableWidth * 0.10;
        $priceWidth = $pageUsableWidth * 0.20;
        $totalWidth = $pageUsableWidth * 0.20;
        $pdf->SetFont($fontName, 'B', 7.5);
        $pdf->Cell($nameWidth, $lineHeight, ($isRTL ? 'البيان' : 'Item'), 'B', 0, $alignStart);
        $pdf->Cell($qtyWidth, $lineHeight, ($isRTL ? 'كمية' : 'Qty'), 'B', 0, $alignCenter);
        $pdf->Cell($priceWidth, $lineHeight, ($isRTL ? 'سعر' : 'Price'), 'B', 0, $alignCenter);
        $pdf->Cell($totalWidth, $lineHeight, ($isRTL ? 'إجمالي' : 'Total'), 'B', 1, $alignCenter);
        $pdf->SetFont($fontName, '', 7.5);

        $subTotalLab = 0;
        $totalDiscountOnLab = 0;
        $totalEnduranceOnLab = 0;
        foreach ($labRequestsToPrint as $lr) {
            $testName = $lr->mainTest?->main_test_name ?? 'Test N/A';
            $quantity = (int) ($lr->count ?? 1);
            $unitPrice = (float) ($lr->price ?? 0);
            $itemGrossTotal = $unitPrice * $quantity;
            $subTotalLab += $itemGrossTotal;
            $itemDiscountPercent = (float) ($lr->discount_per ?? 0);
            $itemDiscountAmount = ($itemGrossTotal * $itemDiscountPercent) / 100;
            $totalDiscountOnLab += $itemDiscountAmount;
            if ($isCompanyPatient) {
                $itemEndurance = (float) ($lr->endurance ?? 0) * $quantity;
                $totalEnduranceOnLab += $itemEndurance;
            }
            $currentYbeforeMultiCell = $pdf->GetY();
            $pdf->MultiCell($nameWidth, $lineHeight - 0.5, $testName, 0, $alignStart, false, 0, '', '', true, 0, false, true, 0, 'T');
            $yAfterMultiCell = $pdf->GetY();
            $pdf->SetXY($pdf->getMargins()['left'] + $nameWidth, $currentYbeforeMultiCell);
            $pdf->Cell($qtyWidth, $lineHeight - 0.5, $quantity, 0, 0, $alignCenter);
            $pdf->Cell($priceWidth, $lineHeight - 0.5, number_format($unitPrice, 2), 0, 0, $alignCenter);
            $pdf->Cell($totalWidth, $lineHeight - 0.5, number_format($itemGrossTotal, 2), 0, 1, $alignCenter);
            $pdf->SetY(max($yAfterMultiCell, $currentYbeforeMultiCell + $lineHeight - 0.5));
        }
        $pdf->Ln(0.5);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(0.5);

        // Totals Section
        $pdf->SetFont($fontName, '', 7);
        $netAfterDiscount = $subTotalLab - $totalDiscountOnLab;
        $netPayableByPatient = $netAfterDiscount - ($isCompanyPatient ? $totalEnduranceOnLab : 0);
        $totalActuallyPaidForTheseLabs = $labRequestsToPrint->sum(fn($lr) => (float) $lr->amount_paid);
        $balanceDueForTheseLabs = $netPayableByPatient - $totalActuallyPaidForTheseLabs;

        $this->drawThermalTotalRow($pdf, ($isRTL ? 'الإجمالي الفرعي:' : 'Subtotal:'), $subTotalLab, $pageUsableWidth);
        if ($totalDiscountOnLab > 0)
            $this->drawThermalTotalRow($pdf, ($isRTL ? 'الخصم:' : 'Discount:'), -$totalDiscountOnLab, $pageUsableWidth);
        if ($isCompanyPatient && $totalEnduranceOnLab > 0)
            $this->drawThermalTotalRow($pdf, ($isRTL ? 'تحمل الشركة:' : 'Company Share:'), -$totalEnduranceOnLab, $pageUsableWidth);
        $pdf->SetFont($fontName, 'B', 7.5);
        $this->drawThermalTotalRow($pdf, ($isRTL ? 'الصافي المطلوب:' : 'Net Payable:'), $netPayableByPatient, $pageUsableWidth, true);
        $pdf->SetFont($fontName, '', 7);
        $this->drawThermalTotalRow($pdf, ($isRTL ? 'المدفوع:' : 'Paid:'), $totalActuallyPaidForTheseLabs, $pageUsableWidth);
        $pdf->SetFont($fontName, 'B', 7.5);
        $this->drawThermalTotalRow($pdf, ($isRTL ? 'المتبقي:' : 'Balance:'), $balanceDueForTheseLabs, $pageUsableWidth, true);

        // Footer Message, Watermark
        if ($appSettings?->show_water_mark) { /* ... Watermark logic ... */
        }
        $pdf->Ln(3);
        $pdf->SetFont($fontName, 'I', 6);
        $footerMessage = $appSettings?->receipt_footer_message ?: ($isRTL ? 'شكراً لزيارتكم!' : 'Thank you for your visit!');
        $pdf->MultiCell(0, $lineHeight - 1, $footerMessage, 0, $alignCenter, false, 1);
        $pdf->Ln(3);

        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $visit->patient->name);
        $pdfFileName = 'LabReceipt_Visit_' . $visit->id . '_' . $patientNameSanitized . '.pdf';
        return $pdf->Output($pdfFileName, 'S'); // 'S' returns as string
    }

    /**
     * Generate a thermal receipt for lab requests associated with a visit.
     */
    public function generateLabThermalReceiptPdf(Request $request, DoctorVisit $visit)
    {
        // Permission Check
        // if (!Auth::user()->can('print lab_receipt', $visit)) { return response()->json(['message' => 'Unauthorized'], 403); }

        $visit->load([
            'patient:id,name,phone,company_id',
            'patient.company:id,name',

            'patientLabRequests.mainTest:id,main_test_name',
            'patientLabRequests.depositUser:id,name',
            'user:id,name', // User who created visit
            'doctor:id,name',
        ]);

        $labRequestsToPrint = $visit->patientLabRequests;

        if ($labRequestsToPrint->isEmpty()) {
            return response()->json(['message' => 'No paid/partially paid lab requests for this visit to create a receipt.'], 404);
        }

        $appSettings = Setting::instance();
        $isCompanyPatient = !empty($visit->patient->company_id);
        $cashierName = Auth::user()?->name ?? $visit->user?->name ?? $labRequestsToPrint->first()?->depositUser?->name ?? 'System';

        $pdf = new MyCustomTCPDF('Lab Receipt', $visit);
        $pdf->SetRightMargin(5);
        $pdf->SetLeftMargin(5);
        $thermalWidth = (float) ($appSettings?->thermal_printer_width ?? 70);
        $pdf->setThermalDefaults($thermalWidth);
        $pdf->AddPage();

        $fontName = 'helvetica';
        $isRTL = $pdf->getRTL();
        $alignStart = $isRTL ? 'R' : 'L';
        $alignCenter = 'C';
        $lineHeight = 3.5;

        // Clinic Header
        $logoData = null;
        if ($appSettings?->logo_base64 && str_starts_with($appSettings->logo_base64, 'data:image')) {
            try {
                $logoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $appSettings->logo_base64));
            } catch (\Exception $e) {
            }
        }
        if ($logoData) {
            $pdf->Image('@' . $logoData, '', $pdf->GetY() + 1, 15, 0, '', '', 'T', false, 300, $alignCenter, false, false, 0);
            $pdf->Ln($logoData ? 10 : 1);
        }
        $pdf->SetFont($fontName, 'B', $logoData ? 8 : 9);
        $pdf->MultiCell(0, $lineHeight, $appSettings?->hospital_name ?: ($appSettings?->lab_name ?: config('app.name')), 0, $alignCenter, false, 1);
        $pdf->SetFont($fontName, '', 6);
        if ($appSettings?->address)
            $pdf->MultiCell(0, $lineHeight - 0.5, $appSettings->address, 0, $alignCenter, false, 1);
        if ($appSettings?->phone)
            $pdf->MultiCell(0, $lineHeight - 0.5, ($isRTL ? "هاتف: " : "Tel: ") . $appSettings->phone, 0, $alignCenter, false, 1);
        if ($appSettings?->vatin)
            $pdf->MultiCell(0, $lineHeight - 0.5, ($isRTL ? "ر.ض: " : "VAT: ") . $appSettings->vatin, 0, $alignCenter, false, 1);
        $pdf->Ln(1);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(1);

        // Receipt Info
        $pdf->SetFont($fontName, '', 6.5);
        $receiptNumber = "LAB-" . $visit->id . "-" . $labRequestsToPrint->first()?->id;
        // $pdf->Cell(0, $lineHeight, ($isRTL ? "إيصال رقم: " : "Receipt #: ") . $receiptNumber, 0, 1, $alignStart);
        $pdf->Cell(0, $lineHeight, ($isRTL ? "زيارة رقم: " : "Visit #: ") . $visit->id, 0, 1, $alignStart);
        $pdf->Cell(0, $lineHeight, ($isRTL ? "التاريخ: " : "Date: ") . Carbon::now()->format('Y/m/d H:i A'), 0, 1, $alignStart);
        $pdf->Cell(0, $lineHeight, ($isRTL ? "المريض: " : "Patient: ") . $visit->patient->name, 0, 1, $alignStart);
        if ($visit->patient->phone)
            $pdf->Cell(0, $lineHeight, ($isRTL ? "الهاتف: " : "Phone: ") . $visit->patient->phone, 0, 1, $alignStart);
        if ($isCompanyPatient && $visit->patient->company)
            $pdf->Cell(0, $lineHeight, ($isRTL ? "الشركة: " : "Company: ") . $visit->patient->company->name, 0, 1, $alignStart);
        if ($visit->doctor)
            $pdf->Cell(0, $lineHeight, ($isRTL ? "الطبيب: " : "Doctor: ") . $visit->doctor->name, 0, 1, $alignStart);
        $pdf->Cell(0, $lineHeight, ($isRTL ? "الكاشير: " : "Cashier: ") . $cashierName, 0, 1, $alignStart);

        if ($appSettings?->barcode && $labRequestsToPrint->first()?->id) { /* ... Barcode ... */
        }
        $pdf->Ln(1);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(0.5);

        // Items Table
        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $nameWidth = $pageUsableWidth * 0.50;
        $qtyWidth = $pageUsableWidth * 0.10;
        $priceWidth = $pageUsableWidth * 0.20;
        $totalWidth = $pageUsableWidth * 0.20;
        $pdf->SetFont($fontName, 'B', 7.5);
        $pdf->Cell($nameWidth, $lineHeight, ($isRTL ? 'البيان' : 'Item'), 'B', 0, $alignStart);
        $pdf->Cell($qtyWidth, $lineHeight, ($isRTL ? 'كمية' : 'Qty'), 'B', 0, $alignCenter);
        $pdf->Cell($priceWidth, $lineHeight, ($isRTL ? 'سعر' : 'Price'), 'B', 0, $alignCenter);
        $pdf->Cell($totalWidth, $lineHeight, ($isRTL ? 'إجمالي' : 'Total'), 'B', 1, $alignCenter);
        $pdf->SetFont($fontName, '', 7.5);

        $subTotalLab = 0;
        $totalDiscountOnLab = 0;
        $totalEnduranceOnLab = 0;
        foreach ($labRequestsToPrint as $lr) {
            $testName = $lr->mainTest?->main_test_name ?? 'Test N/A';
            $quantity = (int) ($lr->count ?? 1);
            $unitPrice = (float) ($lr->price ?? 0);
            $itemGrossTotal = $unitPrice * $quantity;
            $subTotalLab += $itemGrossTotal;
            $itemDiscountPercent = (float) ($lr->discount_per ?? 0);
            $itemDiscountAmount = ($itemGrossTotal * $itemDiscountPercent) / 100;
            $totalDiscountOnLab += $itemDiscountAmount;
            if ($isCompanyPatient) {
                $itemEndurance = (float) ($lr->endurance ?? 0) * $quantity;
                $totalEnduranceOnLab += $itemEndurance;
            }
            $currentYbeforeMultiCell = $pdf->GetY();
            $pdf->MultiCell($nameWidth, $lineHeight - 0.5, $testName, 0, $alignStart, false, 0, '', '', true, 0, false, true, 0, 'T');
            $yAfterMultiCell = $pdf->GetY();
            $pdf->SetXY($pdf->getMargins()['left'] + $nameWidth, $currentYbeforeMultiCell);
            $pdf->Cell($qtyWidth, $lineHeight - 0.5, $quantity, 0, 0, $alignCenter);
            $pdf->Cell($priceWidth, $lineHeight - 0.5, number_format($unitPrice, 2), 0, 0, $alignCenter);
            $pdf->Cell($totalWidth, $lineHeight - 0.5, number_format($itemGrossTotal, 2), 0, 1, $alignCenter);
            $pdf->SetY(max($yAfterMultiCell, $currentYbeforeMultiCell + $lineHeight - 0.5));
        }
        $pdf->Ln(0.5);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(0.5);

        // Totals Section
        $pdf->SetFont($fontName, '', 7);
        $netAfterDiscount = $subTotalLab - $totalDiscountOnLab;
        $netPayableByPatient = $netAfterDiscount - ($isCompanyPatient ? $totalEnduranceOnLab : 0);
        $totalActuallyPaidForTheseLabs = $labRequestsToPrint->sum(fn($lr) => (float) $lr->amount_paid);
        $balanceDueForTheseLabs = $netPayableByPatient - $totalActuallyPaidForTheseLabs;

        $this->drawThermalTotalRow($pdf, ($isRTL ? 'الإجمالي الفرعي:' : 'Subtotal:'), $subTotalLab, $pageUsableWidth);
        if ($totalDiscountOnLab > 0)
            $this->drawThermalTotalRow($pdf, ($isRTL ? 'الخصم:' : 'Discount:'), -$totalDiscountOnLab, $pageUsableWidth);
        if ($isCompanyPatient && $totalEnduranceOnLab > 0)
            $this->drawThermalTotalRow($pdf, ($isRTL ? 'تحمل الشركة:' : 'Company Share:'), -$totalEnduranceOnLab, $pageUsableWidth);
        $pdf->SetFont($fontName, 'B', 7.5);
        $this->drawThermalTotalRow($pdf, ($isRTL ? 'الصافي المطلوب:' : 'Net Payable:'), $netPayableByPatient, $pageUsableWidth, true);
        $pdf->SetFont($fontName, '', 7);
        $this->drawThermalTotalRow($pdf, ($isRTL ? 'المدفوع:' : 'Paid:'), $totalActuallyPaidForTheseLabs, $pageUsableWidth);
        $pdf->SetFont($fontName, 'B', 7.5);
        $this->drawThermalTotalRow($pdf, ($isRTL ? 'المتبقي:' : 'Balance:'), $balanceDueForTheseLabs, $pageUsableWidth, true);

        // Footer Message, Watermark
        if ($appSettings?->show_water_mark) { /* ... Watermark logic ... */
        }
        $pdf->Ln(3);
        $pdf->SetFont($fontName, 'I', 6);
        $footerMessage = $appSettings?->receipt_footer_message ?: ($isRTL ? 'شكراً لزيارتكم!' : 'Thank you for your visit!');
        $pdf->MultiCell(0, $lineHeight - 1, $footerMessage, 0, $alignCenter, false, 1);
        $pdf->Ln(3);

        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $visit->patient->name);
        $pdfFileName = 'LabReceipt_Visit_' . $visit->id . '_' . $patientNameSanitized . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)->header('Content-Type', 'application/pdf')->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }

    /**
     * Generate PDF for lab sample labels for a given visit.
     */
    public function generateLabSampleLabelPdf(Request $request, DoctorVisit $visit)
    {
        // Permission Check
        // if (!Auth::user()->can('print lab_labels', $visit)) { /* ... */ }

        $visit->load([
            'patient:id,name,age_year,gender',
            'patientLabRequests.mainTest:id,main_test_name',
        ]);

        if ($visit->patientLabRequests->isEmpty()) {
            return response()->json(['message' => 'No valid lab requests requiring samples found for this visit.'], 404);
        }

        $appSettings = Setting::instance();
        $labelWidth = (float) ($appSettings->label_printer_width_mm ?? 50);
        $labelHeight = (float) ($appSettings->label_printer_height_mm ?? 25);
        $labelMargin = (float) ($appSettings->label_printer_margin_mm ?? 1.5);
        $fontSize = (float) ($appSettings->label_font_size_pt ?? 6);
        $barcodeHeight = (float) ($appSettings->label_barcode_height_mm ?? 8);
        $barcodeText = $appSettings->label_show_barcode_text ?? true;

        $pdf = new MyCustomTCPDF('Lab Sample Labels', $visit, 'P', 'mm', [$labelWidth, $labelHeight]); // Portrait, custom size
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins($labelMargin, $labelMargin, $labelMargin);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetFont('helvetica', '', $fontSize);

        foreach ($visit->patientLabRequests as $lr) {
            if (!$lr->sample_id) {
                $lr->sample_id = LabRequest::generateSampleId($visit); // Use model helper
                $lr->saveQuietly();
            }
            if (!$lr->sample_id)
                continue;

            $pdf->AddPage();
            $currentY = $pdf->GetY();
            $isRTL = $pdf->getRTL();
            $alignStart = $isRTL ? 'R' : 'L';
            $cellLineHeight = $fontSize * 0.4;

            $patientNameShort = mb_substr($visit->patient->name, 0, 15) . (mb_strlen($visit->patient->name) > 15 ? '...' : '');
            $pdf->SetFont('helvetica', 'B', $fontSize);
            $pdf->MultiCell($labelWidth - (2 * $labelMargin) - 15, $cellLineHeight, $patientNameShort, 0, $alignStart, false, 0, '', $currentY, true, 0, true);
            $pdf->MultiCell(15, $cellLineHeight, "SID:" . $lr->sample_id, 0, ($isRTL ? 'L' : 'R'), false, 1, $pdf->GetX(), $currentY, true, 0, true);
            $currentY = $pdf->GetY();

            $pdf->SetFont('helvetica', '', $fontSize - 1);
            $ageGender = ($visit->patient->age_year ?? 'NA') . 'Y/' . strtoupper(substr($visit->patient->gender ?? 'U', 0, 1));
            $pdf->MultiCell($labelWidth / 2 - $labelMargin, $cellLineHeight, "PID:" . $visit->patient->id, 0, $alignStart, false, 0, '', $currentY, true, 0, false);
            $pdf->MultiCell(0, $cellLineHeight, $ageGender, 0, ($isRTL ? 'L' : 'R'), false, 1, $labelWidth / 2 + $labelMargin / 2, $currentY, true, 0, false);
            $currentY = $pdf->GetY();

            $testNameShort = mb_substr($lr->mainTest?->main_test_name ?? 'Test', 0, 20) . (mb_strlen($lr->mainTest?->main_test_name ?? '') > 20 ? '...' : '');
            $pdf->MultiCell(0, $cellLineHeight, $testNameShort, 0, $alignStart, false, 1, '', $currentY, true, 0, false);
            $currentY = $pdf->GetY();

            if ($appSettings->barcode && $lr->sample_id) {
                $remainingHeight = $labelHeight - $currentY - $labelMargin;
                if ($remainingHeight > 5) {
                    $bcHeight = min($barcodeHeight, $remainingHeight - 1);
                    $style = ['position' => 'S', 'align' => 'C', 'stretch' => false, 'fitwidth' => true, 'border' => false, 'hpadding' => 0, 'vpadding' => 0.5, 'fgcolor' => [0, 0, 0], 'text' => $barcodeText, 'font' => 'helvetica', 'fontsize' => max(4, $fontSize - 2), 'stretchtext' => 0];
                    $pdf->write1DBarcode((string) $lr->sample_id, 'C128B', '', $currentY, $labelWidth - (2 * $labelMargin), $bcHeight, 0.25, $style, 'N');
                }
            }
        }
        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $visit->patient->name);
        $pdfFileName = 'LabLabels_Visit_' . $visit->id . '_' . $patientNameSanitized . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)->header('Content-Type', 'application/pdf')->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }









    public function result(Request $request, $id = null, $base64 = false)
    {
        if ($id !== null) {
            /** @var DoctorVisit $doctorvisit */
            $doctorvisit = Doctorvisit::find($id);
        } else {
            /** @var DoctorVisit $doctorvisit */
            $doctorvisit = Doctorvisit::find($request->get('pid'));
        }

        $labResultReport = new LabResultReport();
        $pdfContent = $labResultReport->generate($doctorvisit, $base64);

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"name.pdf\"")
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');


    }
    /**
     * Generate PDF barcode labels for lab containers (Ahmed Altamayoz config for printer ZY809)
     * 
     * @param Request $request
     * @param Doctorvisit $doctorvisit
     * @return mixed
     */
    public function printBarcodeWithViewer(Request $request, Doctorvisit $doctorvisit)
    {
        try {
            // Validate doctor visit has patient and lab requests
            if (!$doctorvisit->patient) {
                return response()->json(['status' => false, 'message' => 'No patient found for this visit'], 400);
            }

            /** @var Patient $patient */
            $patient = $doctorvisit->patient;
            
            if (!$patient->labrequests || $patient->labrequests->isEmpty()) {
                return response()->json(['status' => false, 'message' => 'No lab requests found for this patient'], 400);
            }

            // PDF configuration
            $customLayout = [50, 25];
            $pageWidth = 50;
            
            // Initialize PDF
            $pdf = new Pdf('landscape', PDF_UNIT, $customLayout, true, 'UTF-8', false);
            
            // Configure PDF properties
            $pdf->setCreator(PDF_CREATOR);
            $pdf->setAuthor('alryyan mahjoob');
            $pdf->setTitle('ايصال المختبر');
            $pdf->setSubject('ايصال المختبر');
            $pdf->setAutoPageBreak(true, 0);
            $pdf->setMargins(0, 0, 0);

            // Try to add custom font, fallback to helvetica if fails
            $arialFont = 'helvetica'; // Default fallback
            try {
                if (class_exists('TCPDF_FONTS') && file_exists(public_path('arial.ttf'))) {
                    $arialFont = \TCPDF_FONTS::addTTFfont(public_path('arial.ttf'));
                }
            } catch (\Exception $e) {
                Log::warning('Failed to load custom font, using helvetica: ' . $e->getMessage());
            }

            // Get unique containers from lab requests
            $containers = $patient->labrequests
                ->map(function (LabRequest $req) {
                    return $req->mainTest->container;
                })
                ->unique('id');

            // Generate labels for each container
            foreach ($containers as $container) {
                $this->generatePdfLabelForContainer($pdf, $patient, $doctorvisit, $container, $arialFont, $pageWidth);
            }

            // Output PDF
        if ($request->has('base64')) {
            $resultAsBase64 = $pdf->output('name.pdf', 'E');
            return $resultAsBase64;
        } else {
            // Return PDF as response with proper headers
            $pdfContent = $pdf->output('barcode_labels.pdf', 'S');
            return response($pdfContent, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="barcode_labels.pdf"',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0'
            ]);
        }
            
        } catch (\Exception $e) {
            Log::error('PDF barcode generation failed: ' . $e->getMessage(), [
                'doctor_visit_id' => $doctorvisit->id,
                'patient_id' => $doctorvisit->patient?->id,
                'error' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'status' => false, 
                'message' => 'Failed to generate PDF barcode labels: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate PDF label for a specific container
     * 
     * @param Pdf $pdf
     * @param Patient $patient
     * @param Doctorvisit $doctorvisit
     * @param object $container
     * @param string $arialFont
     * @param int $pageWidth
     * @return void
     */
    private function generatePdfLabelForContainer(Pdf $pdf, Patient $patient, Doctorvisit $doctorvisit, $container, string $arialFont, int $pageWidth): void
    {
        $pdf->AddPage();
        
        // Get tests for this specific container
        $testsForContainer = $patient->labrequests
            ->filter(function (LabRequest $labrequest) use ($container) {
                return $labrequest->mainTest->container->id == $container->id;
            })
            ->map(function (LabRequest $labRequest) {
                return $labRequest->mainTest;
            });

        // Build test names string with proper formatting
        $testNames = $testsForContainer
            ->pluck('main_test_name')
            ->map(function ($name, $index) {
                return $index === 0 ? $name : '- ' . $name;
            })
            ->implode('');

        // Barcode style configuration
        $barcodeStyle = [
            'position' => 'C',
            'align' => 'C',
            'stretch' => false,
            'fitwidth' => false,
            'cellfitalign' => '',
            'border' => false,
            'hpadding' => 0,
            'vpadding' => 0,
            'fgcolor' => [0, 0, 0],
            'bgcolor' => false,
            'text' => false,
            'font' => 'helvetica',
            'fontsize' => 10,
            'stretchtext' => 4
        ];

        // Generate label content
        $pdf->SetFillColor(240, 240, 240);
        $pdf->SetFont('helvetica', '', 7, '', true);
        
        // Header row with PID and date
        $pdf->Cell(5, 3, '', 0, 0, 'C');
        $pdf->Cell(15, 3, 'PID ' . $doctorvisit->id, 0, 0, '');
        $pdf->Cell(0, 3, $patient->created_at->format('Y-m-d H:i A'), 0, 1, 'R');

        // Visit number and patient name row
        $pdf->Cell(5, 3, '', 0, 0, 'C');
        $pdf->Cell(10, 3, 'No ' . $patient->visit_number, 1, 0, 'C');
        $pdf->SetFont($arialFont, '', 9, '', true);
        $pdf->Cell(5, 3, '', 0, 0, 'C');
        $pdf->Cell(0, 3, $patient->name, 0, 1, 'C');

        // Generate barcode
        $pdf->write1DBarcode((string)$doctorvisit->id, 'C128', 30, '', 25, 10, 0.4, $barcodeStyle, 'N');

        // Test names
        $pdf->SetFont('helvetica', 'u', 7, '', true);
        $pdf->Cell(0, 3, $testNames, 0, 1, 'C');
    }

    public function generateLabVisitReportPdf(Request $request, DoctorVisit $doctorvisit)
    {

        // return $doctorvisit;
        // Permission Check (Example)
        // if (!Auth::user()->can('print lab_report', $visit)) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Eager load all necessary data
        // Ensure DoctorVisit model has this scope defined:
        // public function scopeLoadDefaultLabReportRelations($query) { /* see previous response for implementation */ }
        $doctorvisit->loadDefaultLabReportRelations();

        $labRequestsToReport = $doctorvisit->patientLabRequests->filter(function ($lr) {
            if (!$lr->mainTest)
                return false; // Skip if mainTest relation isn't loaded properly
            return $lr->results->where(fn($r) => $r->result !== null && $r->result !== '')->isNotEmpty() ||
                !$lr->mainTest->divided ||
                $lr->requestedOrganisms->isNotEmpty();
        });

        if ($labRequestsToReport->isEmpty()) {
            return response()->json(['message' => 'No results or relevant tests to report for this visit.'], 404);
        }

        $appSettings = Setting::instance();

        // Pass the $visit context to MyCustomTCPDF constructor
        // MyCustomTCPDF will use this in its Header() and Footer()
        $pdf = new MyCustomTCPDF(
            'Lab Result Report', // Title for PDF metadata
            $doctorvisit,              // Visit context for Header/Footer of MyCustomTCPDF
            'P',
            'mm',
            'A4'      // Default orientation, unit, format
        );

        // Font setup is now primarily handled by MyCustomTCPDF's constructor and its defaultFont
        // $fontMain = 'helvetica'; // e.g., 'aealarabiya' or 'arial'
        // $fontEnglish = 'helvetica'; // A fallback or specific English font if needed

        $pdf->AddPage(); // This triggers MyCustomTCPDF::Header()

        // The cursor (Y position) is now set by MyCustomTCPDF::Header() to be below the header content.

        $firstTestOnPage = true;

        foreach ($labRequestsToReport as $labRequest) {
            $mainTest = $labRequest->mainTest;
            if (!$mainTest)
                continue;

            $estimatedHeight = $this->estimateMainTestBlockHeightForReport($pdf, $labRequest);

            if (
                !$firstTestOnPage &&
                ($mainTest->pageBreak || ($pdf->GetY() + $estimatedHeight > ($pdf->getPageHeight() - $pdf->getBreakMargin())))
            ) {
                $pdf->AddPage(); // This also calls MyCustomTCPDF::Header()
            } elseif (!$firstTestOnPage) {
                $pdf->Ln(3); // Space between main test blocks on the same page
            }

            // Draw the content specific to this MainTest (results, organisms, comments)
            $this->drawMainTestContentBlock($pdf, $labRequest, $appSettings);
            $firstTestOnPage = false;
        }

        // MyCustomTCPDF::Footer() will be called automatically by TCPDF on Output or AddPage.

        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $doctorvisit->patient->name);
        $pdfFileName = 'LabReport_Visit_' . $doctorvisit->id . '_' . $patientNameSanitized . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S'); // 'S' returns as string

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }

    protected function estimateMainTestBlockHeightForReport(MyCustomTCPDF $pdf, LabRequest $labRequest): float
    {
        $fontMain = 'helvetica';
        $pdf->SetFont($fontMain, '', 8); // Use a typical content font size for estimation
        $lineHeight = 5;
        $height = 10; // For main test name and spacing

        if ($labRequest->results->isNotEmpty() && $labRequest->mainTest && $labRequest->mainTest->divided) {
            $height += ($lineHeight + 1); // For child test table headers
            foreach ($labRequest->results as $result) {
                $childTest = $result->childTest;
                if (!$childTest)
                    continue;
                $texts = [
                    $childTest->child_test_name,
                    $result->result ?? '-',
                    $result->unit?->name ?? $childTest->unit?->name ?? '-',
                    $result->normal_range ?? '-',
                    $result->flags ?? '-'
                ];
                $maxLines = 1;
                // Estimate lines for each cell (rough estimate)
                for ($i = 0; $i < count($texts); $i++)
                    $maxLines = max($maxLines, ceil(strlen($texts[$i]) / 20)); // Assume ~20 chars per line
                $height += $maxLines * ($lineHeight - 1);
                if (!empty($result->result_comment))
                    $height += $lineHeight - 1;
            }
        }
        if ($labRequest->requestedOrganisms->isNotEmpty()) {
            $height += ($lineHeight + 2); // Header for organisms
            foreach ($labRequest->requestedOrganisms as $org) {
                $height += $lineHeight; // Organism name
                $height += $lineHeight; // Sensitive/Resistant headers
                $maxRowsAB = max(substr_count($org->sensitive ?? '', "\n") + 1, substr_count($org->resistant ?? '', "\n") + 1);
                $height += $maxRowsAB * ($lineHeight - 1.5);
            }
        }
        if ($labRequest->comment)
            $height += ($lineHeight * 2); // Comment + heading
        return $height;
    }

    protected function drawMainTestContentBlock(MyCustomTCPDF $pdf, LabRequest $labRequest, ?Setting $settings)
    {
        $isRTL = false;
        $fontMain = 'helvetica'; // Primary font (e.g., aealarabiya)
        $fontDetail = 'helvetica'; // Fallback for potentially mixed content, or use $fontMain
        $lineHeight = 5;
        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $mainTest = $labRequest->mainTest;

        // Main Test Name Header
        $pdf->SetFont($fontMain, 'BU', 11); // Bold Underline, size matching patient info headers
        $pdf->SetFillColor(230, 235, 245); // Light background for emphasis
        $pdf->Cell(0, $lineHeight + 2, $mainTest->main_test_name, 0, 1, $isRTL ? 'R' : 'L', true); // Fill added
        $pdf->Ln(1.5);

        // Child Results Table
        if ($mainTest->divided && $labRequest->results->isNotEmpty()) {
            $this->drawChildResultsTableForReport($pdf, $labRequest->results, $fontMain, $pageUsableWidth, $lineHeight, $isRTL, true);
        } elseif (!$mainTest->divided && $labRequest->results->isNotEmpty()) { // Non-divided but uses RequestedResult
            $this->drawChildResultsTableForReport($pdf, $labRequest->results, $fontMain, $pageUsableWidth, $lineHeight, $isRTL, false); // No headers
        } elseif ($mainTest->divided && $labRequest->results->isEmpty()) { // Divided but no results entered
            $pdf->SetFont($fontMain, 'I', 8);
            $pdf->Cell(0, $lineHeight, ($isRTL ? 'لم يتم إدخال نتائج فرعية بعد.' : 'No sub-results entered yet.'), 0, 1, 'C');
        } elseif (!$mainTest->divided && $labRequest->comment) { // Non-divided, result might be in main comment
            $pdf->SetFont($fontMain, '', 9);
            $pdf->SetX($pdf->GetX() + ($isRTL ? 0 : 5));
            $pdf->MultiCell(0, $lineHeight, ($isRTL ? "النتيجة: " : "Result: ") . $labRequest->comment, 0, $isRTL ? 'R' : 'L', false, 1);
        }
        $pdf->Ln(0.5);

        // Organisms Section
        if ($labRequest->requestedOrganisms->isNotEmpty()) {
            $this->drawOrganismsSection($pdf, $labRequest->requestedOrganisms, $fontMain, $pageUsableWidth, $lineHeight, $isRTL);
        }

        // Overall Main Test Comment (if it's distinct from a non-divided result)
        if ($labRequest->comment && ($mainTest->divided || (!$mainTest->divided && $labRequest->results->isNotEmpty()))) {
            $pdf->Ln(1.5);
            $pdf->SetFont($fontMain, 'B', 8.5);
            $pdf->Cell(0, $lineHeight, ($isRTL ? "ملاحظات إضافية:" : "Additional Comment:"), 0, 1, $isRTL ? 'R' : 'L');
            $pdf->SetFont($fontMain, '', 8.5);
            $pdf->SetX($pdf->GetX() + ($isRTL ? 0 : 3));
            $pdf->MultiCell(0, $lineHeight - 1, $labRequest->comment, 0, $isRTL ? 'R' : 'L', false, 1);
        }

        // Watermark (using method from MyCustomTCPDF)
        if ($settings?->show_water_mark && $labRequest->approve) {
            $pdf->drawTextWatermark(($isRTL ? "معتمد" : "AUTHORIZED"), $fontMain);
        }
        $pdf->Ln(1);
    }

    protected function drawChildResultsTableForReport(MyCustomTCPDF $pdf, $results, $fontMain, $pageUsableWidth, $baseLineHeight, $isRTL, $drawHeaders = true)
    {
        $colWidths = [$pageUsableWidth * 0.33, $pageUsableWidth * 0.17, $pageUsableWidth * 0.12, $pageUsableWidth * 0.28, $pageUsableWidth * 0.10];
        $colAligns = [$isRTL ? 'R' : 'L', 'C', 'C', $isRTL ? 'R' : 'L', 'C'];

        if ($drawHeaders) {
            $pdf->SetFont($fontMain, 'B', 7.5);
            $pdf->SetFillColor(240, 240, 240);
            $pdf->SetLineWidth(0.15);
            $pdf->Cell($colWidths[0], $baseLineHeight, ($isRTL ? 'الفحص' : "Test"), 'TB', 0, 'C', true);
            $pdf->Cell($colWidths[1], $baseLineHeight, ($isRTL ? 'النتيجة' : "Result"), 'TB', 0, 'C', true);
            $pdf->Cell($colWidths[2], $baseLineHeight, ($isRTL ? 'الوحدة' : "Unit"), 'TB', 0, 'C', true);
            $pdf->Cell($colWidths[3], $baseLineHeight, ($isRTL ? 'المعدل الطبيعي' : "Normal Range"), 'TB', 0, 'C', true);
            $pdf->Cell($colWidths[4], $baseLineHeight, ($isRTL ? 'علامات' : "Flags"), 'TB', 1, 'C', true);
            $pdf->Ln(0.2);
            $pdf->SetLineWidth(0.1);
        }
        $pdf->SetFont($fontMain, '', 8); // Results font
        $fill = false;

        foreach ($results as $result) {
            $childTest = $result->childTest;
            if (!$childTest)
                continue;

            $isAbnormal = false;
            $numericResult = filter_var($result->result, FILTER_VALIDATE_FLOAT);
            if ($numericResult !== false && $childTest->low !== null && $childTest->upper !== null) {
                if ($numericResult < (float) $childTest->low || $numericResult > (float) $childTest->upper)
                    $isAbnormal = true;
            } elseif (!empty($result->flags) && in_array(strtoupper($result->flags), ['H', 'L', 'A', 'ABN', 'ABNORMAL', '+', '++'])) {
                $isAbnormal = true;
            }

            $texts = [
                $childTest->child_test_name,
                $result->result ?? '-',
                $result->unit?->name ?? $childTest->unit?->name ?? '-',
                $result->normal_range ?? '-',
                $result->flags ?? '-'
            ];

            // --- CORRECTED DYNAMIC ROW HEIGHT CALCULATION ---
            $maxLines = 1; // Assume at least one line
            for ($i = 0; $i < count($texts); $i++) {
                // GetNumLines can be tricky with MultiCell internal calculations.
                // A more reliable way is to estimate based on string length and font,
                // or use a fixed height and allow MultiCell to auto-adjust (though this can lead to uneven rows if not careful).
                // For precise dynamic height with MultiCell, you often draw it once off-page to get height, then redraw.
                // Simpler approach: Estimate lines or use GetNumLines carefully.
                $numCurrentLines = $pdf->getNumLines((string) $texts[$i], $colWidths[$i], false, true, '', 1);
                if ($numCurrentLines > $maxLines) {
                    $maxLines = $numCurrentLines;
                }
            }
            // Calculate row height based on max lines and font size/line height ratio
            // $currentFontSize = $pdf->getFontSizePt(); // Get current font size in points
            // $lineHeightRatio = $pdf->getCellHeightRatio();
            // $singleLineHeight = ($currentFontSize / $pdf->getScaleFactor()) * $lineHeightRatio; // Height of one line in user units (mm)
            // $dynamicRowHeight = $maxLines * $singleLineHeight + ($pdf->getCellPaddings()['T'] + $pdf->getCellPaddings()['B']);
            // Fallback to a simpler estimation or a slightly generous fixed height if GetNumLines is problematic with MultiCell auto-height
            $dynamicRowHeight = $baseLineHeight * $maxLines;
            if ($maxLines > 1)
                $dynamicRowHeight += ($maxLines - 1) * 0.5; // Small padding for multi-lines
            if ($dynamicRowHeight < $baseLineHeight)
                $dynamicRowHeight = $baseLineHeight;
            // --- END OF CORRECTION ---


            if ($pdf->GetY() + $dynamicRowHeight > ($pdf->getPageHeight() - $pdf->getBreakMargin())) {
                $pdf->AddPage();
                if ($drawHeaders) {
                    $pdf->SetFont($fontMain, 'B', 7.5);
                    $pdf->SetFillColor(240, 240, 240);
                    $pdf->SetLineWidth(0.15);
                    $pdf->Cell($colWidths[0], $baseLineHeight, ($isRTL ? 'الفحص' : "Test"), 'TB', 0, 'C', true);
                    $pdf->Cell($colWidths[1], $baseLineHeight, ($isRTL ? 'النتيجة' : "Result"), 'TB', 0, 'C', true);
                    $pdf->Cell($colWidths[2], $baseLineHeight, ($isRTL ? 'الوحدة' : "Unit"), 'TB', 0, 'C', true);
                    $pdf->Cell($colWidths[3], $baseLineHeight, ($isRTL ? 'المعدل الطبيعي' : "Normal Range"), 'TB', 0, 'C', true);
                    $pdf->Cell($colWidths[4], $baseLineHeight, ($isRTL ? 'علامات' : "Flags"), 'TB', 1, 'C', true);
                    $pdf->Ln(0.2);
                    $pdf->SetLineWidth(0.1);
                }
                $pdf->SetFont($fontMain, '', 8);
            }

            $curY = $pdf->GetY();
            $curX = $pdf->GetX();
            $pdf->SetFillColor($fill ? 248 : 255, $fill ? 248 : 255, $fill ? 248 : 255);

            // Use 'M' for vertical alignment (middle) in MultiCell
            $pdf->MultiCell($colWidths[0], $dynamicRowHeight, $texts[0], 1, $colAligns[0], $fill, 0, $curX, $curY, true, 0, false, true, $dynamicRowHeight, 'M');
            $curX += $colWidths[0];
            $pdf->SetFont($fontMain, $isAbnormal ? 'B' : '', 8);
            $pdf->MultiCell($colWidths[1], $dynamicRowHeight, $texts[1], 1, $colAligns[1], $fill, 0, $curX, $curY, true, 0, false, true, $dynamicRowHeight, 'M');
            $curX += $colWidths[1];
            $pdf->SetFont($fontMain, '', 8);
            $pdf->MultiCell($colWidths[2], $dynamicRowHeight, $texts[2], 1, $colAligns[2], $fill, 0, $curX, $curY, true, 0, false, true, $dynamicRowHeight, 'M');
            $curX += $colWidths[2];
            $pdf->MultiCell($colWidths[3], $dynamicRowHeight, $texts[3], 1, $colAligns[3], $fill, 0, $curX, $curY, true, 0, false, true, $dynamicRowHeight, 'M');
            $curX += $colWidths[3];
            $pdf->MultiCell($colWidths[4], $dynamicRowHeight, $texts[4], 1, $colAligns[4], $fill, 1, $curX, $curY, true, 0, false, true, $dynamicRowHeight, 'M');

            // No explicit $pdf->Line needed after each row if MultiCell border is '1' or 'LTRB'
            $fill = !$fill;

            if (!empty($result->result_comment)) {
                $pdf->SetFont($fontMain, 'I', 7.5);
                $pdf->SetFillColor($fill ? 248 : 255, $fill ? 248 : 255, $fill ? 248 : 255);
                $pdf->MultiCell(0, $baseLineHeight - 1.5, ($isRTL ? "تعليق: " : "Comment: ") . $result->result_comment, 'LRB', $isRTL ? 'R' : 'L', $fill, 1); // Changed to LRB to connect to previous row
                $pdf->SetFont($fontMain, '', 8);
                $fill = !$fill;
            }
        }
    }

    protected function drawOrganismsSection(MyCustomTCPDF $pdf, $organisms, $fontMain, $pageWidth, $baseLineHeight, $isRTL)
    {
        if ($organisms->isEmpty())
            return;
        $pdf->Ln(2);
        $pdf->SetFont($fontMain, 'B', 10);
        $pdf->Cell(0, $baseLineHeight, ($isRTL ? "مزرعة وحساسية:" : "Culture & Sensitivity:"), 0, 1, $isRTL ? 'R' : 'L');
        $pdf->Ln(0.5);

        foreach ($organisms as $org) {
            if ($pdf->GetY() + 40 > ($pdf->getPageHeight() - $pdf->getBreakMargin()))
                $pdf->AddPage();

            $pdf->SetFont($fontMain, 'BU', 9);
            $pdf->Cell($pageWidth, $baseLineHeight, $org->organism, 0, 1, 'C'); // Organism Name centered

            $pdf->SetFont($fontMain, 'B', 8);
            $halfWidth = $pageWidth / 2 - 1; // -1 for small gap/border
            $pdf->Cell($halfWidth, $baseLineHeight - 1, ($isRTL ? 'حساس لـ:' : 'Sensitive To:'), 'B', 0, 'C');
            $pdf->Cell(2, $baseLineHeight - 1, '', 0, 0); // Gap
            $pdf->Cell($halfWidth, $baseLineHeight - 1, ($isRTL ? 'مقاوم لـ:' : 'Resistant To:'), 'B', 1, 'C');

            $pdf->SetFont($fontMain, '', 7.5);
            $sensArr = !empty($org->sensitive) ? array_filter(array_map('trim', preg_split('/[\n,]+/', $org->sensitive))) : [];
            $resArr = !empty($org->resistant) ? array_filter(array_map('trim', preg_split('/[\n,]+/', $org->resistant))) : [];
            $maxRows = max(count($sensArr), count($resArr));
            if ($maxRows == 0)
                $maxRows = 1;
            $cellH = $baseLineHeight - 2; // Reduced height for antibiotic list items

            for ($i = 0; $i < $maxRows; $i++) {
                $s_text = $sensArr[$i] ?? '';
                $r_text = $resArr[$i] ?? '';
                $curY = $pdf->GetY();
                $s_height = $pdf->getNumLines($s_text, $halfWidth) * $cellH;
                $r_height = $pdf->getNumLines($r_text, $halfWidth) * $cellH;
                $rowDynamicHeight = max($s_height, $r_height, $cellH);

                if ($pdf->GetY() + $rowDynamicHeight > ($pdf->getPageHeight() - $pdf->getBreakMargin())) {
                    $pdf->AddPage();
                    // Could redraw organism name if it spans pages
                    $pdf->SetFont($fontMain, 'B', 8);
                    $pdf->Cell($halfWidth, $baseLineHeight - 1, 'Sensitive To:', 'B', 0, 'C');
                    $pdf->Cell($halfWidth, $baseLineHeight - 1, 'Resistant To:', 'B', 1, 'C');
                    $pdf->SetFont($fontMain, '', 7.5);
                    $curY = $pdf->GetY();
                }

                $pdf->MultiCell($halfWidth, $rowDynamicHeight, $s_text, 0, ($isRTL ? 'R' : 'L'), false, 0, $pdf->getMargins()['left'], $curY, true, 0, false, true, $rowDynamicHeight, 'T');
                $pdf->MultiCell($halfWidth, $rowDynamicHeight, $r_text, 0, ($isRTL ? 'R' : 'L'), false, 1, $pdf->getMargins()['left'] + $halfWidth + 2, $curY, true, 0, false, true, $rowDynamicHeight, 'T');
            }
            $pdf->Ln(2); // Space after an organism block
        }
    }
    /**
     * Get daily lab income data for a specified month and year.
     * Focuses on total_paid, cash_paid, bank_paid for lab requests.
     */
    public function monthlyLabIncome(Request $request)
    {
        // if (!Auth::user()->can('view monthly_lab_income_report')) { /* ... */ }

        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            // 'user_id_deposited' => 'nullable|integer|exists:users,id', // Optional filter by user who handled payment
        ]);

        $year = $validated['year'];
        $month = $validated['month'];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $period = CarbonPeriod::create($startDate, '1 day', $endDate);

        $dailyData = [];
        $grandTotals = [
            'total_lab_income_paid' => 0,
            'total_lab_cash_paid' => 0,
            'total_lab_bank_paid' => 0,
        ];

        // Fetch all relevant lab requests for the month once for efficiency.
        // We need to consider when the income is recognized:
        // Option 1: When the LabRequest is created (service rendered date)
        // Option 2: When the payment is actually recorded (payment date)
        // Let's assume for now it's based on LabRequest's `updated_at` when `is_paid` becomes true,
        // or more simply, `created_at` if payments usually happen on the same day.
        // For simplicity, we'll use `created_at` of LabRequest and filter by `is_paid = true`.
        // A more accurate system would have a dedicated `payment_date` on the LabRequest or a separate payments table.

        $paidLabRequestsForMonth = LabRequest::query()
            ->where('is_paid', true) // Only consider paid requests for "income"
            ->whereBetween('created_at', [$startDate, $endDate]) // Filter by request creation date
            // ->when($request->filled('user_id_deposited'), fn($q) => $q->where('user_deposited', $request->user_id_deposited))
            ->get();

        foreach ($period as $date) {
            $currentDateStr = $date->format('Y-m-d');

            $requestsOnThisDay = $paidLabRequestsForMonth->filter(function ($lr) use ($date) {
                return Carbon::parse($lr->created_at)->isSameDay($date);
            });

            $dailyTotalPaid = $requestsOnThisDay->sum(function ($lr) {
                // Net amount paid for this request (price * count - discount - endurance)
                // For simplicity, let's assume amount_paid already reflects this net amount
                return (float) $lr->amount_paid;
            });
            $dailyCashPaid = $requestsOnThisDay->where('is_bankak', false)->sum(fn($lr) => (float) $lr->amount_paid);
            $dailyBankPaid = $requestsOnThisDay->where('is_bankak', true)->sum(fn($lr) => (float) $lr->amount_paid);


            if ($dailyTotalPaid > 0 || $request->boolean('show_empty_days', false)) {
                $dailyData[] = [
                    'date' => $currentDateStr,
                    'total_lab_income_paid' => $dailyTotalPaid,
                    'total_lab_cash_paid' => $dailyCashPaid,
                    'total_lab_bank_paid' => $dailyBankPaid,
                ];
            }

            $grandTotals['total_lab_income_paid'] += $dailyTotalPaid;
            $grandTotals['total_lab_cash_paid'] += $dailyCashPaid;
            $grandTotals['total_lab_bank_paid'] += $dailyBankPaid;
        }

        return response()->json([
            'daily_data' => $dailyData,
            'summary' => $grandTotals,
            'report_period' => [
                'month_name' => $startDate->translatedFormat('F Y'),
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ]
        ]);
    }

    /**
     * Generate PDF for Monthly Lab Income Report.
     * This was provided in a previous response, make sure it's in this controller.
     * This method is distinct from the JSON data endpoint.
     */
    public function generateMonthlyLabIncomePdf(Request $request)
    {
        // if (!Auth::user()->can('print monthly_lab_income_report')) { /* ... */ }

        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
        ]);

        // Use the same data fetching logic as the JSON endpoint by calling it internally
        $reportContentRequest = new Request($validated + ['show_empty_days' => true]); // Ensure all days for PDF
        $dataResponse = $this->monthlyLabIncome($reportContentRequest);
        $jsonData = json_decode($dataResponse->getContent(), true);

        $dailyData = $jsonData['daily_data'] ?? [];
        $summary = $jsonData['summary'] ?? [
            'total_lab_income_paid' => 0,
            'total_lab_cash_paid' => 0,
            'total_lab_bank_paid' => 0
        ];
        $reportPeriod = $jsonData['report_period'] ?? [
            'month_name' => Carbon::create($validated['year'], $validated['month'])->translatedFormat('F Y'),
            'from' => Carbon::create($validated['year'], $validated['month'], 1)->toDateString(),
            'to' => Carbon::create($validated['year'], $validated['month'], 1)->endOfMonth()->toDateString()
        ];

        // --- PDF Generation ---
        $reportTitle = 'تقرير إيرادات المختبر الشهرية (المدفوع)';
        $filterCriteria = "لشهر: {$reportPeriod['month_name']}";

        $pdf = new MyCustomTCPDF($reportTitle, $filterCriteria, 'P', 'mm', 'A4'); // Portrait
        $pdf->AddPage();
        $pdf->SetLineWidth(0.1);
        $isRTL = $pdf->getRTL();

        // Table Header
        $headers = [($isRTL ? 'التاريخ' : 'Date'), ($isRTL ? 'إجمالي المدفوع' : 'Total Paid'), ($isRTL ? 'المدفوع نقداً' : 'Cash Paid'), ($isRTL ? 'المدفوع بنك/شبكة' : 'Bank Paid')];
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [$pageWidth * 0.30, $pageWidth * 0.25, $pageWidth * 0.20, 0];
        $colWidths[count($colWidths) - 1] = $pageWidth - array_sum(array_slice($colWidths, 0, -1));
        $alignments = ['C', 'C', 'C', 'C'];

        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        // Table Body
        $pdf->SetFont('helvetica', '', 8);
        $fill = false;
        if (empty($dailyData)) {
            $pdf->Cell(array_sum($colWidths), 10, ($isRTL ? 'لا توجد بيانات لهذه الفترة.' : 'No data for this period.'), 1, 1, 'C', $fill);
        } else {
            foreach ($dailyData as $day) {
                $rowData = [
                    Carbon::parse($day['date'])->translatedFormat('D, M j, Y'),
                    number_format((float) $day['total_lab_income_paid'], 2),
                    number_format((float) $day['total_lab_cash_paid'], 2),
                    number_format((float) $day['total_lab_bank_paid'], 2),
                ];
                $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill);
                $fill = !$fill;
            }
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        // Summary Footer for Table
        $pdf->SetFont('helvetica', 'B', 8.5);
        $summaryRow = [
            ($isRTL ? 'الإجمالي الشهري:' : 'Monthly Total:'),
            number_format((float) $summary['total_lab_income_paid'], 2),
            number_format((float) $summary['total_lab_cash_paid'], 2),
            number_format((float) $summary['total_lab_bank_paid'], 2),
        ];
        $pdf->DrawSummaryRow($summaryRow, $colWidths, $alignments, 8, [220, 220, 220]); // Use DrawSummaryRow

        $pdfFileName = 'monthly_lab_income_paid_' . $reportPeriod['from'] . '_' . $reportPeriod['to'] . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
    public function labTestStatistics(Request $request)
    {
        // if (!Auth::user()->can('view lab_test_statistics_report')) { /* ... */ }

        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'search_test_name' => 'nullable|string|max:255',
            // Remove container_id, package_id filters if only name and count are needed
            'sort_by' => 'nullable|string|in:main_test_name,request_count', // Simplified sort options
            'sort_direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = LabRequest::query()
            ->join('main_tests', 'labrequests.main_test_id', '=', 'main_tests.id')
            ->select([
                'labrequests.main_test_id',
                'main_tests.main_test_name',
                DB::raw('COUNT(labrequests.id) as request_count')
            ])
            ->groupBy('labrequests.main_test_id', 'main_tests.main_test_name');

        // Apply date filters on labrequests.created_at
        if ($request->filled('date_from')) {
            $query->whereDate('labrequests.created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->whereDate('labrequests.created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Filter by test name (search) directly on the joined main_tests table
        if ($request->filled('search_test_name')) {
            $query->where('main_tests.main_test_name', 'LIKE', '%' . $request->search_test_name . '%');
        }
        
        // Remove container_id and package_id filters if they are not part of the requirement anymore
        // if ($request->filled('container_id')) {
        //     $query->where('main_tests.container_id', $request->container_id);
        // }
        // if ($request->filled('package_id')) {
        //     $query->where('main_tests.pack_id', $request->package_id);
        // }

        // Sorting
        $sortBy = $request->input('sort_by', 'request_count');
        $sortDirection = $request->input('sort_direction', 'desc');

        if ($sortBy === 'main_test_name') {
            $query->orderBy('main_tests.main_test_name', $sortDirection);
        } else { // Default to request_count
            $query->orderBy('request_count', $sortDirection);
        }
        // Add a secondary sort for consistency if primary sort isn't unique
        if ($sortBy !== 'main_test_name') {
            $query->orderBy('main_tests.main_test_name', 'asc');
        }


        $perPage = $request->input('per_page', 15);
        $statistics = $query->paginate($perPage);
        
        // The items in $statistics will now have main_test_id, main_test_name, and request_count.
        // We can still use LabTestStatisticResource, but it will only populate these fields.
        return \App\Http\Resources\LabTestStatisticResource::collection($statistics); // Ensure resource is imported
    }

    /**
     * Lab General Report - Shows patients with their lab information
     */
    public function labGeneral(Request $request)
    {
        $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'patient_name' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
            'per_page' => 'nullable|integer|min:5|max:100',
            'start_time' => 'nullable|string|max:255',
            'end_time' => 'nullable|string|max:255',
        ]);

        $query = Patient::query()
            ->join('doctorvisits', 'patients.id', '=', 'doctorvisits.patient_id')
            ->join('doctors', 'patients.doctor_id', '=', 'doctors.id')
            ->join('labrequests', 'patients.id', '=', 'labrequests.pid')
            ->leftJoin('companies', 'patients.company_id', '=', 'companies.id')
            ->leftJoin('users', 'patients.user_id', '=', 'users.id')
            ->select([
                'doctorvisits.id as doctorvisit_id',
                'patients.id',
                'patients.name',
                'doctors.name as doctor_name',
                'users.name as user_name',
                // 'patients.user_requested as user_id',
                DB::raw('SUM(labrequests.price) as total_lab_amount'),
                DB::raw('SUM(labrequests.amount_paid) as total_paid_for_lab'),
                DB::raw('SUM(labrequests.price * labrequests.discount_per / 100) as discount'),
                DB::raw('SUM(CASE WHEN labrequests.is_bankak = 1 THEN labrequests.amount_paid ELSE 0 END) as total_amount_bank'),
                'companies.name as company_name',
                'patients.created_at',
                DB::raw('GROUP_CONCAT(DISTINCT main_tests.main_test_name SEPARATOR ", ") as main_tests_names')
            ])
            ->leftJoin('main_tests', 'labrequests.main_test_id', '=', 'main_tests.id')
            ->groupBy('doctorvisits.id', 'patients.id', 'patients.name', 'doctors.name', 'users.name', 'companies.name', 'patients.created_at');

        // Apply shift filter
        if ($request->filled('shift_id')) {
            $query->where('doctorvisits.shift_id', $request->shift_id);
        }

        // Apply date filters
        if ($request->filled('date_from')) {
            $query->whereDate('labrequests.created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->whereDate('labrequests.created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Filter by patient name
        if ($request->filled('patient_name')) {
            $query->where('patients.name', 'LIKE', '%' . $request->patient_name . '%');
        }

        // Filter by user (who requested the lab)
        if ($request->filled('user_id')) {
            $query->where('labrequests.user_requested', $request->user_id);
        }

        // Apply time filters
        if ($request->filled('start_time')) {
            $query->whereTime('patients.created_at', '>=', $request->start_time);
        }
        if ($request->filled('end_time')) {
            $query->whereTime('patients.created_at', '<=', $request->end_time);
        }

        // Order by patient name
        $query->orderBy('doctorvisits.id', 'desc');

        $perPage = $request->input('per_page', 20);
        $results = $query->paginate($perPage);

        // Get user revenue data
        $userRevenueQuery = Patient::query()
            ->join('doctorvisits', 'patients.id', '=', 'doctorvisits.patient_id')
            ->join('labrequests', 'patients.id', '=', 'labrequests.pid')
            ->join('users', 'labrequests.user_requested', '=', 'users.id')
            ->select([
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('SUM(labrequests.amount_paid) as total_paid'),
                DB::raw('SUM(labrequests.price * labrequests.discount_per / 100) as total_discount'),
                DB::raw('SUM(CASE WHEN labrequests.is_bankak = 0 THEN labrequests.amount_paid ELSE 0 END) as total_cash'),
                DB::raw('SUM(CASE WHEN labrequests.is_bankak = 1 THEN labrequests.amount_paid ELSE 0 END) as total_bank')
            ])
            ->groupBy('users.id', 'users.name');

        // Apply same filters as main query
        if ($request->filled('shift_id')) {
            $userRevenueQuery->where('doctorvisits.shift_id', $request->shift_id);
        }
        if ($request->filled('date_from')) {
            $userRevenueQuery->whereDate('labrequests.created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $userRevenueQuery->whereDate('labrequests.created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }
        if ($request->filled('patient_name')) {
            $userRevenueQuery->where('patients.name', 'LIKE', '%' . $request->patient_name . '%');
        }
        if ($request->filled('user_id')) {
            $userRevenueQuery->where('labrequests.user_requested', $request->user_id);
        }
        if ($request->filled('start_time')) {
            $userRevenueQuery->whereTime('patients.created_at', '>=', $request->start_time);
        }
        if ($request->filled('end_time')) {
            $userRevenueQuery->whereTime('patients.created_at', '<=', $request->end_time);
        }

        $userRevenues = $userRevenueQuery->get();

        // Return combined data
        return response()->json([
            'data' => $results->items(),
            'user_revenues' => $userRevenues,
            'meta' => [
                'current_page' => $results->currentPage(),
                'from' => $results->firstItem(),
                'last_page' => $results->lastPage(),
                'links' => $results->linkCollection()->toArray(),
                'path' => $results->path(),
                'per_page' => $results->perPage(),
                'to' => $results->lastItem(),
                'total' => $results->total(),
            ]
        ]);
    }

    /**
     * Generate PDF for Lab General Report
     */
    public function generateLabGeneralReportPdf(Request $request)
    {
        // Start output buffering to prevent any output before PDF
        ob_start();
        
        $request->validate([
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'patient_name' => 'nullable|string|max:255',
            'user_id' => 'nullable|integer|exists:users,id',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = Patient::query()
            ->join('doctorvisits', 'patients.id', '=', 'doctorvisits.patient_id')
            ->join('doctors', 'patients.doctor_id', '=', 'doctors.id')
            ->join('labrequests', 'patients.id', '=', 'labrequests.pid')
            ->leftJoin('companies', 'patients.company_id', '=', 'companies.id')
            ->select([
                'doctorvisits.id as doctorvisit_id',
                'patients.id',
                'patients.name',
                'doctors.name as doctor_name',
                DB::raw('SUM(labrequests.price) as total_lab_amount'),
                DB::raw('SUM(labrequests.amount_paid) as total_paid_for_lab'),
                DB::raw('SUM(labrequests.price * labrequests.discount_per / 100) as discount'),
                DB::raw('SUM(CASE WHEN labrequests.is_bankak = 1 THEN labrequests.amount_paid ELSE 0 END) as total_amount_bank'),
                'companies.name as company_name',
                DB::raw('GROUP_CONCAT(DISTINCT main_tests.main_test_name SEPARATOR ", ") as main_tests_names')
            ])
            ->leftJoin('main_tests', 'labrequests.main_test_id', '=', 'main_tests.id')
            ->groupBy('doctorvisits.id', 'patients.id', 'patients.name', 'doctors.name', 'companies.name');

        // Apply shift filter
        if ($request->filled('shift_id')) {
            $query->where('doctorvisits.shift_id', $request->shift_id);
        }

        // Apply date filters
        if ($request->filled('date_from')) {
            $query->whereDate('labrequests.created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $query->whereDate('labrequests.created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }

        // Filter by patient name
        if ($request->filled('patient_name')) {
            $query->where('patients.name', 'LIKE', '%' . $request->patient_name . '%');
        }

        // Filter by user (who requested the lab)
        if ($request->filled('user_id')) {
            $query->where('labrequests.user_requested', $request->user_id);
        }

        // Order by patient name
        $query->orderBy('doctorvisits.id', 'desc');

        $results = $query->get();

        // Build user revenue data (same filters)
        $userRevenueQuery = Patient::query()
            ->join('doctorvisits', 'patients.id', '=', 'doctorvisits.patient_id')
            ->join('labrequests', 'patients.id', '=', 'labrequests.pid')
            ->join('users', 'labrequests.user_requested', '=', 'users.id')
            ->select([
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('SUM(labrequests.amount_paid) as total_paid'),
                DB::raw('SUM(labrequests.price * labrequests.discount_per / 100) as total_discount'),
                DB::raw('SUM(CASE WHEN labrequests.is_bankak = 0 THEN labrequests.amount_paid ELSE 0 END) as total_cash'),
                DB::raw('SUM(CASE WHEN labrequests.is_bankak = 1 THEN labrequests.amount_paid ELSE 0 END) as total_bank')
            ])
            ->groupBy('users.id', 'users.name');

        if ($request->filled('shift_id')) {
            $userRevenueQuery->where('doctorvisits.shift_id', $request->shift_id);
        }
        if ($request->filled('date_from')) {
            $userRevenueQuery->whereDate('labrequests.created_at', '>=', Carbon::parse($request->date_from)->startOfDay());
        }
        if ($request->filled('date_to')) {
            $userRevenueQuery->whereDate('labrequests.created_at', '<=', Carbon::parse($request->date_to)->endOfDay());
        }
        if ($request->filled('patient_name')) {
            $userRevenueQuery->where('patients.name', 'LIKE', '%' . $request->patient_name . '%');
        }
        if ($request->filled('user_id')) {
            $userRevenueQuery->where('labrequests.user_requested', $request->user_id);
        }

        $userRevenues = $userRevenueQuery->get();

        // Use the service to generate the PDF
        $report = new \App\Services\Pdf\LabGeneralReport($results, $request, $userRevenues);
        $pdfContent = $report->generate();

        ob_end_clean();

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="lab_general_report_' . date('Y-m-d_H-i-s') . '.pdf"',
            'Cache-Control' => 'private, max-age=0, must-revalidate',
            'Pragma' => 'public',
        ]);
    }

    /**
     * Generate Lab Shift PDF report (summary + details) and stream in browser
     */
    public function labShiftReportPdf(Request $request)
    {
        ob_start();

        if ($request->has('shift')) {
            $shift = \App\Models\Shift::find($request->get('shift'));
            if (!$shift) {
                ob_end_clean();
                return response()->json(['error' => 'Shift not found'], 404);
            }
        } else {
            $shift = \App\Models\Shift::orderByDesc('id')->first();
            if (!$shift) {
                ob_end_clean();
                return response()->json(['error' => 'No shifts available'], 404);
            }
        }

        $service = new \App\Services\Pdf\LabShiftReport();
        $pdfContent = $service->generate($shift);

        $fileName = 'LabReport_Shift_' . $shift->id . '_' . now()->format('Ymd_His') . '.pdf';
        $response = response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $fileName . '"'
        ]);

        ob_end_clean();
        return $response;
    }

  /**
     * Export the list of services to a PDF file.
     */
    public function exportServicesListToPdf(Request $request)
    {
        // Add permission check
        // if (!Auth::user()->can('export services_list')) { /* ... */ }

        // Reuse filtering logic
        $request->validate([
            'search' => 'nullable|string|max:255',
        ]);

        $query = Service::with('serviceGroup:id,name')->orderBy('name');

        $filterCriteria = [];
        if ($request->filled('search')) {
            $searchTerm = $request->search;
            $query->where('name', 'LIKE', '%' . $searchTerm . '%');
            $filterCriteria[] = "Search: " . $searchTerm;
        }
        if ($request->filled('service_group_id') && $request->service_group_id != 'all') {
            $groupId = $request->service_group_id;
            $query->where('service_group_id', $groupId);
            $group = \App\Models\ServiceGroup::find($groupId);
            if($group) $filterCriteria[] = "Group: " . $group->name;
        }

        $services = $query->get();
        $filterCriteriaString = !empty($filterCriteria) ? implode(' | ', $filterCriteria) : "All Services";

        // --- PDF Generation ---
        $reportTitle = 'Services List';
        $pdf = new MyCustomTCPDF($reportTitle, null, 'P', 'mm', 'A4',  true,
        'UTF-8',
        false,
        false,
        $filterCriteriaString);
        $pdf->AddPage();
        
        // --- Table Headers ---
        $headers = ['ID', 'Service Name', 'Service Group', 'Price', 'Status', 'Variable Price?'];
        // A4 Portrait width ~190mm usable
        $colWidths = [15, 70, 35, 25, 20, 25];
        $colWidths[count($colWidths)-1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($colWidths, 0, -1));
        $alignments = ['C', 'L', 'L', 'R', 'C', 'C'];
        
        $pdf->DrawTableHeader($headers, $colWidths, $alignments);
        
        // --- Table Body ---
        $fill = false;
        if($services->isEmpty()){
            $pdf->Cell(array_sum($colWidths), 10, 'No services found matching the criteria.', 1, 1, 'C');
        } else {
            foreach ($services as $service) {
                $rowData = [
                    $service->id,
                    $service->name,
                    $service->serviceGroup?->name ?? 'N/A',
                    number_format((float)$service->price, 2),
                    $service->activate ? 'Active' : 'Inactive',
                    $service->variable ? 'Yes' : 'No'
                ];
                $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill, 6); // Base height 6
                $fill = !$fill;
            }
        }
        // Draw final line under the table
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
        
        $pdfFileName = 'Services_List_' . date('Y-m-d') . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S'); // 'S' returns as string

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }

    /**
     * Generate Cash Reconciliation PDF Report
     */
    public function generateCashReconciliationPdf(Request $request)
    {
        // Permission Check
        // if (!Auth::user()->can('print cash_reconciliation_report')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $result = CashReconciliationReport::generateFromRequest($request);
        
        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response($result['content'], 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "attachment; filename=\"{$result['filename']}\"");
    }

    /**
     * Generate Cash Reconciliation PDF Report for Web (opens in new tab)
     */
    public function generateCashReconciliationPdfWeb(Request $request)
    {
        $result = CashReconciliationReport::generateFromRequest($request);
        
        if (isset($result['error'])) {
            return response()->json(['message' => $result['error']], $result['status']);
        }

        return response($result['content'], 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$result['filename']}\"");
    }

    // Ensure MyCustomTCPDF has drawTextWatermark and drawReportSignatures methods, or define them here
    // ... other helper methods like drawReportSignatures ...
}









