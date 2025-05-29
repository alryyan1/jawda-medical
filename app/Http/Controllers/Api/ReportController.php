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
        $colWidths[count($colWidths)-1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($colWidths, 0, -1));

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
        $colWidths[count($colWidths)-1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($colWidths, 0, -1));

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
                $query->with(['childTest' => function($ctQuery) {
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
            'P', 'mm', 'A4', true, 'UTF-8', false
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
        $pdf->SetFillColor(255,255,255); // Reset fill color
        $pdf->Ln(1);

        $childColWidths = [60, 35, 25, 50, 0]; 
        // Child Test Results Table for this Main Test
        if ($labRequest->results->isNotEmpty()) {
            $pdf->SetFont($defaultFont, '', 9);
            $childHeaders = ['المكون الفرعي', 'النتيجة', 'الوحدة', 'النطاق الطبيعي', 'العلامات'];
            // Usable width = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right']
            // Make name wider, others roughly equal
            $childColWidths[count($childColWidths)-1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($childColWidths, 0, -1)) - $indent;

            $childAligns = ['R', 'C', 'C', 'C', 'C'];

            // Draw child test table header (optional, or just list them)
            $currentX = $pdf->GetX() + ($isRTL ? 0 : $indent); // Apply indent for LTR
            $pdf->SetX($currentX); // Apply indent
            $pdf->SetFont($defaultFont, 'B', 8);
            $pdf->SetFillColor(245, 245, 245);
            foreach($childHeaders as $i => $ch) {
                $pdf->Cell($childColWidths[$i], $lineHeight, $ch, 1, ($i == count($childHeaders)-1 ? 1:0), 'C', true);
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
                for($i = 0; $i < count($rowData); $i++) {
                    $numLines = $pdf->getNumLines((string)$rowData[$i], $childColWidths[$i]);
                    if ($numLines > $maxLines) $maxLines = $numLines;
                }
                if ($maxLines == 0) $maxLines = 1;
                $cellPadding = $pdf->getCellPaddings();
                $currentLineHeight = ($pdf->getFontSize() * $pdf->getCellHeightRatio() * 0.352777778);
                if($currentLineHeight < 3) $currentLineHeight = 3.5;
                $rowH = ($maxLines * $currentLineHeight) + $cellPadding['T'] + $cellPadding['B'] + 0.5;
                if($rowH < 5) $rowH = 5;

                $yBeforeCall = $pdf->GetY();
                if ($yBeforeCall + $rowH > ($pdf->getPageHeight() - $pdf->getBreakMargin())) {
                    $pdf->AddPage(); // TCPDF will call Header()
                    // Redraw child test table header on new page
                    $currentX = $pdf->GetX() + ($isRTL ? 0 : $indent);
                    $pdf->SetX($currentX);
                    $pdf->SetFont($defaultFont, 'B', 8);
                    foreach($childHeaders as $i => $ch) {
                        $pdf->Cell($childColWidths[$i], $lineHeight, $ch, 1, ($i == count($childHeaders)-1 ? 1:0), 'C', true);
                    }
                    $pdf->SetFont($defaultFont, '', 8);
                    $yBeforeCall = $pdf->GetY(); // Update Y after potential page break
                }
                $xPos = $currentX;
                for ($i = 0; $i < count($rowData); $i++) {
                    $align = $childAligns[$i] ?? 'R';
                    if(is_numeric(str_replace(['<','>'],'',$rowData[$i]))) $align = 'C';
                    if($i === 0) $align = ($isRTL ? 'R' : 'L'); // First column align

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
                $pdf->Line($pdf->GetX() + ($isRTL ? 0 : $indent), $pdf->GetY(), $pdf->GetPageWidth() - $pdf->getMargins()['right'], $pdf->GetY()); // Bottom line for row
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
        $colWidths[count($colWidths)-1] = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'] - array_sum(array_slice($colWidths, 0, -1));
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
    public function clinicReport(Request $request,DoctorShift $doctorShift)
    {
    

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
            'mm', 'A4', true, 'UTF-8', false
        );
        
        // Attempt to add Arial (ensure arial.ttf is in a TCPDF accessible font path or public_path if using that)
        // This path needs to be correct or TCPDF needs to find it in its font dirs.
        // $fontPath = public_path('fonts/arial.ttf'); // Example if in public/fonts
        // For TCPDF internal fonts like dejavusans, this is not needed.
        // If using a custom TTF font, ensure it's correctly added via TCPDF_FONTS::addTTFfont
        // For now, relying on MyCustomTCPDF's default font (dejavusans)
        $fontname = $pdf->getDefaultFontFamily(); // Use the default from your custom class
        $fontBold = $pdf->SetFont($fontname, 'B'); // Get bold variant if defaultFontBold is set in MyCustomTCPDF

        $pdf->AddPage();
        $page_width = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];

        // Header section in your PDF logic
        $pdf->SetFont($fontname, 'B', 16); // Slightly smaller than your 22
        // $pdf->Cell($page_width, 5, 'التقرير الخاص', 0, 1, 'C'); // Title is now in TCPDF Header
        // $pdf->Ln(5); // Space after title
        $pdf->SetFont($fontname, 'B', 10); // Smaller font for sub-headers

        $pdf->SetFillColor(220, 220, 220); // Light grey for headers
        $table_col_width_third = $page_width / 3; // For 3-column layout
        $table_col_width_sixth = $page_width / 6; // For 6-column layout

        // First header row
        $pdf->Cell($table_col_width_sixth, 7, 'التاريخ', 1, 0, 'C', 1);
        $pdf->Cell($table_col_width_sixth, 7, $doctorShift->start_time ? $doctorShift->start_time->format('Y/m/d') : '-', 1, 0, 'C');
        $pdf->Cell($table_col_width_sixth * 2, 7, ' ', 0, 0, 'C'); // Spacer
        $pdf->Cell($table_col_width_sixth, 7, 'المستخدم', 1, 0, 'C', 1);
        $pdf->Cell($table_col_width_sixth, 7, $doctorShift->user->username ?? '-', 1, 1, 'C'); // ln=1

        // Second header row
        $pdf->Cell($table_col_width_sixth, 7, 'الطبيب', 1, 0, 'C', 1);
        $pdf->MultiCell($table_col_width_sixth, 7, $doctorShift->doctor->name ?? '-', 1, 'C', false, 0, $pdf->GetX(), $pdf->GetY(), true, 0, false, true, 7,'M');
        $pdf->Cell($table_col_width_sixth * 2, 7, '', 0, 0, 'C'); // Spacer
        $pdf->Cell($table_col_width_sixth, 7, 'زمن فتح المناوبة', 1, 0, 'C', 1);
        $pdf->Cell($table_col_width_sixth, 7, $doctorShift->start_time ? $doctorShift->start_time->format('h:i A') : '-', 1, 1, 'C');
        $pdf->Ln(3);

        // Financial Summary Row
        $pdf->SetFont($fontname, 'B', 9);
        $sectionWidth = ($page_width / 3) - 5; // Approx width for each financial section
        
        $pdf->Cell($sectionWidth, 7, 'إجمالي المرضى: ' . $doctorShift->visits->where('only_lab',0)->count(), 1, 0, 'C');
        $pdf->Cell($sectionWidth, 7, 'استحقاق نقدي: ' . number_format($doctorShift->doctor_credit_cash(), 1), 1, 0, 'C');
        $pdf->Cell($sectionWidth, 7, 'استحقاق تأمين: ' . number_format($doctorShift->doctor_credit_company(), 1), 1, 1, 'C');
        $pdf->Ln(5);

        // Table for patient visits
        $pdf->SetFont($fontname, 'B', 9);
        // Adjust widths based on landscape and content
        $h_widths = [15, 55, 40, 25, 25, 25, 30, 0]; // ID, Name, Company, Total, Cash, Bank, Doc Share, Services
        $h_widths[count($h_widths)-1] = $page_width - array_sum(array_slice($h_widths, 0, -1));
        $h_aligns = ['C', 'R', 'R', 'C', 'C', 'C', 'C', 'R'];
        $headerTexts = ['رقم', 'اسم المريض', 'الشركة', 'إجمالي', 'نقداً', 'بنك', 'حصة الطبيب', 'الخدمات*'];
        
        $pdf->DrawTableHeader($headerTexts, $h_widths, $h_aligns); // Using your helper

        $pdf->SetFont($fontname, '', 8);
        $index = 1;
        $visits = $doctorShift->visits->filter(fn (DoctorVisit $visit) => $visit->only_lab == 0);
        
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
                number_format($doctorvisit->calculateTotalPaid() - $doctorvisit->calculateTotalBankPayments(), 1), // Cash
                number_format($doctorvisit->calculateTotalBankPayments(), 1), // Bank
                number_format($doctorShift->doctor->calculateVisitCredit($doctorvisit, $isCompanyPatient ? 'company' : 'cash'), 1),
                $doctorvisit->services_concatinated() // This will use MultiCell via DrawTableRow
            ];
            $pdf->DrawTableRow($rowData, $h_widths, $h_aligns, ($index % 2 != 0)); // Alternating fill
            
            $pdf->SetTextColor(0, 0, 0); // Reset text color
            $index++;
        }
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
        $pdf->Ln(2);

        // Footer Totals for the patient table
        $pdf->SetFont($fontname, 'B', 9);
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
            $pdf->SetFont($fontname, 'B', 14);
            $pdf->Cell($page_width, 10, 'مصروفات الخدمات للوردية', 0, 1, 'C');
            $pdf->Ln(2);

            $pdf->SetFont($fontname, 'B', 10);
            $cost_col_widths = [$page_width * 0.6, $page_width * 0.4];
            $cost_aligns = ['R', 'C'];
            $pdf->DrawTableHeader(['بيان مصروف الخدمة', 'الإجمالي'], $cost_col_widths, $cost_aligns);
            
            $pdf->SetFont($fontname, '', 9);
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
        $pdf->SetFont($fontname, 'B', 14);
        $pdf->Cell($page_width, 10, 'تفصيل تكاليف الخدمات للمرضى', 0, 1, 'C');
        $pdf->Ln(2);
        $pdf->SetFont($fontname, 'B', 9);
        $cost_detail_widths = [15, 50, 40, 25, 40, 0]; // ID, Name, Company, Total Services Value, Total Service Costs, Cost Names
        $cost_detail_widths[count($cost_detail_widths)-1] = $page_width - array_sum(array_slice($cost_detail_widths, 0, -1));
        $cost_detail_aligns = ['C','R','R','C','C','R'];
        $pdf->DrawTableHeader(['رقم','اسم المريض','الشركة','إجمالي الخدمات','إجمالي التكاليف','بيان التكاليف*'], $cost_detail_widths, $cost_detail_aligns);

        $pdf->SetFont($fontname, '', 8);
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
    public function allclinicsReportNew(Request $request)
    {
        $request->validate([
            'shift' => 'required|integer|exists:shifts,id', // General Shift ID
            'user' => 'nullable|integer|exists:users,id', // User ID for filtering collections
        ]);

        $shift = Shift::find($request->get('shift'));
        if (!$shift) {
            return response()->json(['error' => 'الوردية المحددة غير موجودة.'], 404);
        }

        // Get all DoctorShifts related to this general Shift
        $doctorShiftsQuery = DoctorShift::with([
            'user:id,username,name', 
            'doctor.specialist:id,name', // Eager load doctor and their specialist
            'visits.patient.company:id,name',
            'visits.requestedServices.service:id,name',
            'visits.labRequests.mainTest:id,main_test_name',
        ])->where('shift_id', $shift->id);

        // The original PDF logic seems to iterate through doctors in a shift, not users.
        // If the report is per doctor shift session:
        // If a specific doctor_shift_id was intended:
        // $doctorShiftForReport = DoctorShift::with([...])->find($request->get('specific_doctor_shift_id'));
        // For now, we will loop through all doctor shifts within the general shift.
        
        $doctor_shifts_for_report = $doctorShiftsQuery->get();

        if ($doctor_shifts_for_report->isEmpty() && !$request->has('user') /* Only error if no doctor shifts at all for the general shift */ ) {
             // If request had a user filter, it might be that this user didn't manage any doctor shift
        }


        // --- PDF Initialization ---
        $reportMainTitle = 'التقرير المالي العام للوردية';
        $filterCriteriaString = "وردية رقم: " . $shift->id . " | بتاريخ: " . Carbon::parse($shift->created_at)->format('Y/m/d');
        if($request->has('user') && $userForFilter = User::find($request->get('user'))){
            $filterCriteriaString .= " | للمستخدم: " . $userForFilter->name;
        }

        $pdf = new MyCustomTCPDF(
            $reportMainTitle,
            $filterCriteriaString,
            'P', 'mm', 'A4', true, 'UTF-8', false // Portrait for this summary
        );
        
        $fontname = $pdf->getDefaultFontFamily(); // Using font from MyCustomTCPDF

        // --- PAGE 1: Financial Summary (Collections by User, Expenses) ---
        $pdf->AddPage();
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        
        // Shift Information (already part of MyCustomTCPDF header, but can add more specifics)
        // $pdf->SetFont($fontname, 'B', 11); // ... etc. ...

        // --- Section 1: Collections by User ---
        $pdf->SetFont($fontname, 'B', 12);
        $pdf->Cell(0, 8, 'ملخص المتحصلات حسب المستخدم (لهذه الوردية العامة)', 0, 1, 'C');
        $pdf->Ln(1);

        // Iterate users who had payment activity in this shift
        // This requires user_deposited on LabRequest and a similar field on RequestedService
        $usersWithActivityIds = DoctorVisit::where('shift_id', $shift->id)
            ->with(['labRequests', 'requestedServices'])
            ->get()
            ->flatMap(function($visit) {
                $ids = [];
                foreach($visit->labRequests as $lr) if($lr->user_deposited) $ids[] = $lr->user_deposited;
                foreach($visit->requestedServices as $rs) if($rs->user_deposited) $ids[] = $rs->user_deposited; // Assuming user_deposited on RequestedService
                return $ids;
            })
            ->unique()
            ->filter();
            
        $usersToReport = User::whereIn('id', $usersWithActivityIds)->get();
        if ($request->has('user')) { // If specific user filter is applied
            $usersToReport = User::where('id', $request->get('user'))->get();
        }


        $userCollectionsPresented = false;
        if ($usersToReport->isNotEmpty()) {
            foreach ($usersToReport as $user) {
                // Use Shift model methods to get user-specific totals for THIS general shift
                $totalPaidForUser = $shift->paidLab($user->id) + $shift->totalPaidService($user->id);
                $totalBankForUser = $shift->bankakLab($user->id) + $shift->totalPaidServiceBank($user->id);
                $totalCashForUser = $totalPaidForUser - $totalBankForUser;

                // User specific costs
                $userTotalCosts = $shift->totalCost($user->id);
                $userBankCosts = $shift->totalCostBank($user->id);
                $userCashCosts = $userTotalCosts - $userBankCosts;

                $netBankForUser = $totalBankForUser - $userBankCosts;
                $netCashForUser = $totalCashForUser - $userCashCosts;

                if ($totalPaidForUser == 0 && $userTotalCosts == 0) continue; // Skip if no activity
                $userCollectionsPresented = true;

                $pdf->SetFont($fontname, 'B', 10);
                $pdf->Cell(0, 7, 'المستخدم: ' . ($user->name ?: $user->username), 'B', 1, 'R');
                $pdf->Ln(1);

                $pdf->SetFont($fontname, 'B', 9);
                $h_widths = [$pageWidth*0.25, $pageWidth*0.15, $pageWidth*0.15, $pageWidth*0.15, $pageWidth*0.15, $pageWidth*0.15];
                $h_aligns = ['R', 'C', 'C', 'C', 'C', 'C'];
                $pdf->DrawTableHeader(['البيان', 'إجمالي متحصل', 'بنك', 'نقدي', 'صافي بنك', 'صافي نقدي'], $h_widths, $h_aligns);
                
                $pdf->SetFont($fontname, '', 9);
                $dataRow = ['إجمالي الإيرادات', $totalPaidForUser, $totalBankForUser, $totalCashForUser, '-', '-']; //SAR is example
                $pdf->DrawTableRow($dataRow, $h_widths, $h_aligns);
                $dataRow = ['إجمالي المصروفات', $userTotalCosts, $userBankCosts, $userCashCosts, '-', '-'];
                $pdf->DrawTableRow($dataRow, $h_widths, $h_aligns, true);
                $dataRow = ['الصافي للمستخدم', '-', '-', '-', $netBankForUser, $netCashForUser];
                $pdf->DrawTableRow($dataRow, $h_widths, $h_aligns);
                $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
                $pdf->Ln(3);
            }
        }
        if (!$userCollectionsPresented) { /* ... no collections message ... */ }


        // --- Section 2: General Expenses for the Shift ---
        $pdf->SetFont($fontname, 'B', 12);
        $pdf->Cell(0, 8, 'ملخص المصروفات العامة للوردية', 0, 1, 'C');
        $pdf->Ln(1);
        $shiftCosts = $shift->costs()->with('costCategory')->get(); // From Shift model
        if ($shiftCosts->count() > 0) {
            $exp_h = ['الوصف', 'الفئة', 'المبلغ الكلي', 'نقدي', 'بنك/شبكة'];
            $exp_w = [$pageWidth*0.4, $pageWidth*0.2, $pageWidth*0.15, $pageWidth*0.125, $pageWidth*0.125];
            $exp_a = ['R','R','C','C','C'];
            $pdf->DrawTableHeader($exp_h, $exp_w, $exp_a);
            $pdf->SetFont($fontname, '', 9);
            $fillExp = false;
            foreach($shiftCosts as $c){
                $cash = (float)$c->amount - (float)$c->amount_bankak;
                $totalActualCost = (float)$c->amount + (float)$c->amount_bankak; // Should be $c->amount if it's the total
                $pdf->DrawTableRow([$c->description, $c->costCategory?->name ?? '-', $totalActualCost, $cash, $c->amount_bankak], $exp_w, $exp_a, $fillExp);
                $fillExp = !$fillExp;
            }
            $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
        } else { /* ... no expenses message ... */ }
        $pdf->Ln(5);


        // --- PAGE 2 (and onwards): Doctors' Dues per Doctor Shift session ---
        if ($doctor_shifts_for_report->isNotEmpty()) {
            $pdf->AddPage('L'); // Landscape for detailed doctor breakdown
            $pageWidthL = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right']; // Recalculate for landscape

            $pdf->SetFont($fontname, 'B', 12);
            $pdf->Cell(0, 8, 'تفصيل استحقاقات الأطباء للمناوبات خلال الوردية العامة', 0, 1, 'C');
            $pdf->Ln(2);

            foreach ($doctor_shifts_for_report as $doctor_shift) {
                if ($doctor_shift->visits->where('only_lab', 0)->isEmpty() && $doctor_shift->doctor_credit_cash() == 0 && $doctor_shift->doctor_credit_company() == 0) {
                    continue; // Skip doctor shifts with no relevant activity for this report part
                }

                $pdf->SetFont($fontname, 'B', 10);
                $pdf->Cell(0, 7, "الطبيب: " . $doctor_shift->doctor->name . " (مناوبة #" . $doctor_shift->id . ") | التخصص: " . ($doctor_shift->doctor->specialist->name ?? '-'), 'B', 1, 'R');
                $pdf->Ln(1);
                
                // Summary for this doctor's shift
                $pdf->SetFont($fontname, 'B', 9);
                $docSummaryWidth = $pageWidthL / 4;
                $pdf->Cell($docSummaryWidth, 6, 'إجمالي المرضى (عيادة): ' . $doctor_shift->visits->where('only_lab',0)->count(), 1, 0, 'C');
                $pdf->Cell($docSummaryWidth, 6, 'استحقاق نقدي: ' . $doctor_shift->doctor_credit_cash(), 1, 0, 'C');
                $pdf->Cell($docSummaryWidth, 6, 'استحقاق تأمين: ' . $doctor_shift->doctor_credit_company(), 1, 0, 'C');
                $totalDocCredit = $doctor_shift->doctor_credit_cash() + $doctor_shift->doctor_credit_company();
                $pdf->Cell($docSummaryWidth, 6, 'إجمالي الاستحقاق: ' . $totalDocCredit, 1, 1, 'C');
                $pdf->Ln(2);

                // Patient details table for this doctor's shift
                $pdf->SetFont($fontname, 'B', 8);
                $pat_h = ['م', 'اسم المريض', 'الشركة', 'إجمالي مدفوع', 'نقدي', 'بنك', 'حصة الطبيب', 'الخدمات*'];
                $pat_w = [10, 50, 35, 25, 25, 25, 30, $pageWidthL - (10+50+35+25+25+25+30)];
                $pat_a = ['C','R','R','C','C','C','C','R'];
                $pdf->DrawTableHeader($pat_h, $pat_w, $pat_a);
                
                $pdf->SetFont($fontname, '', 7); // Smaller font for details
                $patIndex = 1;
                $visitsForDoctor = $doctor_shift->visits->filter(fn (DoctorVisit $visit) => $visit->only_lab == 0);
                foreach ($visitsForDoctor as $dv) {
                    $isCompPat = !!$dv->patient?->company_id;
                    $rowData = [
                        $patIndex++, $dv->patient->name ?? '-', $dv->patient?->company?->name ?? '-',
                        $dv->calculateTotalPaid(),
                        $dv->calculateTotalPaid() - $dv->calculateTotalBankPayments(),
                        $dv->calculateTotalBankPayments(),
                        $doctor_shift->doctor->calculateVisitCredit($dv, $isCompPat ? 'company' : 'cash'),
                        $dv->services_concatinated()
                    ];
                    $pdf->DrawTableRow($rowData, $pat_w, $pat_a, ($patIndex % 2 == 0));
                }
                 $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
                $pdf->Ln(5); // Space after each doctor's section
            }
        } else {
            $pdf->SetFont($fontname, '', 10);
            $pdf->Cell(0, 7, 'لا توجد مناوبات أطباء مسجلة أو نشطة لهذه الوردية العامة.', 0, 1, 'C');
        }
        
        // --- Clinic Service Costs (General for the shift) ---
        // (This section might be better on Page 1 if it's not too long)
        $clinicServiceCosts = $shift->shiftClinicServiceCosts();
        if (!empty($clinicServiceCosts)) {
            if ($pdf->GetY() + (count($clinicServiceCosts) * 6) + 20 > $pdf->getPageHeight() - $pdf->getBreakMargin()) $pdf->AddPage('P'); else $pdf->Ln(3); // Add page if not enough space, or just add some space
            $pdf->SetFont($fontname, 'B', 12);
            $pdf->Cell(0, 8, 'مصروفات الخدمات العامة للوردية', 0, 1, 'C');
            // ... (Table for clinicServiceCosts using DrawTableHeader/Row - similar to general expenses on page 1) ...
        }


        // --- Final Summary on a new page or at the end ---
        // (Similar to the summary in your original PDF code)


        // Output
        $fileName = 'AllClinicsReport_Shift_' . $shift->id . '_' . now()->format('Ymd_His') . '.pdf';
        $pdfContent = $pdf->Output($fileName, 'S');
        return response($pdfContent, 200)
                  ->header('Content-Type', 'application/pdf')
                  ->header('Content-Disposition', "inline; filename=\"{$fileName}\"");
    }
}

