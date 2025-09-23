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
        parent::__construct('  ');

        $this->results = $results;
        $this->request = $request;
        $this->userRevenues = $userRevenues;

        $this->SetCreator('Jawda Medical System');
        $this->SetAuthor('Jawda Medical System');
        $this->SetTitle('تقرير المختبر العام');
        $this->SetSubject('تقرير المختبر العام');
        $this->isLab = true; // Enable footer with page numbers

        // Set margins (left, top, right)
        $this->SetMargins(15, 20, 15);
        $this->SetAutoPageBreak(true, 20);
    }

    public function generate(): string
    {
        $request = $this->request;
        $results = $this->results;

        // Add a page
        $this->AddPage('L', 'A4');

        // Calculate available width (A4 landscape = 297mm, minus margins = 267mm)
        $availableWidth = 267; // 297 - 15 (left) - 15 (right)

        // Set default font to Arial
        $this->SetFont('arial', '', 10);
        $this->setAutoPageBreak(true, 40);
        // Title with better styling
        $this->SetFont('arial', 'B', 18);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 12, 'تقرير المختبر العام', 0, 1, 'C');
        $this->Ln(3);
        
        // Add a decorative line under title
        $this->SetDrawColor(70, 130, 180);
        $this->SetLineWidth(0.1);
        $this->Line(15, $this->GetY(), 282, $this->GetY());
        $this->Ln(8);

        // Report period with better styling
        $this->SetFont('arial', '', 10);
        $this->SetTextColor(60, 60, 60);
        
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $this->Cell(0, 8, 'فترة التقرير: من ' . $request->date_from . ' إلى ' . $request->date_to, 0, 1, 'R');
        } elseif ($request->filled('date_from')) {
            $this->Cell(0, 8, 'من تاريخ: ' . $request->date_from, 0, 1, 'R');
        } elseif ($request->filled('date_to')) {
            $this->Cell(0, 8, 'إلى تاريخ: ' . $request->date_to, 0, 1, 'R');
        }

        // Shift information
        if ($request->filled('shift_id')) {
            $this->Cell(0, 8, 'المناوبة: ' . $request->shift_id, 0, 1, 'R');
        }
        
        // Add generation date and time
        $this->SetFont('arial', 'I', 9);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 6, 'تم إنشاء التقرير في: ' . Carbon::now()->format('Y-m-d H:i:s'), 0, 1, 'R');
        $this->Ln(8);

        // User Revenue Section
        $this->renderUserRevenueSection($availableWidth);

        $this->AddPage();
        
        // Patient Details Section
        $this->renderPatientsTable($availableWidth, $results);

        // Summary section with modern styling
        $this->Ln(12);
        $this->AddPage();
        $this->renderSummarySection($results);

        // Output PDF as string
        $filename = 'lab_general_report_' . date('Y-m-d_H-i-s') . '.pdf';
        return $this->Output($filename, 'S');
    }

    protected function renderUserRevenueSection(float $availableWidth): void
    {
        $this->SetFont('arial', 'B', 14);
        $this->SetFillColor(70, 130, 180);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, 'ايراد حسب المستخدم', 1, 1, 'C', true);

        // headers
        $this->SetFont('arial', 'B', 10);
        $this->SetFillColor(255, 255, 255);
        $this->SetTextColor(0, 0, 0);
        
        $userColWidths = [
            $availableWidth * 0.25,
            $availableWidth * 0.25,
            $availableWidth * 0.20,
            $availableWidth * 0.15,
            $availableWidth * 0.15
        ];
        $userHeaders = ['اسم المستخدم', 'إجمالي المدفوع', 'إجمالي التخفيض', 'إجمالي كاش', 'إجمالي بنك'];
        for ($i = 0; $i < count($userHeaders); $i++) {
            $this->Cell($userColWidths[$i], 8, $userHeaders[$i], 1, 0, 'C', false);
        }
        $this->Ln();

        // data rows
        $this->SetFont('arial', '', 9);
        $totalUserPaid = 0;
        $totalUserDiscount = 0;
        $totalUserCash = 0;
        $totalUserBank = 0;

        foreach ($this->userRevenues as $index => $userRevenue) {
            $totalUserPaid += $userRevenue->total_paid;
            $totalUserDiscount += $userRevenue->total_discount;
            $totalUserCash += $userRevenue->total_cash;
            $totalUserBank += $userRevenue->total_bank;

            if ($index % 2 == 0) {
                $this->SetFillColor(248, 249, 250);
            } else {
                $this->SetFillColor(255, 255, 255);
            }

            $this->Cell($userColWidths[0], 8, $userRevenue->user_name, 1, 0, 'C', true);
            $this->Cell($userColWidths[1], 8, number_format($userRevenue->total_paid, 2), 1, 0, 'C', true);
            $this->Cell($userColWidths[2], 8, number_format($userRevenue->total_discount, 2), 1, 0, 'C', true);
            $this->Cell($userColWidths[3], 8, number_format($userRevenue->total_cash, 2), 1, 0, 'C', true);
            $this->Cell($userColWidths[4], 8, number_format($userRevenue->total_bank, 2), 1, 0, 'C', true);
            $this->Ln();
        }

        // totals row
        $this->SetFont('arial', 'B', 10);
        $this->SetFillColor(188, 188, 188);
        // $this->SetTextColor(255, 255, 255);
        $this->Cell($userColWidths[0], 8, 'الإجمالي', 1, 0, 'C', true);
        $this->Cell($userColWidths[1], 8, number_format($totalUserPaid, 2), 1, 0, 'C', true);
        $this->Cell($userColWidths[2], 8, number_format($totalUserDiscount, 2), 1, 0, 'C', true);
        $this->Cell($userColWidths[3], 8, number_format($totalUserCash, 2), 1, 0, 'C', true);
        $this->Cell($userColWidths[4], 8, number_format($totalUserBank, 2), 1, 0, 'C', true);
        $this->Ln();
    }

    protected function renderPatientsTable(float $availableWidth, $results): void
    {
        $this->SetFont('arial', 'B', 14);
        $this->SetFillColor(70, 130, 180);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, 'تفاصيل المرضى', 1, 1, 'C', true);
        
        $this->SetFont('arial', 'B', 10);
        $this->SetFillColor(255, 255, 255);
        $this->SetTextColor(0, 0, 0);

        $headers = [
            'رقم الزيارة', 'اسم المريض', 'الطبيب', 'إجمالي المبلغ', 'المدفوع', 'الخصم', 'المبلغ البنك', 'الشركة', 'التحاليل'
        ];
        $colWidths = [
            $availableWidth * 0.08,
            $availableWidth * 0.15,
            $availableWidth * 0.12,
            $availableWidth * 0.10,
            $availableWidth * 0.10,
            $availableWidth * 0.08,
            $availableWidth * 0.10,
            $availableWidth * 0.12,
            $availableWidth * 0.15
        ];

        $totalWidth = array_sum($colWidths);
        if (abs($totalWidth - $availableWidth) > 1) {
            $colWidths[8] += ($availableWidth - $totalWidth);
        }

        for ($i = 0; $i < count($headers); $i++) {
            $this->Cell($colWidths[$i], 10, $headers[$i], 1, 0, 'C', false);
        }
        $this->Ln();

        $this->SetFont('arial', '', 9);
        $this->SetTextColor(0, 0, 0);

        $totalLabAmount = 0;
        $totalPaid = 0;
        $totalDiscount = 0;
        $totalBank = 0;

        foreach ($results as $index => $patient) {
            $totalLabAmount += $patient->total_lab_amount;
            $totalPaid += $patient->total_paid_for_lab;
            $totalDiscount += $patient->discount;
            $totalBank += $patient->total_amount_bank;

            if ($index % 2 == 0) {
                $this->SetFillColor(248, 249, 250);
            } else {
                $this->SetFillColor(255, 255, 255);
            }

            $hasDiscount = $patient->discount > 0;
            if ($hasDiscount) {
                $this->SetFillColor(255, 248, 220);
            }

            $this->Cell($colWidths[0], 8, $patient->doctorvisit_id, 1, 0, 'C', true);
            $this->Cell($colWidths[1], 8, $patient->name, 1, 0, 'C', true);
            $this->Cell($colWidths[2], 8, $patient->doctor_name, 1, 0, 'C', true,'',true);
            $this->Cell($colWidths[3], 8, number_format($patient->total_lab_amount, 2), 1, 0, 'C', true);
            $this->Cell($colWidths[4], 8, number_format($patient->total_paid_for_lab, 2), 1, 0, 'C', true);
            if ($hasDiscount) {
                $this->SetTextColor(255, 140, 0);
            }
            $this->Cell($colWidths[5], 8, number_format($patient->discount, 2), 1, 0, 'C', true);
            $this->SetTextColor(0, 0, 0);
            if ($patient->total_amount_bank > 0) {
                $this->SetTextColor(220, 20, 60);
            }
            $this->Cell($colWidths[6], 8, number_format($patient->total_amount_bank, 2), 1, 0, 'C', true);
            $this->SetTextColor(0, 0, 0);
            $this->Cell($colWidths[7], 8, $patient->company_name ?: '-', 1, 0, 'C', true);
            
            $currentY = $this->GetY();
            $this->MultiCell($colWidths[8], 8, $patient->main_tests_names, 1, 'C', true);
            $newY = $this->GetY();
            $this->SetXY($this->GetX() + $colWidths[8], $currentY);

            if ($newY > $currentY + 8) {
                $rowHeight = $newY - $currentY;
                $this->SetXY(15, $currentY);
                $this->Cell($colWidths[0], $rowHeight, $patient->doctorvisit_id, 1, 0, 'C', true);
                $this->Cell($colWidths[1], $rowHeight, $patient->name, 1, 0, 'C', true);
                $this->Cell($colWidths[2], $rowHeight, $patient->doctor_name, 1, 0, 'C', true);
                $this->Cell($colWidths[3], $rowHeight, number_format($patient->total_lab_amount, 2), 1, 0, 'C', true);
                $this->Cell($colWidths[4], $rowHeight, number_format($patient->total_paid_for_lab, 2), 1, 0, 'C', true);
                if ($hasDiscount) {
                    $this->SetTextColor(255, 140, 0);
                }
                $this->Cell($colWidths[5], $rowHeight, number_format($patient->discount, 2), 1, 0, 'C', true);
                $this->SetTextColor(0, 0, 0);
                if ($patient->total_amount_bank > 0) {
                    $this->SetTextColor(220, 20, 60);
                }
                $this->Cell($colWidths[6], $rowHeight, number_format($patient->total_amount_bank, 2), 1, 0, 'C', true);
                $this->SetTextColor(0, 0, 0);
                $this->Cell($colWidths[7], $rowHeight, $patient->company_name ?: '-', 1, 0, 'C', true);
                $this->SetXY(15 + array_sum(array_slice($colWidths, 0, 8)), $currentY);
                $this->MultiCell($colWidths[8], 8, $patient->main_tests_names, 1, 'C', true);
            }
            
            $this->Ln();
        }

        // Totals row
        $this->SetFont('arial', 'B', 10);
        $this->SetFillColor(50, 50, 50);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($colWidths[0] + $colWidths[1] + $colWidths[2], 10, 'الإجمالي', 1, 0, 'C', true);
        $this->Cell($colWidths[3], 10, number_format($totalLabAmount, 2), 1, 0, 'C', true);
        $this->Cell($colWidths[4], 10, number_format($totalPaid, 2), 1, 0, 'C', true);
        $this->Cell($colWidths[5], 10, number_format($totalDiscount, 2), 1, 0, 'C', true);
        $this->Cell($colWidths[6], 10, number_format($totalBank, 2), 1, 0, 'C', true);
        $this->Cell($colWidths[7] + $colWidths[8], 10, '', 1, 0, 'C', true);
        $this->Ln();
    }

    protected function renderSummarySection($results): void
    {
        $this->SetFont('arial', 'B', 14);
        $this->SetFillColor(70, 130, 180);
        $this->SetTextColor(255, 255, 255);
        $this->Cell(0, 10, 'ملخص التقرير', 1, 1, 'C', true);
        
        $this->SetFont('arial', '', 11);
        $this->SetTextColor(0, 0, 0);

        $totalLabAmount = $results->sum('total_lab_amount');
        $totalPaid = $results->sum('total_paid_for_lab');
        $totalDiscount = $results->sum('discount');
        $totalBank = $results->sum('total_amount_bank');

        $summaryItems = [
            'إجمالي المرضى: ' . $results->count(),
            'إجمالي مبلغ المختبر: ' . number_format($totalLabAmount, 2) . ' ',
            'إجمالي المدفوع: ' . number_format($totalPaid, 2) . ' ',
            'إجمالي الخصم: ' . number_format($totalDiscount, 2) . ' ',
            'إجمالي المبلغ البنك: ' . number_format($totalBank, 2) . ' '
        ];
        
        foreach ($summaryItems as $index => $item) {
            if ($index % 2 == 0) {
                $this->SetFillColor(248, 249, 250);
            } else {
                $this->SetFillColor(255, 255, 255);
            }
            $this->Cell(0, 8, $item, 1, 1, 'R', true);
        }
    }
}


