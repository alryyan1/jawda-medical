<?php

namespace App\Services\Pdf;

use App\Mypdf\Pdf;

class MonthlyAttendanceReport
{
    /**
     * Generate Monthly Attendance PDF content.
     *
     * @param array $summaryList Array of attendance summary rows
     * @param array $meta Metadata: year, month, month_name, shift_name
     * @return string PDF content (string from TCPDF Output with 'S')
     */
    public function generate(array $summaryList, array $meta): string
    {
        // Initialize PDF (Landscape A4)
        $pdf = new Pdf('landscape', PDF_UNIT, 'A4', true, 'UTF-8', false);
        $pdf->setCompression(true);
        $pdf->setImageScale(1.25);
        $pdf->setJPEGQuality(80);

        // Arabic / RTL setup
        $title = 'ملخص الحضور الشهري للموظفين';
        $filterCriteria = 'عن: ' . ($meta['month_name'] ?? '') .
            (!empty($meta['shift_name']) ? (' | الوردية: ' . $meta['shift_name']) : '');
        $generatedAt = 'تاريخ الإنشاء: ' . date('Y-m-d H:i');

        $pdf->setCreator(PDF_CREATOR);
        $pdf->setAuthor('System');
        $pdf->setTitle($title);
        $pdf->setSubject('تقرير الحضور');

        // Use RTL and Arabic language settings
        if (method_exists($pdf, 'setRTL')) {
            $pdf->setRTL(true);
        }
        if (method_exists($pdf, 'setLanguageArray')) {
            $lg = [];
            $lg['a_meta_charset']  = 'UTF-8';
            $lg['a_meta_dir']      = 'rtl';
            $lg['a_meta_language'] = 'ar';
            $lg['w_page']          = 'صفحة';
            $pdf->setLanguageArray($lg);
        }

        $pdf->SetMargins(PDF_MARGIN_LEFT, 18, PDF_MARGIN_RIGHT);
        $pdf->setHeaderMargin(5);
        $pdf->setFooterMargin(10);
        $pdf->setAutoPageBreak(true, 15);
        $pdf->setFillColor(200, 200, 200);

        // Brand / palette
        $primaryR = 33; $primaryG = 150; $primaryB = 243; // blue
        $tableHeaderR = $primaryR; $tableHeaderG = $primaryG; $tableHeaderB = $primaryB;
        $zebraR = 248; $zebraG = 249; $zebraB = 250;
        $textDarkR = 45; $textDarkG = 55; $textDarkB = 72;

        // Simple header renderer
        $pageWidth = $pdf->getPageWidth() - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT;
        $pdf->head = function ($pdf) use ($title, $filterCriteria, $pageWidth, $primaryR, $primaryG, $primaryB, $textDarkR, $textDarkG, $textDarkB) {
            // Use a Unicode font that supports Arabic (bundled with TCPDF)
            $pdf->SetFillColor($primaryR, $primaryG, $primaryB);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('dejavusans', 'B', 14, '', true);
            $pdf->Cell($pageWidth, 10, $title, 0, 1, 'C', 1);

            $pdf->SetTextColor($textDarkR, $textDarkG, $textDarkB);
            $pdf->SetFont('dejavusans', '', 9, '', true);
            $pdf->Cell($pageWidth, 7, $filterCriteria, 0, 1, 'C');
            $pdf->Ln(2);
        };

        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 8);

        // Define table
        // Arabic table headers
        $headers = [
            '#', 'اسم الموظف', 'الأيام المجدولة', 'الحضور', 'تأخير', 'انصراف مبكر',
            'غياب', 'إجازة', 'إجازة مرضية', 'عطلات'
        ];

        // Column widths and aligns
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [8, 55, 20, 20, 20, 22, 20, 25, 25, 0];
        $colWidths[count($colWidths) - 1] = $pageWidth - array_sum(array_slice($colWidths, 0, -1));
        // Align name column to the right for RTL readability
        $aligns = ['C', 'R', 'C', 'C', 'C', 'C', 'C', 'C', 'C', 'C'];

        // Draw header row
        // Styled table header
        $pdf->SetDrawColor(200, 200, 200);
        if (is_callable([$pdf, 'DrawTableHeader'])) {
            // Let custom helper render if available
            call_user_func([$pdf, 'DrawTableHeader'], $headers, $colWidths, $aligns, 8);
        } else {
            $pdf->SetFillColor($tableHeaderR, $tableHeaderG, $tableHeaderB);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetFont('dejavusans', 'B', 8);
            foreach ($headers as $i => $h) {
                $pdf->Cell($colWidths[$i], 8, $h, 1, 0, 'C', 1);
            }
            $pdf->Ln();
            $pdf->SetTextColor(0, 0, 0);
            $pdf->SetFont('dejavusans', '', 8);
        }

