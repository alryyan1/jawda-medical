<?php

namespace App\Services\Pdf;

use Carbon\Carbon;
use Illuminate\Http\Request;

class LabGeneralReport extends MyCustomTCPDF
{
    protected $results;
    protected $userRevenues;
    protected Request $request;

    public function __construct($results, Request $request, $userRevenues)
    {
        // Provide spaces as the logo parameter so it relies on default headers if available.
        parent::__construct('  ');

        $this->results = $results;
        $this->request = $request;
        $this->userRevenues = $userRevenues;

        $this->SetCreator('Jawda Medical System');
        $this->SetAuthor('Jawda Medical System');
        $this->SetTitle('تقرير المختبر العام');
        $this->SetSubject('تقرير المختبر العام');
        $this->isLab = true; // Enable footer with page numbers

        // Set margins
        $this->SetMargins(15, 20, 15);
        $this->SetAutoPageBreak(true, 15); // compact
    }

    public function generate(): string
    {
        $request = $this->request;
        $results = $this->results;

        $this->AddPage('L', 'A4');
        $availableWidth = 267;

        $this->SetFont('arial', '', 10);
        $this->setAutoPageBreak(true, 25);
        
        $this->SetFont('arial', 'B', 18);
        $this->SetTextColor(41, 98, 255); // Professional blue
        $this->Cell(0, 10, 'تقرير المختبر العام', 0, 1, 'C');
        
        $this->SetDrawColor(220, 220, 220);
        $this->SetLineWidth(0.2);
        $this->Line(15, $this->GetY(), 282, $this->GetY());
        $this->Ln(4);

        $this->SetFont('arial', '', 10);
        $this->SetTextColor(80, 80, 80);
        
        $startTime = $request->get('start_time', '00:00');
        $endTime = $request->get('end_time', '23:59');

        if ($request->filled('date_from') && $request->filled('date_to')) {
            $this->Cell(0, 6, 'فترة التقرير: من ' . $request->date_from . ' ' . $startTime . ' إلى ' . $request->date_to . ' ' . $endTime, 0, 1, 'R');
        } elseif ($request->filled('date_from')) {
            $this->Cell(0, 6, 'من تاريخ: ' . $request->date_from . ' ' . $startTime, 0, 1, 'R');
        } elseif ($request->filled('date_to')) {
            $this->Cell(0, 6, 'إلى تاريخ: ' . $request->date_to . ' ' . $endTime, 0, 1, 'R');
        }

        if ($request->filled('shift_id')) {
            $this->Cell(0, 6, 'المناوبة: ' . $request->shift_id, 0, 1, 'R');
        }
        
        $this->SetFont('arial', 'I', 9);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 6, 'تم إنشاء التقرير في: ' . Carbon::now()->format('Y-m-d H:i:s'), 0, 1, 'R');
        $this->Ln(6);

        // Render tables
        $this->renderUserRevenueSection($availableWidth);
        $this->AddPage();
        $this->renderPatientsTable($availableWidth, $results);

        $this->Ln(8);
        $this->AddPage('P', 'A4'); // For summary, portrait is nicer, or stick with L for consistency. Let's do L.
        $this->renderSummarySection($results);

