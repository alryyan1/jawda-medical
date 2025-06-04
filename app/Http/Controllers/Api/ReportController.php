<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// app/Http/Controllers/Api/ReportController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceGroup; // For filter
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // For aggregate functions
use Carbon\Carbon;
// You might create a specific Resource for this report item if needed
use App\Http\Resources\ServiceResource; // Can be adapted or a new one created
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
use App\Services\Pdf\MyCustomTCPDF;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;
use App\Models\CostCategory;
use App\Models\RequestedServiceDeposit;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    // ... (other report methods) ...

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
        return response()->json([
            'data' => $statistics->through(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => (float) $service->price,
                    'activate' => (bool) $service->activate,
                    'service_group_id' => $service->service_group_id,
                    'service_group_name' => $service->serviceGroup?->name, // From with('serviceGroup')
                    'request_count' => (int) $service->request_count,
                    // 'total_revenue' => (float) ($service->total_revenue ?? 0),
                ];
            }),
            'links' => [
                'first' => $statistics->url(1),
                'last' => $statistics->url($statistics->lastPage()),
                'prev' => $statistics->previousPageUrl(),
                'next' => $statistics->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $statistics->currentPage(),
                'from' => $statistics->firstItem(),
                'last_page' => $statistics->lastPage(),
                'path' => $statistics->path(),
                'per_page' => $statistics->perPage(),
                'to' => $statistics->lastItem(),
                'total' => $statistics->total(),
            ],
        ]);
    }

    public function doctorShiftsReportPdf(Request $request)
    {
        // Permission Check (ensure this permission is defined and assigned)
        // if (!Auth::user()->can('view doctor_shift_reports')) { 
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // return $request->all();

        // Validation for filters (same as before)
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'status' => 'nullable|in:0,1,all',
            'shift_id' => 'nullable|integer|exists:shifts,id',
        ]);

        // --- Fetch Data (same logic as before) ---
        $query = DoctorShift::with([
            'doctor',
            'user',
            'generalShift',
        ])
            ->latest('start_time');

        $filterCriteria = [];
        // ... (filter application logic - same as before) ...
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $from = Carbon::parse($request->date_from)->startOfDay();
            $to = Carbon::parse($request->date_to)->endOfDay();
            $query->whereBetween('start_time', [$from, $to]);
            $filterCriteria[] = "التاريخ من: " . $from->format('Y-m-d') . " إلى: " . $to->format('Y-m-d');
        } // ... (add other filters for doctor_id, status, shift_id as before) ...

        $doctorShifts = $query->get();

        if ($doctorShifts->isEmpty()) {
            return response()->json(['message' => 'لا توجد بيانات لإنشاء التقرير بناءً على الفلاتر المحددة.'], 404);
        }

        $filterCriteriaString = !empty($filterCriteria) ? "الفلاتر: " . implode(' | ', $filterCriteria) : "عرض كل المناوبات";

        // --- PDF Generation ---
        // Using TCPDF constants for orientation, unit, format
        // ***** CORRECTED INSTANTIATION *****
        $pdf = new MyCustomTCPDF(
            'تقرير مناوبات الأطباء',      // $reportTitle
            $filterCriteriaString,         // $filterCriteria
            'L',                           // $orientation (Landscape)
            'mm',                          // $unit (PDF_UNIT if defined and preferred)
            'A4',                          // $format (PDF_PAGE_FORMAT if defined and preferred)
            true,                          // $unicode
            'UTF-8',                       // $encoding
            false,                         // $diskcache
            false                          // $pdfa
        );
        $pdf->AddPage();
        $pdf->SetLineWidth(0.1); // Thinner lines for table borders

        // --- Table Header ---
        $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 9); // Bold for table headers
        $pdf->SetFillColor(230, 230, 230); // Light grey background for header
        $pdf->SetTextColor(0); // Black text
        $border = 1; // 'LTRB' or 1 for all borders
        $align = 'C'; // Center align headers
        $ln = 0; // Next cell to the right
        $fill = true; // Fill header background

        // Define column widths (total should be around page width - margins, e.g., 297mm - 10mm - 10mm = 277 for A4 Landscape)
        // A4 Landscape width = 297mm. Margins L=10, R=10. Usable width = 277mm.
        $colWidths = [
            55, // Doctor Name (20%)
            40, // General Shift (15%)
            55, // Start Time (20%)
            55, // End Time (20%)
            27, // Duration (10%)
            27, // Status (10%)
            // 27.7, // Opened By (10%) - if adding this, adjust others slightly
        ];
        // Add one more for "Opened By" if you keep it
        $openedByWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum($colWidths);
        if ($openedByWidth < 15) $openedByWidth = 20; // Ensure minimum width
        $colWidths[] = $openedByWidth;
        $pdf->setRTL(true); // Set RTL for Arabic text

        $pdf->Cell($colWidths[0], 7, 'اسم الطبيب', $border, $ln, $align, $fill);
        $pdf->Cell($colWidths[1], 7, 'الوردية العامة', $border, $ln, $align, $fill);
        $pdf->Cell($colWidths[2], 7, 'وقت البدء', $border, $ln, $align, $fill);
        $pdf->Cell($colWidths[3], 7, 'وقت الإنتهاء', $border, $ln, $align, $fill);
        $pdf->Cell($colWidths[4], 7, 'المدة', $border, $ln, $align, $fill);
        $pdf->Cell($colWidths[5], 7, 'الحالة', $border, $ln, $align, $fill);
        $pdf->Cell($colWidths[6], 7, 'فُتحت بواسطة', $border, 1, $align, $fill); // ln=1 to move to next line

        // --- Table Body ---
        $pdf->SetFont($pdf->getDefaultFontFamily(), '', 8); // Regular for table content
        $pdf->SetFillColor(255, 255, 255); // White background for cells
        $fillRow = false; // For alternating row colors (optional)

        foreach ($doctorShifts as $ds) {
            $startTime = $ds->start_time ? Carbon::parse($ds->start_time)->format('Y-m-d h:i A') : '-';
            $endTime = $ds->end_time ? Carbon::parse($ds->end_time)->format('Y-m-d h:i A') : '-';
            $duration = '-';
            if ($ds->start_time && $ds->end_time) {
                $duration = Carbon::parse($ds->start_time)->diff(Carbon::parse($ds->end_time))->format('%H س %I د');
            } elseif ($ds->start_time && $ds->status) { // If active and started
                $duration = Carbon::parse($ds->start_time)->diffForHumans(now(), true) . ' (مستمرة)';
            }

            // Check if MultiCell is needed for any field (e.g., long doctor name)
            // For simple text, Cell is fine. For text that might wrap, MultiCell is better.
            // Using current Y to draw all cells at the same height for the row.
            $currentY = $pdf->GetY();
            $rowMaxHeight = 6; // Minimum row height

            // Calculate max height needed for this row if using MultiCell extensively
            // For now, assuming single line is mostly sufficient or Cell's auto-height adjustment will work.

            // Doctor Name (might need MultiCell if names can be long)
            $pdf->MultiCell($colWidths[0], $rowMaxHeight, $ds->doctor->name ?? '-', $border, 'R', $fillRow, $ln, '', '', true, 0, false, true, $rowMaxHeight, 'M');
            $pdf->MultiCell($colWidths[1], $rowMaxHeight, $ds->generalShift->name ?? ($ds->shift_id ? '#' . $ds->shift_id : '-'), $border, 'C', $fillRow, $ln, '', '', true, 0, false, true, $rowMaxHeight, 'M');
            $pdf->MultiCell($colWidths[2], $rowMaxHeight, $startTime, $border, 'C', $fillRow, $ln, '', '', true, 0, false, true, $rowMaxHeight, 'M');
            $pdf->MultiCell($colWidths[3], $rowMaxHeight, $endTime, $border, 'C', $fillRow, $ln, '', '', true, 0, false, true, $rowMaxHeight, 'M');
            $pdf->MultiCell($colWidths[4], $rowMaxHeight, $duration, $border, 'C', $fillRow, $ln, '', '', true, 0, false, true, $rowMaxHeight, 'M');
            $pdf->MultiCell($colWidths[5], $rowMaxHeight, ($ds->status ? 'مفتوحة' : 'مغلقة'), $border, 'C', $fillRow, $ln, '', '', true, 0, false, true, $rowMaxHeight, 'M');
            $pdf->MultiCell($colWidths[6], $rowMaxHeight, $ds->user->name ?? '-', $border, 'R', $fillRow, 1, '', '', true, 0, false, true, $rowMaxHeight, 'M'); // ln=1 for last cell

            $fillRow = !$fillRow; // Alternate row fill color if desired
        }

        // --- Output PDF ---
        $pdfFileName = 'doctor_shifts_report_' . date('Ymd_His') . '.pdf';
        // TCPDF's Output method handles Content-Disposition for 'I' and 'D'
        // --- Output PDF ---
        $pdfFileName = 'lab_price_list_' . date('Ymd_His') . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
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
        $headerFont = $pdf->getDefaultFontFamily(); // Or $pdf->defaultFontBold
        $dataFont = $pdf->getDefaultFontFamily();

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
            $pdf->MultiCell($priceWidth, $cellHeight, number_format((float)$test->price, 2), 1, 'C', false, 0, '', '', true, 0, false, true, $cellHeight, 'M'); // ln=0 if more blocks in this row

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

        $pdf = new MyCustomTCPDF($reportTitle, $filterCriteriaString, 'P', 'mm', 'A4');
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
                ? number_format((float)$service->pivot->static_endurance, 2) . ' (ثابت)'
                : number_format((float)$service->pivot->percentage_endurance, 1) . '%';

            $rowData = [
                $service->name,
                $service->serviceGroup?->name ?? '-',
                number_format((float)$service->pivot->price, 2),
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

        $pdf = new MyCustomTCPDF($reportTitle, $filterCriteriaString, 'P', 'mm', 'A4');
        $pdf->AddPage();

        $headers = ['اسم الفحص', 'نوع العينة', 'سعر العقد', 'تحمل الشركة', 'موافقة'];
        $colWidths = [70, 35, 25, 35, 20];
        $colWidths[count($colWidths) - 1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($colWidths, 0, -1));

        $alignments = ['R', 'R', 'C', 'C', 'C'];
        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        $fill = false;
        foreach ($contractedTests as $test) {
            $enduranceText = $test->pivot->use_static
                ? number_format((float)$test->pivot->endurance_static, 0) . ' (ثابت)' // Assuming static endurance for tests might be integer
                : number_format((float)$test->pivot->endurance_percentage, 1) . '%';

            $rowData = [
                $test->main_test_name,
                $test->container?->container_name ?? '-',
                number_format((float)$test->pivot->price, 2),
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
    public function generateLabVisitReportPdf(Request $request, DoctorVisit $visit)
    {
        // Permission Check: e.g., can('print lab_report', $visit)
        // if (!Auth::user()->can('print lab_report', $visit)) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Eager load all necessary data for the report
        $visit->load([
            'patient.company', // Load patient and their company
            'doctor:id,name',          // Doctor of the visit
            'labRequests' => function ($query) {
                $query->orderBy('created_at'); // Order lab requests consistently
            },
            'labRequests.mainTest:id,main_test_name,divided,pageBreak', // Main test info
            'labRequests.requestingUser:id,name', // User who requested the test
            'labRequests.results' => function ($query) {
                $query->with(['childTest' => function ($ctQuery) {
                    $ctQuery->with('unit:id,name', 'childGroup:id,name')->orderBy('test_order')->orderBy('child_test_name');
                }, 'enteredBy:id,name', 'authorizedBy:id,name'])
                    ->whereNotNull('result'); // Only results that have been entered
            }
        ]);

        if ($visit->labRequests->isEmpty()) {
            return response()->json(['message' => 'لا توجد طلبات مختبر لهذه الزيارة لإنشاء تقرير.'], 404);
        }

        // Fetch settings for header/footer content
        $appSettings = Setting::instance(); // Your helper to get settings

        $reportTitle = 'تقرير نتائج الفحوصات المخبرية';
        $filterCriteria = "زيارة رقم: {$visit->id} | تاريخ: {$visit->visit_date->format('Y-m-d')}";

        // Create PDF instance
        $pdf = new MyCustomTCPDF(
            $reportTitle,
            $filterCriteria,
            'P',
            'mm',
            'A4',
            true,
            'UTF-8',
            false
        );
        // Optionally override company details if settings are different from PDF defaults
        // if ($appSettings && $appSettings->hospital_name) $pdf->setCompanyName($appSettings->hospital_name); // (Need to add setter in MyCustomTCPDF)

        $pdf->AddPage();
        $pdf->SetLineWidth(0.1);

        // --- Section 1: Patient and Doctor Information ---
        $this->drawPatientDoctorInfo($pdf, $visit, $appSettings);

        // --- Section 2: Lab Requests and Results ---
        $currentYBeforeTests = $pdf->GetY();

        foreach ($visit->labRequests as $labRequest) {
            if ($labRequest->mainTest->pageBreak && $pdf->GetY() > $currentYBeforeTests && $pdf->GetY() > 60) { // Check if Y moved from previous and not at top
                // Add some space or check if enough space for next test header
                if ($pdf->GetY() + 20 > ($pdf->getPageHeight() - $pdf->getBreakMargin())) { // Rough check for space
                    $pdf->AddPage();
                    $currentYBeforeTests = $pdf->GetY(); // Reset for new page
                } else if ($pdf->GetY() > $currentYBeforeTests) { // If not a new page but some content, add a bit of space
                    $pdf->Ln(5);
                }
            }
            $currentYBeforeTests = $pdf->GetY(); // Update Y before this test block

            $this->drawMainTestResults($pdf, $labRequest);
            $pdf->Ln(2); // Space between main tests
        }

        // --- Section 3: Signatures / Footer Information (if not fully handled by TCPDF Footer) ---
        // $this->drawSignatures($pdf, $visit); // Example custom method

        // --- Output PDF ---
        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $visit->patient->name);
        $pdfFileName = 'LabReport_' . $visit->id . '_' . $patientNameSanitized . '_' . date('Ymd') . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }

    /**
     * Helper function to draw Patient and Doctor Info Section
     */
    protected function drawPatientDoctorInfo(MyCustomTCPDF $pdf, DoctorVisit $visit, ?Setting $settings)
    {
        $isRTL = $pdf->getRTL();
        $defaultFont = $pdf->getDefaultFontFamily();
        $labelWidth = 25;
        $valueWidth = 60;
        $spacerWidth = 5;
        $lineHeight = 5.5;

        $pdf->SetFont($defaultFont, 'B', 10);
        $pdf->Cell(0, 7, 'بيانات المريض والطبيب', 0, 1, $isRTL ? 'R' : 'L');
        $pdf->SetFont($defaultFont, '', 9);

        // Patient Info - Two Columns
        $startX = $pdf->GetX();
        $currentY = $pdf->GetY();

        // Column 1
        $pdf->SetXY($startX, $currentY);
        $pdf->Cell($labelWidth, $lineHeight, 'اسم المريض:', 0, 0, $isRTL ? 'R' : 'L');
        $pdf->MultiCell($valueWidth, $lineHeight, $visit->patient->name ?? '-', 0, $isRTL ? 'R' : 'L', false, 1);

        $currentY = $pdf->GetY(); // Update Y after MultiCell
        $pdf->SetXY($startX, $currentY);
        $pdf->Cell($labelWidth, $lineHeight, 'رقم الملف/المريض:', 0, 0, $isRTL ? 'R' : 'L');
        $pdf->Cell($valueWidth, $lineHeight, $visit->patient->id ?? '-', 0, 1, $isRTL ? 'R' : 'L'); // ln=1

        $currentY = $pdf->GetY();
        $pdf->SetXY($startX, $currentY);
        $pdf->Cell($labelWidth, $lineHeight, 'العمر:', 0, 0, $isRTL ? 'R' : 'L');
        $pdf->Cell($valueWidth, $lineHeight, $visit->patient->getFullAgeAttribute() ?? '-', 0, 1, $isRTL ? 'R' : 'L');

        $currentY = $pdf->GetY();
        $pdf->SetXY($startX, $currentY);
        $pdf->Cell($labelWidth, $lineHeight, 'الجنس:', 0, 0, $isRTL ? 'R' : 'L');
        $pdf->Cell($valueWidth, $lineHeight, $visit->patient->gender ? trans('common.genderEnum.' . $visit->patient->gender) : '-', 0, 1, $isRTL ? 'R' : 'L');

        // Column 2 - Calculate X position
        $col2X = $startX + $labelWidth + $valueWidth + $spacerWidth;
        $pdf->SetY($pdf->GetY() - ($lineHeight * 4)); // Reset Y to start of patient info block

        $pdf->SetXY($col2X, $pdf->GetY());
        $pdf->Cell($labelWidth, $lineHeight, 'الطبيب المعالج:', 0, 0, $isRTL ? 'R' : 'L');
        $pdf->Cell($valueWidth, $lineHeight, $visit->doctor->name ?? '-', 0, 1, $isRTL ? 'R' : 'L');

        $currentY = $pdf->GetY();
        $pdf->SetXY($col2X, $currentY);
        $pdf->Cell($labelWidth, $lineHeight, 'تاريخ الزيارة:', 0, 0, $isRTL ? 'R' : 'L');
        $pdf->Cell($valueWidth, $lineHeight, $visit->visit_date?->format('Y-m-d') ?? '-', 0, 1, $isRTL ? 'R' : 'L');

        $sampleId = $visit->labRequests->first()?->sample_id ?? $visit->labRequests->first()?->id ?? '-';
        $currentY = $pdf->GetY();
        $pdf->SetXY($col2X, $currentY);
        $pdf->Cell($labelWidth, $lineHeight, 'رقم العينة/الطلب:', 0, 0, $isRTL ? 'R' : 'L');
        $pdf->Cell($valueWidth, $lineHeight, $sampleId, 0, 1, $isRTL ? 'R' : 'L');

        $requestedBy = $visit->labRequests->first()?->requestingUser?->name ?? '-';
        $currentY = $pdf->GetY();
        $pdf->SetXY($col2X, $currentY);
        $pdf->Cell($labelWidth, $lineHeight, 'طلب بواسطة:', 0, 0, $isRTL ? 'R' : 'L');
        $pdf->Cell($valueWidth, $lineHeight, $requestedBy, 0, 1, $isRTL ? 'R' : 'L');

        $pdf->Ln(3); // Space after patient/doctor info
        // Draw a line separator
        $pdf->Line($pdf->GetX(), $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
        $pdf->Ln(3);
    }

    /**
     * Helper function to draw results for a single MainTest
     */
    protected function drawMainTestResults(MyCustomTCPDF $pdf, LabRequest $labRequest)
    {
        $isRTL = $pdf->getRTL();
        $defaultFont = $pdf->getDefaultFontFamily();
        $lineHeight = 5.5;
        $indent = 5; // Indent for child tests

        // Main Test Header
        $pdf->SetFont($defaultFont, 'B', 10); // Bold and slightly larger for main test name
        // Background for Main Test Header
        $pdf->SetFillColor(240, 245, 250); // Light blue-ish
        $pdf->Cell(0, $lineHeight + 1, $labRequest->mainTest->main_test_name, 'B', 1, $isRTL ? 'R' : 'L', true); // ln=1, Fill=true, Border Bottom
        $pdf->SetFillColor(255, 255, 255); // Reset fill color
        $pdf->Ln(1);

        $childColWidths = [60, 35, 25, 50, 0];
        // Child Test Results Table for this Main Test
        if ($labRequest->results->isNotEmpty()) {
            $pdf->SetFont($defaultFont, '', 9);
            $childHeaders = ['المكون الفرعي', 'النتيجة', 'الوحدة', 'النطاق الطبيعي', 'العلامات'];
            // Usable width = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right']
            // Make name wider, others roughly equal
            $childColWidths[count($childColWidths) - 1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($childColWidths, 0, -1)) - $indent;

            $childAligns = ['R', 'C', 'C', 'C', 'C'];

            // Draw child test table header (optional, or just list them)
            $currentX = $pdf->GetX() + ($isRTL ? 0 : $indent); // Apply indent for LTR
            $pdf->SetX($currentX); // Apply indent
            $pdf->SetFont($defaultFont, 'B', 8);
            $pdf->SetFillColor(245, 245, 245);
            foreach ($childHeaders as $i => $ch) {
                $pdf->Cell($childColWidths[$i], $lineHeight, $ch, 1, ($i == count($childHeaders) - 1 ? 1 : 0), 'C', true);
            }
            $pdf->SetFont($defaultFont, '', 8);


            foreach ($labRequest->results->sortBy(fn($r) => $r->childTest?->test_order ?? 999) as $result) {
                $childTest = $result->childTest; // Assumes childTest is loaded on result
                $isAbnormal = false; // TODO: Implement abnormality check based on ranges/flags

                $resultValue = $result->result ?? '-';
                if ($isAbnormal) $resultValue = "**{$resultValue}**"; // TCPDF pseudo-bold with **

                $rowData = [
                    $childTest?->child_test_name ?? 'غير معروف',
                    $resultValue,
                    $result->unit_name ?? $childTest?->unit?->name ?? '-',
                    $result->normal_range ?? $childTest?->normalRange ?? '-', // Use snapshotted range
                    $result->flags ?? '-',
                ];

                $currentX = $pdf->GetX() + ($isRTL ? 0 : $indent);
                $pdf->SetX($currentX); // Apply indent for LTR for the row

                // Calculate row height
                $maxLines = 0;
                for ($i = 0; $i < count($rowData); $i++) {
                    $numLines = $pdf->getNumLines((string)$rowData[$i], $childColWidths[$i]);
                    if ($numLines > $maxLines) $maxLines = $numLines;
                }
                if ($maxLines == 0) $maxLines = 1;
                $cellPadding = $pdf->getCellPaddings();
                $currentLineHeight = ($pdf->getFontSize() * $pdf->getCellHeightRatio() * 0.352777778);
                if ($currentLineHeight < 3) $currentLineHeight = 3.5;
                $rowH = ($maxLines * $currentLineHeight) + $cellPadding['T'] + $cellPadding['B'] + 0.5;
                if ($rowH < 5) $rowH = 5;

                $yBeforeCall = $pdf->GetY();
                if ($yBeforeCall + $rowH > ($pdf->getPageHeight() - $pdf->getBreakMargin())) {
                    $pdf->AddPage(); // TCPDF will call Header()
                    // Redraw child test table header on new page
                    $currentX = $pdf->GetX() + ($isRTL ? 0 : $indent);
                    $pdf->SetX($currentX);
                    $pdf->SetFont($defaultFont, 'B', 8);
                    foreach ($childHeaders as $i => $ch) {
                        $pdf->Cell($childColWidths[$i], $lineHeight, $ch, 1, ($i == count($childHeaders) - 1 ? 1 : 0), 'C', true);
                    }
                    $pdf->SetFont($defaultFont, '', 8);
                    $yBeforeCall = $pdf->GetY(); // Update Y after potential page break
                }
                $xPos = $currentX;
                for ($i = 0; $i < count($rowData); $i++) {
                    $align = $childAligns[$i] ?? 'R';
                    if (is_numeric(str_replace(['<', '>'], '', $rowData[$i]))) $align = 'C';
                    if ($i === 0) $align = ($isRTL ? 'R' : 'L'); // First column align

                    // Apply special formatting for abnormal results
                    if ($isAbnormal && $i == 1) { // Assuming result is 2nd column
                        $pdf->SetFont($defaultFont, 'B', 8); // Bold for abnormal
                    }

                    $pdf->MultiCell($childColWidths[$i], $rowH, (string)$rowData[$i], 'LR', $align, false, 0, $xPos, $yBeforeCall, true, 0, $isAbnormal && $i == 1 ? true : false, true, $rowH, 'M');
                    $xPos += $childColWidths[$i];

                    if ($isAbnormal && $i == 1) {
                        $pdf->SetFont($defaultFont, '', 8); // Reset font
                    }
                }
                $pdf->Ln($rowH);
                $pdf->Line($pdf->GetX() + ($isRTL ? 0 : $indent), $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY()); // Bottom line for row
            }
        } else {
            $currentX = $pdf->GetX() + ($isRTL ? 0 : $indent);
            $pdf->SetX($currentX);
            $pdf->Cell(array_sum($childColWidths), $lineHeight, 'لم يتم إدخال نتائج لهذا الفحص بعد.', 'LTRB', 1, 'C');
        }

        // Overall comment for the MainTest (from LabRequest)
        if (!empty($labRequest->comment)) {
            $pdf->Ln(1);
            $currentX = $pdf->GetX() + ($isRTL ? 0 : $indent);
            $pdf->SetX($currentX);
            $pdf->SetFont($defaultFont, 'I', 8);
            $pdf->MultiCell(array_sum($childColWidths), $lineHeight, 'ملاحظة على الفحص: ' . $labRequest->comment, 0, $isRTL ? 'R' : 'L', false, 1);
            $pdf->Ln(1);
        }
    }
    public function generateMonthlyLabIncomePdf(Request $request)
    {
        // Permission Check: e.g., can('view monthly_lab_income_report')
        // if (!auth()->user()->can('view monthly_lab_income_report')) { ... }

        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 1),
        ]);

        $year = $validated['year'];
        $month = $validated['month'];

        $startDate = Carbon::create($year, $month, 1)->startOfDay();
        $endDate = $startDate->copy()->endOfMonth()->endOfDay();
        $period = CarbonPeriod::create($startDate, $endDate);

        $reportTitle = 'تقرير إيرادات المختبر الشهري';
        $filterCriteria = "لشهر: {$startDate->translatedFormat('F Y')} ( {$startDate->format('Y-m-d')} - {$endDate->format('Y-m-d')} )";

        // --- Data Aggregation ---
        $dailyData = [];
        $grandTotals = [
            'income' => 0,
            'discount' => 0,
            'cash' => 0,
            'bank' => 0,
        ];

        // Fetch all relevant lab requests for the month for efficiency
        // Eager load patient for company check
        $labRequestsForMonth = LabRequest::with('patient')
            ->whereBetween('created_at', [$startDate, $endDate]) // Filter by request creation date
            // Or filter by payment date if that's more relevant for "income"
            // ->whereHas('payments', function($q) use ($startDate, $endDate) { // If using a payments relation
            //     $q->whereBetween('payment_date', [$startDate, $endDate]);
            // })
            ->get();

        // Group lab requests by creation date (day)
        $requestsByDate = $labRequestsForMonth->groupBy(function ($request) {
            return Carbon::parse($request->created_at)->format('Y-m-d');
        });


        foreach ($period as $date) {
            $currentDateStr = $date->format('Y-m-d');
            $dailyIncome = 0;
            $dailyDiscount = 0;
            $dailyCash = 0;
            $dailyBank = 0;

            if ($requestsByDate->has($currentDateStr)) {
                foreach ($requestsByDate[$currentDateStr] as $lr) {
                    $price = (float) ($lr->price ?? 0);
                    $count = (int) ($lr->count ?? 1);
                    $itemSubTotal = $price * $count;

                    $discountAmount = ($itemSubTotal * ((int) ($lr->discount_per ?? 0) / 100));
                    // Add fixed discount if you have it: + (float)($lr->fixed_discount_amount ?? 0);

                    $enduranceAmount = (float) ($lr->endurance ?? 0);
                    $isCompanyPatient = !!$lr->patient?->company_id;

                    $netPayableByPatient = $itemSubTotal - $discountAmount - ($isCompanyPatient ? $enduranceAmount : 0);

                    // Income is based on the net amount the patient is supposed to pay for services rendered ON this day
                    // This assumes 'created_at' of LabRequest signifies the service rendering day for income recognition
                    $dailyIncome += $netPayableByPatient;
                    $dailyDiscount += $discountAmount; // Summing calculated discount for the day

                    // For cash/bank, we sum what was ACTUALLY collected for requests of this day
                    // This assumes labrequests.is_paid and labrequests.amount_paid reflect collection for that request.
                    // If payments are separate, this logic needs to change.
                    if ($lr->is_paid || $lr->amount_paid > 0) {
                        // This is tricky: amount_paid might be partial.
                        // For simplicity of this report based on current LabRequest model:
                        // If you want actual collected cash/bank for THIS DAY, you need a payment date on LabRequest
                        // or join with a payment/deposit table filtered by payment_date.
                        // Let's assume if it's paid, the amount_paid is the collected amount for that item on its creation day.
                        $collectedAmountForItem = (float) $lr->amount_paid;

                        if ($lr->is_bankak) { // Or your field for bank payment
                            $dailyBank += $collectedAmountForItem;
                        } else {
                            $dailyCash += $collectedAmountForItem;
                        }
                    }
                }
            }

            $dailyData[$currentDateStr] = [
                'date' => $currentDateStr,
                'income' => $dailyIncome,
                'discount' => $dailyDiscount,
                'cash' => $dailyCash,
                'bank' => $dailyBank,
            ];

            $grandTotals['income'] += $dailyIncome;
            $grandTotals['discount'] += $dailyDiscount;
            $grandTotals['cash'] += $dailyCash;
            $grandTotals['bank'] += $dailyBank;
        }


        // --- PDF Generation ---
        $pdf = new MyCustomTCPDF($reportTitle, $filterCriteria, 'L', 'mm', 'A4'); // Landscape
        $pdf->AddPage();
        $pdf->SetLineWidth(0.1);

        // Table Header
        $headers = ['التاريخ', 'إجمالي الإيراد (الصافي)', 'إجمالي الخصومات', 'المحصل نقداً', 'المحصل بنك/شبكة'];
        // A4 Landscape width ~277mm usable
        $colWidths = [40, 60, 50, 60, 0];
        $colWidths[count($colWidths) - 1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($colWidths, 0, -1));
        $alignments = ['C', 'C', 'C', 'C', 'C'];
        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        // Table Body
        $fill = false;
        $pdf->SetFont($pdf->getDefaultFontFamily(), '', 8);
        foreach ($dailyData as $dayData) {
            if ($dayData['income'] == 0 && $dayData['cash'] == 0 && $dayData['bank'] == 0 && $dayData['discount'] == 0) {
                // Optionally skip days with no activity to make report shorter
                // continue; 
            }
            $rowData = [
                Carbon::parse($dayData['date'])->format('Y-m-d (D)'), // Format date with day name
                number_format($dayData['income'], 2),
                number_format($dayData['discount'], 2),
                number_format($dayData['cash'], 2),
                number_format($dayData['bank'], 2),
            ];
            $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill);
            $fill = !$fill;
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY()); // Bottom line for table
        $pdf->Ln(5);

        // Grand Totals Section
        $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 10);
        $pdf->Cell(0, 8, 'ملخص إجمالي للشهر', 0, 1, $pdf->getRTL() ? 'R' : 'L');

        $pdf->SetFont($pdf->getDefaultFontFamily(), '', 9);
        $totalLabelWidth = 60;
        $totalValueWidth = 50;

        $pdf->Cell($totalLabelWidth, 7, 'إجمالي الإيرادات (الصافي):', 'LTRB', 0, 'R');
        $pdf->Cell($totalValueWidth, 7, number_format($grandTotals['income'], 2), 'LTRB', 1, 'C');
        $pdf->Cell($totalLabelWidth, 7, 'إجمالي الخصومات الممنوحة:', 'LTRB', 0, 'R');
        $pdf->Cell($totalValueWidth, 7, number_format($grandTotals['discount'], 2), 'LTRB', 1, 'C');
        $pdf->Cell($totalLabelWidth, 7, 'إجمالي المحصل نقداً:', 'LTRB', 0, 'R');
        $pdf->Cell($totalValueWidth, 7, number_format($grandTotals['cash'], 2), 'LTRB', 1, 'C');
        $pdf->Cell($totalLabelWidth, 7, 'إجمالي المحصل بنك/شبكة:', 'LTRB', 0, 'R');
        $pdf->Cell($totalValueWidth, 7, number_format($grandTotals['bank'], 2), 'LTRB', 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 9);
        $pdf->Cell($totalLabelWidth, 7, 'إجمالي صافي الدخل المحصل:', 'LTRB', 0, 'R');
        $pdf->Cell($totalValueWidth, 7, number_format($grandTotals['cash'] + $grandTotals['bank'], 2), 'LTRB', 1, 'C');


        // --- Output PDF ---
        $pdfFileName = 'monthly_lab_income_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT) . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
    public function clinicReport(Request $request, DoctorShift $doctorShift)
    {
        
        if($request->get('doctor_shift_id')){
            $doctorShift = DoctorShift::find($request->get('doctor_shift_id'));
        }

        // $userId = $request->get('user'); // Not used in your original code for filtering DoctorShift
        $doctorShift->load([
            'user:id,username',
            'doctor:id,name,cash_percentage,company_percentage,static_wage', // Load percentages
            'visits.patient.company:id,name', // Load patient and their company for each visit
            'visits.requestedServices.service:id,name', // Load services for each visit
            'visits.labRequests.mainTest:id,main_test_name', // Load lab tests for each visit
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
            $filterCriteria,                 // Filters
            'L',                             // Orientation: Landscape
            'mm',
            'A4',
            true,
            'UTF-8',
            false
        );

        // Attempt to add Arial (ensure arial.ttf is in a TCPDF accessible font path or public_path if using that)
        // This path needs to be correct or TCPDF needs to find it in its font dirs.
        // $fontPath = public_path('fonts/arial.ttf'); // Example if in public/fonts
        // For TCPDF internal fonts like dejavusans, this is not needed.
        // If using a custom TTF font, ensure it's correctly added via TCPDF_FONTS::addTTFfont
        // For now, relying on MyCustomTCPDF's default font (dejavusans)
        // 'arial' = $pdf->getDefaultFontFamily(); // Use the default from your custom class
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
                number_format($doctorvisit->calculateTotalServiceValue(), 1), // Using model method
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
        $doctor_shift_id = $request->get('doctor_shift_id');
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
        $pdf = new MyCustomTCPDF('تقرير العام', '', 'L', 'mm', 'A4');

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
            $totalPaid = $shift->paidLab($user->id) + $shift->totalPaidService($user->id);
            $totalBank = $shift->bankakLab($user->id) + $shift->totalPaidServiceBank($user->id);
            // Costs specific to this user within this shift (if applicable)
            $totalCostForUser = $shift->totalCost($user->id); // Ensure this method exists and is relevant
            $totalCostBankForUser = $shift->totalCostBank($user->id);
            $totalCost = $shift->totalCost($user->id);
            $totalCostBank = $shift->totalCostBank($user->id);
            $totalCostCash = $totalCost - $totalCostBank;
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
            $pdf->Cell($headerColWidth, 6, number_format(($totalPaid - $totalBank) - $totalCostCash, 2), 1, 0, 'C');
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
        // Permission Check
        // if (!Auth::user()->can('print thermal_receipt', $visit)) { ... }

        $visit->load([
            'patient:id,name,phone,company_id', // Added company_id
            'patient.company:id,name', // To display company name if present
            'requestedServices.service:id,name,price', 
            'doctor:id,name' // Doctor of the visit
        ]);

        if ($visit->requestedServices->isEmpty()) {
            return response()->json(['message' => 'لا توجد خدمات لإنشاء إيصال لها في هذه الزيارة.'], 404);
        }

        $appSettings = Setting::instance();
        $isCompanyPatient = !empty($visit->patient->company_id);

        // --- PDF Instantiation with Thermal Defaults ---
        $pdf = new MyCustomTCPDF('إيصال خدمات', "زيارة رقم: {$visit->id}"); // Title and filter less prominent on thermal
        $pdf->setThermalDefaults( (float)($appSettings?->thermal_printer_width ?? 76) ); // Use setting or default
        $pdf->AddPage();
        
        $fontName = $pdf->getDefaultFontFamily(); // Using the one from MyCustomTCPDF defaults for thermal
        $isRTL = $pdf->getRTL();
        $alignRight = $isRTL ? 'L' : 'R';
        $alignLeft = $isRTL ? 'R' : 'L';
        $alignCenter = 'C';

        // --- Clinic/Company Header ---
        $logoData = null;
        if ($appSettings?->logo_base64 && str_starts_with($appSettings->logo_base64, 'data:image')) {
             try { $logoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $appSettings->logo_base64)); } catch (\Exception $e) {}
        }
        
        if ($logoData) {
            $pdf->Image('@'.$logoData, '', '', 15, 0, '', '', 'T', false, 300, $alignCenter, false, false, 0, false, false, false); // Centered logo, width 15mm
            $pdf->Ln(1); // Space after logo if small
        }

        $pdf->SetFont($fontName, 'B', $logoData ? 9 : 10);
        $pdf->MultiCell(0, 4, $appSettings?->hospital_name ?: ($appSettings?->lab_name ?: config('app.name')), 0, $alignCenter, false, 1);
        
        $pdf->SetFont($fontName, '', 6.5); // Smaller font for details
        if ($appSettings?->address) $pdf->MultiCell(0, 3, $appSettings->address, 0, $alignCenter, false, 1);
        if ($appSettings?->phone) $pdf->MultiCell(0, 3, "الهاتف: " . $appSettings->phone, 0, $alignCenter, false, 1);
        if ($appSettings?->vatin) $pdf->MultiCell(0, 3, "رقم ضريبي: " . $appSettings->vatin, 0, $alignCenter, false, 1);
        
        $pdf->Ln(1);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C'); // Horizontal line
        $pdf->Ln(1);

        // Receipt Info
        $pdf->SetFont($fontName, '', 7.5);
        $receiptNumber =  $visit->id ; // More unique
        $pdf->Cell(0, 3.5, "رقم الفاتورة: " . $receiptNumber, 0, 1, $alignRight);
        $pdf->Cell(0, 3.5, "رقم الانتظار: " . $visit->number, 0, 1, $alignRight);
        $pdf->Cell(0, 3.5, "التاريخ: " . Carbon::now()->format('Y/m/d H:i A'), 0, 1, $alignRight);
        $pdf->Cell(0, 3.5, "المريض: " . $visit->patient->name, 0, 1, $alignRight);
        if ($visit->patient->phone) $pdf->Cell(0, 3.5, "الهاتف: " . $visit->patient->phone, 0, 1, $alignRight);
        if ($isCompanyPatient && $visit->patient->company) $pdf->Cell(0, 3.5, "الشركة: " . $visit->patient->company->name, 0, 1, $alignRight);
        if ($visit->doctor) $pdf->Cell(0, 3.5, "الطبيب: " . $visit->doctor->name, 0, 1, $alignRight);
        $pdf->Cell(0, 3.5, "الكاشير: " . (Auth::user()?->name ?? 'النظام'), 0, 1, $alignRight);
        
        // Barcode for Visit ID
        if($appSettings?->barcode){
            $pdf->Ln(2);
            $style = [
                'position' => '', 'align' => 'C', 'stretch' => false, 'fitwidth' => true, 'cellfitalign' => '',
                'border' => false, 'hpadding' => 'auto', 'vpadding' => 'auto', 'fgcolor' => [0,0,0],
                'bgcolor' => false, 'text' => true, 'font' => $pdf->getDefaultFontFamily(), 'fontsize' => 6, 'stretchtext' => 4
            ];
            $pdf->write1DBarcode((string)$visit->id, 'C128B', '', '', '', 12, 0.3, $style, 'N'); // Height 12mm, bar width 0.3mm
            $pdf->Ln(1);
        }

        $pdf->Ln(2);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C'); // Horizontal line
        $pdf->Ln(1);


        // Items Table
        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $nameWidth = $pageUsableWidth * 0.48; 
        $qtyWidth  = $pageUsableWidth * 0.12; 
        $priceWidth = $pageUsableWidth * 0.20; 
        $totalWidth = $pageUsableWidth * 0.20; 

        $pdf->SetFont($fontName, 'B', 7);
        $pdf->Cell($nameWidth, 4, 'البيان', 'B', 0, 'R');
        $pdf->Cell($qtyWidth, 4, 'كمية', 'B', 0, 'C');
        $pdf->Cell($priceWidth, 4, 'سعر', 'B', 0, 'C');
        $pdf->Cell($totalWidth, 4, 'إجمالي', 'B', 1, 'C');
        $pdf->SetFont($fontName, '', 7);

        $subTotalServices = 0;
        $totalDiscountOnServices = 0;
        $totalEnduranceOnServices = 0;

        foreach ($visit->requestedServices as $rs) {
            $serviceName = $rs->service?->name ?? 'خدمة غير معروفة';
            $quantity = (int)($rs->count ?? 1);
            $unitPrice = (float)($rs->price ?? 0); // This should be the contract price if company, else standard
            $itemGrossTotal = $unitPrice * $quantity;
            $subTotalServices += $itemGrossTotal;

            $itemDiscountPercent = (float)($rs->discount_per ?? 0);
            $itemDiscountFixed = (float)($rs->discount ?? 0);
            $itemDiscountAmount = (($itemGrossTotal * $itemDiscountPercent) / 100) + $itemDiscountFixed;
            $totalDiscountOnServices += $itemDiscountAmount;
            
            $itemNetAfterDiscount = $itemGrossTotal - $itemDiscountAmount;

            $itemEndurance = 0;
            if ($isCompanyPatient) {
                $itemEndurance = (float)($rs->endurance ?? 0) * $quantity; // Endurance per item * count
                $totalEnduranceOnServices += $itemEndurance;
            }
            
            // For display in table, we show the item's gross total before endurance
            $pdf->MultiCell($nameWidth, 3.5, $serviceName, 0, 'R', false, 0, '', '', true, 0, false, true, 0, 'T');
            $currentY = $pdf->GetY(); // Capture Y after MultiCell might have wrapped
            $pdf->SetXY($pdf->getMargins()['left'] + $nameWidth, $currentY);

            $pdf->Cell($qtyWidth, 3.5, $quantity, 0, 0, 'C');
            $pdf->Cell($priceWidth, 3.5, number_format($unitPrice, 2), 0, 0, 'C');
            $pdf->Cell($totalWidth, 3.5, number_format($itemGrossTotal, 2), 0, 1, 'C');
            $pdf->SetY(max($pdf->GetY(), $currentY + ($pdf->getNumLines($serviceName, $nameWidth) * 3.5) )); // Ensure Y moves past tallest cell
        }
        $pdf->Ln(1);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(1);

        // Totals Section
        $pdf->SetFont($fontName, '', 7.5); // Slightly larger for totals
        
        $this->drawThermalTotalRow($pdf, 'إجمالي الخدمات:', $subTotalServices, $pageUsableWidth);
        if ($totalDiscountOnServices > 0) {
            $this->drawThermalTotalRow($pdf, 'إجمالي الخصم:', -$totalDiscountOnServices, $pageUsableWidth, 'text-red-500');
        }
        
        $netAfterDiscount = $subTotalServices - $totalDiscountOnServices;
        $companyWillPayOnFuture = $netAfterDiscount - ($isCompanyPatient ? $totalEnduranceOnServices : 0);
        if ($isCompanyPatient) {
            $this->drawThermalTotalRow($pdf, 'تحمل الشركة:', -$companyWillPayOnFuture, $pageUsableWidth, 'text-blue-500');
        }
        
        $pdf->SetFont($fontName, 'B', 8.5);
        $this->drawThermalTotalRow($pdf, 'صافي المطلوب من المريض:', $totalEnduranceOnServices, $pageUsableWidth, true);
        $pdf->SetFont($fontName, '', 7.5);

        $totalPaidByPatient = $visit->requestedServices->sum('amount_paid'); // Assumes amount_paid is patient's payment
        $this->drawThermalTotalRow($pdf, 'المبلغ المدفوع:', $totalPaidByPatient, $pageUsableWidth);
        
        $balanceDue = $companyWillPayOnFuture - $totalPaidByPatient;
        $pdf->SetFont($fontName, 'B', 8.5);
        $this->drawThermalTotalRow($pdf, 'المبلغ المتبقي:', $visit->amountRemaining(), $pageUsableWidth, ($balanceDue != 0));

        $pdf->Ln(2);
     


        $pdf->Ln(3);
        $pdf->SetFont($fontName, 'I', 6.5);
        $footerMessage = $appSettings?->receipt_footer_message ?: 'شكراً لزيارتكم!';
        $pdf->MultiCell(0, 3, $footerMessage, 0, 'C', false, 1);
        $pdf->Ln(5); 

        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $visit->patient->name);
        $pdfFileName = 'Receipt_Visit_' . $visit->id . '_' . $patientNameSanitized . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }

    protected function drawThermalTotalRow(MyCustomTCPDF $pdf, string $label, float $value, float $pageUsableWidth, bool $isBoldValue = false, string $valueClass = '')
    {
        $fontName = $pdf->getDefaultFontFamily();
        $currentFontSize = $pdf->getFontSizePt();
        $currentStyle = $pdf->getFontStyle();

        $labelWidth = $pageUsableWidth * 0.60;
        $valueWidth = $pageUsableWidth * 0.40;

        if ($isBoldValue) $pdf->SetFont($fontName, 'B', $currentFontSize + 0.5); // Slightly larger if bold

        $pdf->Cell($labelWidth, 4, $label, 0, 0, $pdf->getRTL() ? 'R' : 'L');
        $pdf->Cell($valueWidth, 4, number_format($value, 2), 0, 1, $pdf->getRTL() ? 'L' : 'R');
        
        if ($isBoldValue) $pdf->SetFont($fontName, $currentStyle, $currentFontSize); // Reset
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
        if ($isBoldValue) $pdf->SetFont($currentFont, 'B', $pdf->getFontSizePt());
        $pdf->Cell($valueWidth, 5, number_format($value, 2), 0, 1, 'L'); // Align value to left for numbers
        if ($isBoldValue) $pdf->SetFont($currentFont, $currentStyle, $pdf->getFontSizePt()); // Reset
    }
    public function generateCostsReportPdf(Request $request)
    {
        // Permission Check: e.g., can('print cost_report')
        // if (!Auth::user()->can('print cost_report')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Validation
        $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'cost_category_id' => 'nullable|integer|exists:cost_categories,id',
            'user_id' => 'nullable|integer|exists:users,id',
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'payment_method' => 'nullable|string|in:cash,bank,all',
            'per_page' => 'nullable|integer|min:10|max:100',
            'sort_by' => 'nullable|string|in:created_at,amount,description',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'export_format' => 'nullable|string|in:pdf,excel,csv',
            'group_by' => 'nullable|string|in:day,week,month,category,user',
        ]);

        // --- Fetch Data ---
        $query = Cost::with(['costCategory:id,name', 'userCost:id,name', 'shift:id', 'doctorShift.doctor:id,name']);
        $filterCriteria = [];

        // Date range filter
        if ($request->filled('date_from')) {
            $from = Carbon::parse($request->date_from)->startOfDay();
            $query->whereDate('created_at', '>=', $from);
            $filterCriteria[] = "من تاريخ: " . $from->format('Y-m-d');
        }
        if ($request->filled('date_to')) {
            $to = Carbon::parse($request->date_to)->endOfDay();
            $query->whereDate('created_at', '<=', $to);
            $filterCriteria[] = "إلى تاريخ: " . $to->format('Y-m-d');
        }

        // Cost category filter
        if ($request->filled('cost_category_id')) {
            $query->where('cost_category_id', $request->cost_category_id);
            $category = CostCategory::find($request->cost_category_id);
            if ($category) {
                $filterCriteria[] = "الفئة: " . $category->name;
            }
        }

        // User filter
        if ($request->filled('user_id')) {
            $query->where('user_cost', $request->user_id);
            $user = User::find($request->user_id);
            if ($user) {
                $filterCriteria[] = "المستخدم: " . $user->name;
            }
        }

        // Shift filter
        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
            $filterCriteria[] = "الوردية رقم: " . $request->shift_id;
        }

        // Payment method filter
        if ($request->filled('payment_method')) {
            if ($request->payment_method === 'cash') {
                $query->where('amount', '>', 0)->where('amount_bankak', '=', 0);
                $filterCriteria[] = "طريقة الدفع: نقداً";
            } elseif ($request->payment_method === 'bank') {
                $query->where('amount_bankak', '>', 0);
                $filterCriteria[] = "طريقة الدفع: بنك/شبكة";
            }
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        if ($sortBy === 'amount') {
            $query->orderByRaw('(amount + amount_bankak) ' . $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $costs = $query->get();

        if ($costs->isEmpty()) {
            return response()->json(['message' => 'لا توجد مصروفات تطابق هذه الفلاتر لإنشاء التقرير.'], 404);
        }

        $filterCriteriaString = !empty($filterCriteria) ? implode(' | ', $filterCriteria) : "جميع المصروفات";

        // --- PDF Generation ---
        $pdf = new MyCustomTCPDF('تقرير المصروفات', $filterCriteriaString, 'L', 'mm', 'A4');
        $pdf->AddPage();

        // Summary Section
        $totalCash = $costs->sum('amount');
        $totalBank = $costs->sum('amount_bankak');
        $grandTotal = $totalCash + $totalBank;

        $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 12);
        $pdf->Cell(0, 8, 'ملخص المصروفات', 0, 1, 'C');
        $pdf->Ln(2);

        $pdf->SetFont($pdf->getDefaultFontFamily(), '', 10);
        $summaryWidth = ($pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right']) / 3;
        $pdf->Cell($summaryWidth, 7, 'إجمالي المصروفات النقدية: ' . number_format($totalCash, 2), 1, 0, 'C');
        $pdf->Cell($summaryWidth, 7, 'إجمالي المصروفات البنكية: ' . number_format($totalBank, 2), 1, 0, 'C');
        $pdf->Cell($summaryWidth, 7, 'إجمالي المصروفات: ' . number_format($grandTotal, 2), 1, 1, 'C');
        $pdf->Ln(5);

        // Detailed Costs Table
        $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 10);
        $pdf->Cell(0, 8, 'تفاصيل المصروفات', 0, 1, 'C');
        $pdf->Ln(2);

        $headers = ['التاريخ', 'الوصف', 'الفئة', 'المستخدم', 'الوردية', 'نقداً', 'بنك/شبكة', 'الإجمالي'];
        $colWidths = [25, 65, 35, 35, 25, 25, 25, 0];
        $colWidths[count($colWidths) - 1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($colWidths, 0, -1));
        $alignments = ['C', 'R', 'R', 'R', 'C', 'C', 'C', 'C'];
        
        $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 9);
        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        $pdf->SetFont($pdf->getDefaultFontFamily(), '', 8);
        $fill = false;

        foreach ($costs as $cost) {
            $rowData = [
                Carbon::parse($cost->created_at)->format('Y-m-d H:i'),
                $cost->description,
                $cost->costCategory?->name ?? '-',
                $cost->userCost?->name ?? '-',
                $cost->shift?->name ?? ($cost->shift_id ? '#' . $cost->shift_id : '-'),
                number_format($cost->amount, 2),
                number_format($cost->amount_bankak, 2),
                number_format($cost->amount + $cost->amount_bankak, 2),
            ];
            $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill);
            $fill = !$fill;
        }

        // Draw final line under the table
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
        $pdf->Ln(2);

        // Grand Total Row
        $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 9);
        $totalColsWidth = array_sum(array_slice($colWidths, 0, 5));
        $pdf->Cell($totalColsWidth, 7, 'الإجمالي:', 1, 0, 'R', true);
        $pdf->Cell($colWidths[5], 7, number_format($totalCash, 2), 1, 0, 'C', true);
        $pdf->Cell($colWidths[6], 7, number_format($totalBank, 2), 1, 0, 'C', true);
        $pdf->Cell($colWidths[7], 7, number_format($grandTotal, 2), 1, 1, 'C', true);

        // Category Summary if grouped by category
        if ($request->input('group_by') === 'category') {
            $pdf->AddPage();
            $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 12);
            $pdf->Cell(0, 8, 'ملخص المصروفات حسب الفئة', 0, 1, 'C');
            $pdf->Ln(2);

            $categoryTotals = $costs->groupBy('cost_category_id')
                ->map(function ($groupCosts) {
                    return [
                        'name' => $groupCosts->first()->costCategory?->name ?? 'بدون فئة',
                        'cash' => $groupCosts->sum('amount'),
                        'bank' => $groupCosts->sum('amount_bankak'),
                        'total' => $groupCosts->sum('amount') + $groupCosts->sum('amount_bankak'),
                    ];
                });

            $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
            $catHeaders = ['الفئة', 'نقداً', 'بنك/شبكة', 'الإجمالي'];
            $catWidths = [$pageWidth * 0.4, $pageWidth * 0.2, $pageWidth * 0.2, $pageWidth * 0.2];
            $catAligns = ['R', 'C', 'C', 'C'];
            
            $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 9);
            $pdf->DrawTableHeader($catHeaders, $catWidths, $catAligns);

            $pdf->SetFont($pdf->getDefaultFontFamily(), '', 9);
            foreach ($categoryTotals as $catTotal) {
                $pdf->DrawTableRow([
                    $catTotal['name'],
                    number_format($catTotal['cash'], 2),
                    number_format($catTotal['bank'], 2),
                    number_format($catTotal['total'], 2)
                ], $catWidths, $catAligns, $fill);
                $fill = !$fill;
            }
        }

        // --- Output PDF ---
        $pdfFileName = 'costs_report_' . date('Ymd_His') . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');
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
            
            $dailyTotalCosts = $costsOnThisDay->sum(fn($cost) => (float)$cost->amount + (float)$cost->amount_bankak);
            $dailyCashCosts = $costsOnThisDay->sum('amount');
            $dailyBankCosts = $costsOnThisDay->sum('amount_bankak');

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
            // These will be calculated at the end from summed totals
        }
        
        $grandTotals['net_total_income'] = $grandTotals['total_deposits'] - $grandTotals['total_costs_for_days_with_deposits'];
        $grandTotals['net_cash_flow'] = $grandTotals['total_cash_deposits'] - $allCostsForMonth->sum('amount'); // Total cash costs for month
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
            
            $depositsOnThisDay = $allDepositsForMonth->filter(fn ($d) => Carbon::parse($d->created_at)->isSameDay($date));
            $costsOnThisDay = $allCostsForMonth->filter(fn ($c) => Carbon::parse($c->created_at)->isSameDay($date));

            if ($depositsOnThisDay->isEmpty() && $costsOnThisDay->isEmpty() && !$request->input('show_empty_days', true)) { // Default to true for reports
                continue;
            }

            $dailyTotalDeposits = $depositsOnThisDay->sum('amount');
            $dailyCashDeposits = $depositsOnThisDay->where('is_bank', false)->sum('amount');
            $dailyBankDeposits = $depositsOnThisDay->where('is_bank', true)->sum('amount');
            
            $dailyCashCosts = $costsOnThisDay->sum('amount');
            $dailyBankCosts = $costsOnThisDay->sum('amount_bankak');
            $dailyTotalCosts = $dailyCashCosts + $dailyBankCosts;

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
            ];

            $grandTotals['total_deposits'] += $dailyTotalDeposits;
            $grandTotals['total_cash_deposits'] += $dailyCashDeposits;
            $grandTotals['total_bank_deposits'] += $dailyBankDeposits;
            $grandTotals['total_costs_for_days_with_activity'] += $dailyTotalCosts;
        }
        
        $grandTotals['net_total_income'] = $grandTotals['total_deposits'] - $grandTotals['total_costs_for_days_with_activity'];
        $grandTotals['net_cash_flow'] = $grandTotals['total_cash_deposits'] - $allCostsForMonth->sum('amount');
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

  
}