        // Empty-state handling
        if (empty($summaryList)) {
            $pdf->Ln(10);
            $pdf->SetFont('dejavusans', 'B', 10);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->Cell($pageWidth, 10, 'لا توجد بيانات لعرضها في هذا الشهر.', 1, 1, 'C');
            $pdf->SetTextColor(0, 0, 0);
        }

        // Totals accumulator
        $totals = [
            'total_scheduled_days' => 0,
            'present_days' => 0,
            'late_present_days' => 0,
            'early_leave_days' => 0,
            'absent_days' => 0,
            'on_leave_days' => 0,
            'sick_leave_days' => 0,
            'holidays_on_workdays' => 0,
        ];

        $fill = false;
        foreach ($summaryList as $idx => $summary) {
            $rowData = [
                $idx + 1,
                (string)($summary['user_name'] ?? ''),
                (string)($summary['total_scheduled_days'] ?? ''),
                (string)($summary['present_days'] ?? ''),
                (string)($summary['late_present_days'] ?? ''),
                (string)($summary['early_leave_days'] ?? ''),
                (string)($summary['absent_days'] ?? ''),
                (string)($summary['on_leave_days'] ?? ''),
                (string)($summary['sick_leave_days'] ?? ''),
                (string)($summary['holidays_on_workdays'] ?? ''),
            ];

            // Update totals (treat missing as 0)
            $totals['total_scheduled_days'] += (int)($summary['total_scheduled_days'] ?? 0);
            $totals['present_days'] += (int)($summary['present_days'] ?? 0);
            $totals['late_present_days'] += (int)($summary['late_present_days'] ?? 0);
            $totals['early_leave_days'] += (int)($summary['early_leave_days'] ?? 0);
            $totals['absent_days'] += (int)($summary['absent_days'] ?? 0);
            $totals['on_leave_days'] += (int)($summary['on_leave_days'] ?? 0);
            $totals['sick_leave_days'] += (int)($summary['sick_leave_days'] ?? 0);
            $totals['holidays_on_workdays'] += (int)($summary['holidays_on_workdays'] ?? 0);

            // Zebra row background
            $pdf->SetFillColor($zebraR, $zebraG, $zebraB);
            if (is_callable([$pdf, 'DrawTableRow'])) {
                call_user_func([$pdf, 'DrawTableRow'], $rowData, $colWidths, $aligns, $fill, 6);
            } else {
                foreach ($rowData as $i => $cell) {
                    $pdf->Cell($colWidths[$i], 6, $cell, 1, 0, $aligns[$i], $fill ? 1 : 0);
                }
                $pdf->Ln();
            }
            $fill = !$fill;
        }

        // Totals row (only if we had data)
        if (!empty($summaryList)) {
            $pdf->SetFont('dejavusans', 'B', 8);
            $pdf->SetFillColor(230, 240, 255);
            $pdf->Cell($colWidths[0], 7, '', 1, 0, 'C', 1);
            $pdf->Cell($colWidths[1], 7, 'الإجمالي', 1, 0, 'R', 1);
            $pdf->SetFont('dejavusans', '', 8);
            $totalsRow = [
                (string)$totals['total_scheduled_days'],
                (string)$totals['present_days'],
                (string)$totals['late_present_days'],
                (string)$totals['early_leave_days'],
                (string)$totals['absent_days'],
                (string)$totals['on_leave_days'],
                (string)$totals['sick_leave_days'],
                (string)$totals['holidays_on_workdays'],
            ];
            for ($i = 2; $i < count($colWidths); $i++) {
                $pdf->Cell($colWidths[$i], 7, $totalsRow[$i - 2], 1, 0, 'C', 1);
            }
            $pdf->Ln();
        }

        // Bottom line
        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        // Footer note (generated at)
        $pdf->Ln(2);
        $pdf->SetFont('dejavusans', '', 7);
        $pdf->SetTextColor(120, 120, 120);
        $pdf->Cell($pageWidth, 5, $generatedAt, 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        $fileName = 'MonthlyAttendance_' . ($meta['year'] ?? '') . '-' . ($meta['month'] ?? '');
        if (!empty($meta['shift_name'])) {
            $fileName .= '_' . str_replace(' ', '_', (string)$meta['shift_name']);
        }
        $fileName .= '.pdf';

        return $pdf->Output($fileName, 'S');
    }
}


