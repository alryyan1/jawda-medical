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
use App\Models\Doctor;
use App\Models\DoctorShift;
use App\Models\Setting;
use App\Models\Shift;
use App\Services\Pdf\MyCustomTCPDF;
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
            $pdf->MultiCell($colWidths[1], $rowMaxHeight, $ds->generalShift->name ?? ($ds->shift_id ? '#'.$ds->shift_id : '-'), $border, 'C', $fillRow, $ln, '', '', true, 0, false, true, $rowMaxHeight, 'M');
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
        $pdf->Output($pdfFileName, 'I'); // 'I' for inline display in browser
        exit; // Important to prevent Laravel from sending further output
    }
}



