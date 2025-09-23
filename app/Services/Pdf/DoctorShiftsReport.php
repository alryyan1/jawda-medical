<?php

namespace App\Services\Pdf;

use App\Models\DoctorShift;
use App\Models\Doctor;
use App\Models\User;
use App\Models\Shift;
use App\Services\Pdf\MyCustomTCPDF;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DoctorShiftsReport
{
    /**
     * Generate doctor shifts PDF report.
     *
     * @param Request $request
     * @return string PDF content
     */
    public function generate(Request $request): string
    {
        // Validate request parameters
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'status' => 'nullable|in:0,1,all', // 0 for closed, 1 for open
            'shift_id' => 'nullable|integer|exists:shifts,id', // General Shift ID
            'user_id_opened' => 'nullable|integer|exists:users,id',
            'doctor_name_search' => 'nullable|string|max:255',
        ]);

        // Build query with relationships
        $query = DoctorShift::with([
            'doctor.specialist:id,name',
            'user:id,name', // User who opened DoctorShift
            'generalShift:id',
            // Relations for entitlement calculations
            'visits.patient.company',
            'visits.requestedServices.service',
            'visits.patientLabRequests.mainTest',
        ]);

        $filterCriteria = [];
       
        // Apply date filter
        $query->whereRaw('Date(doctor_shifts.created_at) between ? and ?', [$request->date_from, $request->date_to]);
        
        // Apply filters
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
            if($doc = Doctor::find($request->doctor_id)) $filterCriteria[] = "Doctor: ".$doc->name;
        }
        
        if ($request->filled('user_id_opened')) {
            $query->where('user_id', $request->user_id_opened);
             if($u = User::find($request->user_id_opened)) $filterCriteria[] = "Opened By: ".$u->name;
        }
        
        if ($request->filled('doctor_name_search')) {
            $searchTerm = $request->doctor_name_search;
            $query->whereHas('doctor', fn($q) => $q->where('name', 'LIKE', "%{$searchTerm}%"));
            $filterCriteria[] = "Search: ".$searchTerm;
        }
        
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', (bool)$request->status);
            $filterCriteria[] = "Status: " . ((bool)$request->status ? 'Open' : 'Closed');
        }
        
        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
            if($gs = Shift::find($request->shift_id)) $filterCriteria[] = "Gen. Shift: #".($gs->name ?? $gs->id);
        }

        // Execute query with sorting
        $doctorShifts = $query->join('doctors', 'doctor_shifts.doctor_id', '=', 'doctors.id')
                               ->select('doctor_shifts.*')
                               ->orderBy('doctors.name', 'asc')
                               ->get();

        if ($doctorShifts->isEmpty()) {
            throw new \Exception('No data found for the selected filters.');
        }
        
        // Generate PDF
        return $this->generatePdf($doctorShifts, $filterCriteria);
    }

    /**
     * Generate the PDF content
     *
     * @param \Illuminate\Database\Eloquent\Collection $doctorShifts
     * @param array $filterCriteria
     * @return string
     */
    private function generatePdf($doctorShifts, array $filterCriteria): string
    {
        $reportTitle = 'Doctor Shifts Report';
        $filterCriteriaString = !empty($filterCriteria) ? "Filters: " . implode(' | ', $filterCriteria) : "All Shifts";

        $pdf = new MyCustomTCPDF('', null, 'L', 'mm', 'A4', true, 'utf-8', false, false, $filterCriteriaString);
        $pdf->AddPage();
        $defaultFont = $pdf->getDefaultFontFamily();
        $isRTL = $pdf->getRTL();

        // Add report header with better styling
        $this->addReportHeader($pdf, $isRTL);

        // Set up table structure
        $this->setupTable($pdf, $defaultFont, $isRTL);

        // Render data rows
        $grandTotals = $this->renderDataRows($pdf, $doctorShifts, $isRTL);

        // Render grand totals
        $this->renderGrandTotals($pdf, $grandTotals, $isRTL);

        // Add report footer
        $this->addReportFooter($pdf, $doctorShifts->count(), $isRTL);

        // Generate PDF content
        $pdfFileName = 'DoctorShifts_Report_' . date('Ymd_His') . '.pdf';
        return $pdf->Output($pdfFileName, 'S');
    }

    /**
     * Setup table headers and structure
     *
     * @param MyCustomTCPDF $pdf
     * @param string $defaultFont
     * @param bool $isRTL
     */
    private function setupTable(MyCustomTCPDF $pdf, string $defaultFont, bool $isRTL): void
    {
        // Table Header - New order: Specialist, Doctor, Total Entitlement, Cash Entitlement, Insurance Entitlement, Employee
        $headers = ['Specialist', 'Doctor', 'Total Entl.', 'Cash Entl.', 'Ins. Entl.', 'Employee'];
        
        // Arabic table header
        if($isRTL){
            $headers = ['التخصص', 'الطبيب', 'إجمالي المستحق', 'استحقاق (كاش)', 'استحقاق (تأمين)', 'الموظف'];
        }
        
        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        // Adjusted column widths for better UI/UX: Specialist, Doctor, Total, Cash, Insurance, Employee
        $colWidths = [50, 70, 45, 45, 45, 50];
        $colWidths[count($colWidths)-1] = $pageUsableWidth - array_sum(array_slice($colWidths,0,-1));
        // Align text: names left, numbers right
        $alignments = ['C', 'C', 'C', 'C', 'C', 'C'];
        
        $pdf->SetTableDefinition($headers, $colWidths, $alignments);
        $pdf->DrawTableHeader(null, null, null, 6);
    }

    /**
     * Render data rows
     *
     * @param MyCustomTCPDF $pdf
     * @param \Illuminate\Database\Eloquent\Collection $doctorShifts
     * @param bool $isRTL
     * @return array
     */
    private function renderDataRows(MyCustomTCPDF $pdf, $doctorShifts, bool $isRTL): array
    {
        // Slightly larger font for readability
        $pdf->SetFont($pdf->getDefaultFontFamily(), '', 12);
        $fill = false;
        $grandTotals = ['total_entl' => 0, 'cash_entl' => 0, 'ins_entl' => 0];

        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [50, 70, 45, 45, 45, 50];
        $colWidths[count($colWidths)-1] = $pageUsableWidth - array_sum(array_slice($colWidths,0,-1));

        // Zebra striping
        $pdf->SetFillColor(246, 248, 252);

        foreach ($doctorShifts as $ds) {
            $cashEntl = $ds->doctor_credit_cash();
            $insEntl = $ds->doctor_credit_company();
            $staticWage = (!$ds->status && $ds->doctor) ? (float)$ds->doctor->static_wage : 0;
            $totalEntl = $cashEntl + $insEntl + $staticWage;

            $grandTotals['total_entl'] += $totalEntl;
            $grandTotals['cash_entl'] += $cashEntl;
            $grandTotals['ins_entl'] += $insEntl;

            // New column order: Specialist, Doctor, Total Entitlement, Cash Entitlement, Insurance Entitlement, Employee
            // Resolve user name; fallback to '-' if null or empty
            $employeeName = '-';
                $employeeName = $ds->user->username ?? User::find($ds->user_id)->username;
            

            $rowData = [
                $ds->doctor?->specialist?->name ?? '-',
                $ds->doctor?->name ?? 'N/A',
                number_format($totalEntl, 2),
                number_format($cashEntl, 2),
                number_format($insEntl, 2),
                $employeeName,
            ];
            
            // Align: L, L, R, R, R, L
            $pdf->Cell($colWidths[0], 7, $rowData[0], 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[1], 7, $rowData[1], 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[2], 7, $rowData[2], 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[3], 7, $rowData[3], 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[4], 7, $rowData[4], 1, 0, 'C', $fill);
            $pdf->Cell($colWidths[5], 7, $rowData[5], 1, 0, 'C', $fill);
            $pdf->Ln(7);
            $fill = !$fill;
        }

        return $grandTotals;
    }

    /**
     * Render grand totals row
     *
     * @param MyCustomTCPDF $pdf
     * @param array $grandTotals
     * @param bool $isRTL
     */
    private function renderGrandTotals(MyCustomTCPDF $pdf, array $grandTotals, bool $isRTL): void
    {
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());
        
        // Grand Totals Row - Updated for new column structure
        $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 14);
        $summaryRowData = [
            ($isRTL ? 'الإجمالي العام' : 'Grand Total:'), 
            '', // Doctor column
            number_format($grandTotals['total_entl'], 2),
            number_format($grandTotals['cash_entl'], 2),
            number_format($grandTotals['ins_entl'], 2),
            '' // Employee column
        ];
        
        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [50, 70, 45, 45, 45, 50];
        $colWidths[count($colWidths)-1] = $pageUsableWidth - array_sum(array_slice($colWidths,0,-1));
        
        // Alignments for summary, ensure it matches number of cells in $summaryRowData
        $summaryAligns = ['C', 'C', 'C', 'C', 'C', 'C'];
        $pdf->DrawTableRow($summaryRowData, $colWidths, $summaryAligns, true, 7);
    }

    /**
     * Add report header with better styling
     *
     * @param MyCustomTCPDF $pdf
     * @param bool $isRTL
     */
    private function addReportHeader(MyCustomTCPDF $pdf, bool $isRTL): void
    {
        // Larger title for better readability
        $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 18);
        $pdf->SetTextColor(0, 0, 0);
        
        // Main title
        $title = $isRTL ? 'تقرير مناوبات الأطباء' : 'Doctor Shifts Report';
        $pdf->Cell(0, 12, $title, 0, 1, 'C');
        
        // Date and time
        $pdf->SetFont($pdf->getDefaultFontFamily(), '', 11);
        $dateTime = $isRTL ? 'تاريخ التقرير: ' . date('Y-m-d H:i') : 'Report Date: ' . date('Y-m-d H:i');
        $pdf->Cell(0, 6, $dateTime, 0, 1, 'C');
        
        $pdf->Ln(5);
    }

    /**
     * Add report footer with summary information
     *
     * @param MyCustomTCPDF $pdf
     * @param int $totalRecords
     * @param bool $isRTL
     */
    private function addReportFooter(MyCustomTCPDF $pdf, int $totalRecords, bool $isRTL): void
    {
        $pdf->Ln(10);
        
        // Summary line
        $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 12);
        $summary = $isRTL ? "إجمالي عدد المناوبات: {$totalRecords}" : "Total Shifts: {$totalRecords}";
        $pdf->Cell(0, 6, $summary, 0, 1, 'C');
        
        // Generated timestamp
        $pdf->SetFont($pdf->getDefaultFontFamily(), '', 8);
        $generated = $isRTL ? 'تم إنشاء التقرير في: ' . date('Y-m-d H:i:s') : 'Generated on: ' . date('Y-m-d H:i:s');
        $pdf->Cell(0, 4, $generated, 0, 1, 'C');
    }
}
