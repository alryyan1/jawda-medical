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
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'shift_id' => 'nullable|integer|exists:shifts,id', // General Shift ID
            'user_id_opened' => 'nullable|integer|exists:users,id',
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
       
   
        
        // Apply filters
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
            if($doc = Doctor::find($request->doctor_id)) $filterCriteria[] = "Doctor: ".$doc->name;
        }
        
        if ($request->filled('user_opened')) {
            $query->where('user_id', $request->user_opened);
             if($u = User::find($request->user_opened)) $filterCriteria[] = "Opened By: ".$u->name;
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
        if($request->filled('date_from')){
            $query->where('doctor_shifts.created_at', $request->date_from);
        }
        if($request->filled('date_to')){
            $query->where('doctor_shifts.created_at', $request->date_to);
        }

        // Execute query with sorting
        $doctorShifts = $query->join('doctors', 'doctor_shifts.doctor_id', '=', 'doctors.id')
                               ->select('doctor_shifts.*')
                               ->orderBy('doctors.name', 'asc')
                               ->get();

        if ($doctorShifts->isEmpty()) {
            throw new \Exception('No data found for the selected filters.');
        }
        
        // For the user summary, re-query WITHOUT the user_opened filter so all users appear.
        $userOpenedId = $request->filled('user_opened') ? (int) $request->user_opened : null;
        if ($userOpenedId !== null) {
            $summaryQuery = DoctorShift::with([
                'user:id,name',
                'visits.patient.company',
                'visits.requestedServices.service',
                'visits.patientLabRequests.mainTest',
                'doctor',
            ]);
            if ($request->filled('shift_id'))       $summaryQuery->where('shift_id', $request->shift_id);
            if ($request->filled('doctor_id'))       $summaryQuery->where('doctor_id', $request->doctor_id);
            if ($request->filled('doctor_name_search')) {
                $st = $request->doctor_name_search;
                $summaryQuery->whereHas('doctor', fn($q) => $q->where('name', 'LIKE', "%{$st}%"));
            }
            if ($request->has('status') && $request->status !== 'all') $summaryQuery->where('status', (bool)$request->status);
            if ($request->filled('date_from')) $summaryQuery->where('doctor_shifts.created_at', $request->date_from);
            if ($request->filled('date_to'))   $summaryQuery->where('doctor_shifts.created_at', $request->date_to);
            $allUsersShifts = $summaryQuery->get();
        } else {
            $allUsersShifts = $doctorShifts; // already all users, no user_opened filter was applied
        }

        return $this->generatePdf($doctorShifts, $filterCriteria, $userOpenedId, $allUsersShifts);
    }

    /**
     * Generate the PDF content
     *
     * @param \Illuminate\Database\Eloquent\Collection $doctorShifts
     * @param array $filterCriteria
     * @return string
     */
    private function generatePdf($doctorShifts, array $filterCriteria, ?int $userOpenedId = null, $allUsersShifts = null): string
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

        // User summary table — only when filtered by a specific opener
        if ($userOpenedId !== null) {
            $this->renderUserSummaryTable($pdf, $allUsersShifts ?? $doctorShifts, $isRTL);
        }

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
        $headers = ['Specialist', 'Doctor', 'Patients', 'Total Paid', 'Total Entl.', 'Cash Entl.', 'Ins. Entl.', 'Net'];

        if ($isRTL) {
            $headers = ['التخصص', 'الطبيب', 'عدد المرضى', 'إجمالي المدفوع', 'إجمالي المستحق', 'استحقاق (كاش)', 'استحقاق (تأمين)', 'الصافي'];
        }

        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [45, 60, 25, 35, 35, 35, 20, 20];
        $colWidths[count($colWidths) - 1] = $pageUsableWidth - \array_sum(\array_slice($colWidths, 0, -1));
        $alignments = ['C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'];

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
        $grandTotals = ['patients' => 0, 'total_paid' => 0, 'total_entl' => 0, 'cash_entl' => 0, 'ins_entl' => 0, 'net' => 0];

        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [45, 60, 25, 35, 35, 35, 20, 20];
        $colWidths[count($colWidths) - 1] = $pageUsableWidth - \array_sum(\array_slice($colWidths, 0, -1));

        $pdf->SetFillColor(246, 248, 252);

        foreach ($doctorShifts as $ds) {
            $cashEntl    = $ds->doctor_credit_cash();
            $insEntl     = $ds->doctor_credit_company();
            $staticWage  = (!$ds->status && $ds->doctor) ? (float) $ds->doctor->static_wage : 0;
            $totalEntl   = $cashEntl + $insEntl + $staticWage;
            
            // Total paid is now net of returns for consistency
            $totalPaid     = $ds->total_paid_services() - $ds->total_returns();
            $net           = $ds->hospital_credit();
            $patientsCount = $ds->visits->count();

            $grandTotals['patients']   += $patientsCount;
            $grandTotals['total_paid'] += $totalPaid;
            $grandTotals['net']        += $net;
            $grandTotals['total_entl'] += $totalEntl;
            $grandTotals['cash_entl']  += $cashEntl;
            $grandTotals['ins_entl']   += $insEntl;

            $rowData = [
                $ds->doctor?->specialist?->name ?? '-',
                $ds->doctor?->name ?? 'N/A',
                $patientsCount,
                number_format($totalPaid, 2),
                number_format($totalEntl, 2),
                number_format($cashEntl, 2),
                number_format($insEntl, 2),
                number_format($net, 2),
            ];

            foreach ($rowData as $i => $cell) {
                $pdf->Cell($colWidths[$i], 7, $cell, 1, 0, 'C', $fill);
            }
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
            '',
            $grandTotals['patients'],
            number_format($grandTotals['total_paid'], 2),
            number_format($grandTotals['total_entl'], 2),
            number_format($grandTotals['cash_entl'], 2),
            number_format($grandTotals['ins_entl'], 2),
            number_format($grandTotals['net'], 2),
        ];

        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [45, 60, 25, 35, 35, 35, 20, 36];
        $colWidths[count($colWidths) - 1] = $pageUsableWidth - \array_sum(\array_slice($colWidths, 0, -1));

        $summaryAligns = ['C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'];
        $pdf->DrawTableRow($summaryRowData, $colWidths, $summaryAligns, true, 7);
    }

    /**
     * Render per-user summary as cards: one card per user, 2 cards per row.
     * Each card shows Income / Costs / Net broken down by Total / Cash / Bank.
     */
    private function renderUserSummaryTable(MyCustomTCPDF $pdf, $doctorShifts, bool $isRTL): void
    {
        // ── 1. Aggregate data ────────────────────────────────────────────────
        $byUser = [];
        foreach ($doctorShifts as $ds) {
            $userId   = $ds->user_id ?? 0;
            $userName = $ds->user->name ?? $ds->user->username ?? "#{$userId}";

            if (!isset($byUser[$userId])) {
                $byUser[$userId] = [
                    'name'        => $userName,
                    'income'      => 0,
                    'income_cash' => 0,
                    'income_bank' => 0,
                    'costs'       => 0,
                    'costs_cash'  => 0,
                    'costs_bank'  => 0,
                ];
            }

            $income      = $ds->total_paid_services();
            $income_bank = $ds->total_bank();
            $income_cash = $income - $income_bank;
            $entl_cash   = $ds->doctor_credit_cash();
            $entl_ins    = $ds->doctor_credit_company();
            $staticWage  = (!$ds->status && $ds->doctor) ? (float) $ds->doctor->static_wage : 0;
            $costs       = $entl_cash + $entl_ins + $staticWage;
            $costs_cash  = $entl_cash;
            $costs_bank  = $entl_ins + $staticWage;

            $byUser[$userId]['income']      += $income;
            $byUser[$userId]['income_cash'] += $income_cash;
            $byUser[$userId]['income_bank'] += $income_bank;
            $byUser[$userId]['costs']       += $costs;
            $byUser[$userId]['costs_cash']  += $costs_cash;
            $byUser[$userId]['costs_bank']  += $costs_bank;
        }

        // ── 2. Section heading ───────────────────────────────────────────────
        $pdf->Ln(8);
        $pdf->SetFont($pdf->getDefaultFontFamily(), 'B', 13);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell(0, 8, $isRTL ? 'ملخص الموظفين' : 'User Summary', 0, 1, 'C');
        $pdf->Ln(3);

        // ── 3. Card layout constants ─────────────────────────────────────────
        $lMargin        = $pdf->getMargins()['left'];
        $rMargin        = $pdf->getMargins()['right'];
        $pageW          = $pdf->getPageWidth();
        $usableW        = $pageW - $lMargin - $rMargin;
        $cardsPerRow    = 2;
        $cardGap        = 6;  // mm between cards
        $cardW          = ($usableW - ($cardsPerRow - 1) * $cardGap) / $cardsPerRow;

        // Inside each card: 4 columns — label | total | cash | bank
        $labelW  = $cardW * 0.34;
        $valW    = ($cardW - $labelW) / 3;

        $cardHeaderH = 8;   // name bar
        $subHeaderH  = 6;   // column labels row
        $rowH        = 6;   // data row height
        $cardH       = $cardHeaderH + $subHeaderH + 3 * $rowH + 2; // total card height

        // label strings
        $lblTotal = $isRTL ? 'الإجمالي' : 'Total';
        $lblCash  = $isRTL ? 'كاش'      : 'Cash';
        $lblBank  = $isRTL ? 'بنك'       : 'Bank';
        $lblIncome = $isRTL ? 'الإيراد'   : 'Income';
        $lblCosts  = $isRTL ? 'المصروف'   : 'Costs';
        $lblNet    = $isRTL ? 'الصافي'    : 'Net';

        // colours
        $hdrBg    = [41,  128, 185]; // blue header
        $subBg    = [235, 245, 251]; // light-blue sub-header
        $rowBg1   = [255, 255, 255];
        $rowBg2   = [248, 250, 253];
        $netGreen = [39,  174,  96];
        $netRed   = [192,  57,  43];

        $font = $pdf->getDefaultFontFamily();

        // ── 4. Draw cards ────────────────────────────────────────────────────
        $col     = 0;
        $rowTopY = $pdf->GetY();

        foreach ($byUser as $row) {
            $net      = $row['income']      - $row['costs'];
            $net_cash = $row['income_cash'] - $row['costs_cash'];
            $net_bank = $row['income_bank'] - $row['costs_bank'];

            // card X depends on column (RTL: first card is on the right)
            $cardX = $isRTL
                ? $pageW - $rMargin - ($col + 1) * $cardW - $col * $cardGap
                : $lMargin + $col * ($cardW + $cardGap);
            $cardY = $rowTopY;

            // page-break check
            if ($cardY + $cardH > $pdf->getPageHeight() - $pdf->getBreakMargin()) {
                $pdf->AddPage('L');
                $rowTopY = $pdf->GetY();
                $cardY   = $rowTopY;
                $col     = 0;
                $cardX   = $lMargin;
            }

            // ── card outer border ──
            $pdf->SetDrawColor(180, 200, 220);
            $pdf->SetLineWidth(0.3);
            $pdf->Rect($cardX, $cardY, $cardW, $cardH, 'D');

            // ── name header bar ──
            $pdf->SetFillColor($hdrBg[0], $hdrBg[1], $hdrBg[2]);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont($font, 'B', 10);
            $pdf->SetXY($cardX, $cardY);
            $pdf->MultiCell($cardW, $cardHeaderH, $row['name'], 0, 'C', true, 1, $cardX, $cardY, true, 0, false, true, $cardHeaderH, 'M');

            // ── column sub-header (Total | Cash | Bank) ──
            $subY = $cardY + $cardHeaderH;
            $pdf->SetFillColor($subBg[0], $subBg[1], $subBg[2]);
            $pdf->SetTextColor(60, 100, 140);
            $pdf->SetFont($font, 'B', 8);
            $pdf->SetXY($cardX, $subY);
            $pdf->Cell($labelW, $subHeaderH, '', 0, 0, 'C', true);
            $pdf->Cell($valW,   $subHeaderH, $lblTotal, 1, 0, 'C', true);
            $pdf->Cell($valW,   $subHeaderH, $lblCash,  1, 0, 'C', true);
            $pdf->Cell($valW,   $subHeaderH, $lblBank,  1, 1, 'C', true);

            // ── data rows: Income / Costs / Net ──
            $dataRows = [
                ['label' => $lblIncome, 'total' => $row['income'],      'cash' => $row['income_cash'],  'bank' => $row['income_bank'],  'color' => null,     'bg' => $rowBg1],
                ['label' => $lblCosts,  'total' => $row['costs'],       'cash' => $row['costs_cash'],   'bank' => $row['costs_bank'],   'color' => null,     'bg' => $rowBg2],
                ['label' => $lblNet,    'total' => $net,                'cash' => $net_cash,            'bank' => $net_bank,            'color' => $net >= 0 ? $netGreen : $netRed, 'bg' => $rowBg1],
            ];

            $dataY = $subY + $subHeaderH;
            foreach ($dataRows as $dr) {
                [$r, $g, $b] = $dr['bg'];
                $pdf->SetFillColor($r, $g, $b);
                $pdf->SetFont($font, 'B', 8);
                $pdf->SetTextColor(60, 60, 60);
                $pdf->SetXY($cardX, $dataY);
                $pdf->Cell($labelW, $rowH, $dr['label'], 1, 0, 'C', true);

                $pdf->SetFont($font, '', 8);
                if ($dr['color']) {
                    [$cr, $cg, $cb] = $dr['color'];
                    $pdf->SetTextColor($cr, $cg, $cb);
                } else {
                    $pdf->SetTextColor(0, 0, 0);
                }
                $pdf->Cell($valW, $rowH, number_format($dr['total'], 2), 1, 0, 'C', true);
                $pdf->Cell($valW, $rowH, number_format($dr['cash'],  2), 1, 0, 'C', true);
                $pdf->Cell($valW, $rowH, number_format($dr['bank'],  2), 1, 1, 'C', true);
                $dataY += $rowH;
            }

            // ── advance column / row ──
            $col++;
            if ($col >= $cardsPerRow) {
                $col      = 0;
                $rowTopY += $cardH + $cardGap;
                $pdf->SetY($rowTopY);
            }
        }

        // ensure cursor is below last row of cards
        if ($col > 0) {
            $pdf->SetY($rowTopY + $cardH + $cardGap);
        }

        $pdf->SetTextColor(0, 0, 0);
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