        return $this->Output('lab_general_report_' . date('Y-m-d_H-i-s') . '.pdf', 'S');
    }

    protected function renderUserRevenueSection(float $availableWidth): void
    {
        $this->SetFont('arial', 'B', 14);
        $this->SetTextColor(41, 98, 255);
        $this->Cell(0, 8, 'إيراد حسب المستخدم', 0, 1, 'R');
        $this->Ln(2);

        $this->SetFont('arial', 'B', 10);
        $this->SetFillColor(240, 244, 248);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(220, 220, 220); // Border color
        
        $userColWidths = [
            $availableWidth * 0.25,
            $availableWidth * 0.25,
            $availableWidth * 0.20,
            $availableWidth * 0.15,
            $availableWidth * 0.15
        ];
        $userHeaders = ['اسم المستخدم', 'إجمالي المدفوع', 'إجمالي التخفيض', 'إجمالي كاش', 'إجمالي بنك'];
        
        foreach ($userColWidths as $i => $w) {
            $this->Cell($w, 8, $userHeaders[$i], 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFont('arial', '', 10);
        
        $totalUserPaid = 0;
        $totalUserDiscount = 0;
        $totalUserCash = 0;
        $totalUserBank = 0;

        foreach ($this->userRevenues as $index => $userRevenue) {
            $totalUserPaid += $userRevenue->total_paid;
            $totalUserDiscount += $userRevenue->total_discount;
            $totalUserCash += $userRevenue->total_cash;
            $totalUserBank += $userRevenue->total_bank;

            $fill = ($index % 2 == 0) ? [248, 249, 250] : [255, 255, 255];
            $this->SetFillColorArray($fill);
            $this->SetTextColor(0, 0, 0);

            $this->Cell($userColWidths[0], 8, $userRevenue->user_name, 1, 0, 'C', true);
            $this->Cell($userColWidths[1], 8, number_format($userRevenue->total_paid, 2), 1, 0, 'C', true);
            $this->Cell($userColWidths[2], 8, number_format($userRevenue->total_discount, 2), 1, 0, 'C', true);
            $this->Cell($userColWidths[3], 8, number_format($userRevenue->total_cash, 2), 1, 0, 'C', true);
            
            if ($userRevenue->total_bank > 0) {
                $this->SetTextColor(220, 20, 60);
            }
            $this->Cell($userColWidths[4], 8, number_format($userRevenue->total_bank, 2), 1, 0, 'C', true);
            $this->Ln();
        }

        // totals row matched to #3498db (52, 152, 219)
        $this->SetFont('arial', 'B', 10);
        $this->SetFillColor(52, 152, 219);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(41, 128, 185);
        
        $this->Cell($userColWidths[0], 9, 'الإجمالي', 1, 0, 'C', true);
        $this->Cell($userColWidths[1], 9, number_format($totalUserPaid, 2), 1, 0, 'C', true);
        $this->Cell($userColWidths[2], 9, number_format($totalUserDiscount, 2), 1, 0, 'C', true);
        $this->Cell($userColWidths[3], 9, number_format($totalUserCash, 2), 1, 0, 'C', true);
        $this->Cell($userColWidths[4], 9, number_format($totalUserBank, 2), 1, 0, 'C', true);
        $this->Ln();
    }

    protected function renderPatientsTable(float $availableWidth, $results): void
    {
        $this->SetFont('arial', 'B', 14);
        $this->SetTextColor(41, 98, 255);
        $this->Cell(0, 8, 'تفاصيل المرضى', 0, 1, 'R');
        $this->Ln(2);
        
        $this->SetFont('arial', 'B', 9);
        $this->SetFillColor(240, 244, 248);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(220, 220, 220);

        $headers = [
            'رقم الزيارة', 'اسم المريض', 'الطبيب', 'إجمالي المبلغ', 'المدفوع', 'الخصم', 'المبلغ البنك', 'الشركة', 'التحاليل'
        ];
        $colWidths = [
            $availableWidth * 0.08,
            $availableWidth * 0.15,
            $availableWidth * 0.12,
            $availableWidth * 0.09,
            $availableWidth * 0.09,
            $availableWidth * 0.08,
            $availableWidth * 0.09,
            $availableWidth * 0.12,
            $availableWidth * 0.18
        ];

        // Ensure widths add up exactly.
        $totalWidth = array_sum($colWidths);
        if (abs($totalWidth - $availableWidth) > 0.1) {
            $colWidths[8] += ($availableWidth - $totalWidth);
        }

        foreach ($colWidths as $i => $w) {
            $this->Cell($w, 8, $headers[$i], 1, 0, 'C', true);
        }
        $this->Ln();

        $this->SetFont('arial', '', 9);

        $totalLabAmount = 0;
        $totalPaid = 0;
        $totalDiscount = 0;
        $totalBank = 0;

        foreach ($results as $index => $patient) {
            $totalLabAmount += $patient->total_lab_amount;
            $totalPaid += $patient->total_paid_for_lab;
            $totalDiscount += $patient->discount;
            $totalBank += $patient->total_amount_bank;

            $fillColor = ($index % 2 == 0) ? [248, 249, 250] : [255, 255, 255];
            $hasDiscount = $patient->discount > 0;
            if ($hasDiscount) {
                $fillColor = [255, 248, 220]; // Light amber alert
            }

            $this->SetFillColorArray($fillColor);
            $this->SetTextColor(40, 40, 40);

            $rowData = [
                $patient->doctorvisit_id,
                $patient->name,
                $patient->doctor_name,
                number_format($patient->total_lab_amount, 2),
                number_format($patient->total_paid_for_lab, 2),
                number_format($patient->discount, 2),
                number_format($patient->total_amount_bank, 2),
                $patient->company_name ?: '-',
            ];

            // Calc height required for tests column
            $testsText = trim((string)$patient->main_tests_names);
            $lines = $this->getNumLines($testsText, $colWidths[8]);
            $h = 7;
            $rowHeight = max($h, $lines * 5) + 2;

            if ($this->GetY() + $rowHeight > $this->getPageHeight() - $this->getMargins()['bottom']) {
                $this->AddPage();
            }

            foreach ($rowData as $i => $val) {
                if ($i == 5 && $hasDiscount) {
                    $this->SetTextColor(255, 140, 0); // Orange
                } elseif ($i == 6 && $patient->total_amount_bank > 0) {
                    $this->SetTextColor(220, 20, 60); // Red
                } else {
                    $this->SetTextColor(40, 40, 40); // Standard
                }
                
                $this->MultiCell($colWidths[$i], $rowHeight, $val, 1, 'C', true, 0, null, null, true, 0, false, true, $rowHeight, 'M');
            }

            $this->SetTextColor(80, 80, 80);
            $this->MultiCell($colWidths[8], $rowHeight, $testsText, 1, 'R', true, 1, null, null, true, 0, false, true, $rowHeight, 'M');
        }

        // Totals row
        $this->SetFont('arial', 'B', 10);
        $this->SetFillColor(52, 152, 219);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(41, 128, 185);

        $this->Cell($colWidths[0] + $colWidths[1] + $colWidths[2], 9, 'الإجمالي', 1, 0, 'C', true);
        $this->Cell($colWidths[3], 9, number_format($totalLabAmount, 2), 1, 0, 'C', true);
        $this->Cell($colWidths[4], 9, number_format($totalPaid, 2), 1, 0, 'C', true);
        $this->Cell($colWidths[5], 9, number_format($totalDiscount, 2), 1, 0, 'C', true);
        $this->Cell($colWidths[6], 9, number_format($totalBank, 2), 1, 0, 'C', true);
        $this->Cell($colWidths[7] + $colWidths[8], 9, '', 1, 0, 'C', true);
        $this->Ln();
    }

    protected function renderSummarySection($results): void
    {
        $this->SetFont('arial', 'B', 14);
        $this->SetTextColor(41, 98, 255);
        $this->Cell(0, 8, 'ملخص التقرير', 0, 1, 'R');
        $this->Ln(2);
        
        $this->SetFont('arial', 'B', 11);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(220, 220, 220);

        $totalLabAmount = $results->sum('total_lab_amount');
        $totalPaid = $results->sum('total_paid_for_lab');
        $totalDiscount = $results->sum('discount');
        $totalBank = $results->sum('total_amount_bank');

        $summaryItems = [
            'إجمالي المرضى' => $results->count(),
            'إجمالي مبلغ المختبر' => number_format($totalLabAmount, 2),
            'إجمالي المدفوع' => number_format($totalPaid, 2),
            'إجمالي الخصم' => number_format($totalDiscount, 2),
            'إجمالي المبلغ البنك' => number_format($totalBank, 2)
        ];
        
        $i = 0;
        foreach ($summaryItems as $label => $val) {
            $fill = ($i % 2 == 0) ? [248, 249, 250] : [255, 255, 255];
            $this->SetFillColorArray($fill);
            
            // Render label on right, value on left. Or right-aligned.
            $this->Cell(80, 10, $label, 1, 0, 'R', true);
            $this->Cell(60, 10, $val, 1, 1, 'C', true);
            $i++;
        }
    }
}
