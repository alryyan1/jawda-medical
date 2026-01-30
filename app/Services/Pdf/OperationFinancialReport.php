<?php

namespace App\Services\Pdf;

use TCPDF;
use App\Models\Operation;
use Carbon\Carbon;

class OperationFinancialReport
{
    private TCPDF $pdf;
    private Operation $operation;

    public function __construct(Operation $operation)
    {
        $this->operation = $operation;
        $this->initializePdf();
    }

    private function initializePdf(): void
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->setPrintHeader(false); // Custom header manual
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(15, 10, 15); // L, T, R
        $this->pdf->SetAutoPageBreak(TRUE, 15);
        $this->pdf->setRTL(true);
        $this->pdf->SetFont('arial', '', 12);
        $this->pdf->AddPage();
    }

    public function generate(): string
    {
        $this->renderHeader();
        $this->renderInfoTable();
        $this->renderFinancialTable();
        $this->renderFooter();

        return $this->pdf->Output('', 'S');
    }

    private function renderHeader(): void
    {
        $this->pdf->SetFont('arial', 'B', 16);
        $this->pdf->Cell(0, 10, 'مركز ون كير لجراحة اليوم الواحد', 0, 1, 'C');
        $this->pdf->SetFont('arial', 'B', 14);
        $this->pdf->Cell(0, 8, 'توزيع نسب العمليات', 0, 1, 'C');
        $this->pdf->Ln(5);
    }

    private function renderInfoTable(): void
    {
        $this->pdf->SetFont('arial', '', 12);

        // Date and Day
        $date = Carbon::parse($this->operation->operation_date);
        $this->pdf->Cell(20, 8, 'التاريخ:', 0, 0, 'L');
        $this->pdf->Cell(40, 8, $date->format('Y/m/d'), 0, 0, 'R');

        $this->pdf->Cell(20, 8, 'اليوم:', 0, 0, 'L');
        $this->pdf->Cell(40, 8, $date->locale('ar')->dayName, 0, 1, 'R');

        // Patient Name
        $this->pdf->Cell(30, 8, 'اسم المريض:', 0, 0, 'L');
        $this->pdf->SetFont('arial', 'B', 12);
        $patientName = $this->operation->admission?->patient?->name ?? 'غير محدد';
        $this->pdf->Cell(0, 8, $patientName, 'B', 1, 'R'); // Underline manually or bottom border

        $this->pdf->SetFont('arial', '', 12);

        // Operation Type
        $this->pdf->Cell(30, 8, 'نوع العملية:', 0, 0, 'L');
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->Cell(0, 8, $this->operation->operation_type, 'B', 1, 'R');

        $this->pdf->SetFont('arial', '', 12);
        $this->pdf->Ln(2);

        // Operation Value
        $this->pdf->Cell(30, 8, 'قيمة العملية:', 0, 0, 'L');
        $this->pdf->SetFont('arial', 'B', 12);
        $totalAmount = number_format($this->operation->total_amount, 0);
        $this->pdf->Cell(50, 8, $totalAmount . ' د.ع', 1, 1, 'C'); // Boxed as in image

        $this->pdf->Ln(5);
    }

    private function renderFinancialTable(): void
    {
        // Table Header
        $this->pdf->SetFont('arial', 'B', 11);
        $this->pdf->SetFillColor(240, 240, 240);

        // Columns: Note (30), Signature (30), Amount (30), Name (50), Ratio/Item (50)
        // Total Width ~190 (A4 is 210, margins 15+15=30 => 180 usable)
        // Let's adjust: Item (50), Name (50), Amount (30), Signature (30), Notes (20) -> 180

        $w = [55, 45, 30, 30, 20];

        $this->pdf->Cell($w[0], 10, 'النسب / البند', 1, 0, 'C', true);
        $this->pdf->Cell($w[1], 10, 'الاسم', 1, 0, 'C', true);
        $this->pdf->Cell($w[2], 10, 'المبلغ', 1, 0, 'C', true);
        $this->pdf->Cell($w[3], 10, 'التوقيع', 1, 0, 'C', true);
        $this->pdf->Cell($w[4], 10, 'ملاحظات', 1, 1, 'C', true);

        // Data Rows
        $this->pdf->SetFont('arial', '', 11);

        $items = $this->operation->financeItems;

        // Sort items roughly by standard order (Surgeon -> Assistant -> Anesthesia -> Others -> Center) for display
        // We can do custom sort or just list them

        foreach ($items as $item) {
            $amount = number_format((float)$item->amount, 0);

            // Name logic
            $name = '';
            // Surgeon ID is 1
            if ($item->operation_item_id === 1) {
                // Try to get operation doctor? Or user? For now leave blank or use description
                $name = ''; // Usually signed by doctor
            }

            $this->pdf->Cell($w[0], 8, $item->description, 1, 0, 'R');
            $this->pdf->Cell($w[1], 8, $name, 1, 0, 'C'); // Name placeholder
            $this->pdf->Cell($w[2], 8, $amount, 1, 0, 'C');
            $this->pdf->Cell($w[3], 8, '', 1, 0, 'C'); // Signature empty
            $this->pdf->Cell($w[4], 8, '', 1, 1, 'C'); // Note empty
        }

        // Space filler rows if few items? (Optional, based on image showing many rows)

        // Total Row
        $this->pdf->SetFont('arial', 'B', 11);
        $this->pdf->Cell($w[0] + $w[1], 10, 'الإجمالي', 1, 0, 'C');
        $this->pdf->Cell($w[2], 10, number_format((float)$this->operation->total_amount, 0), 1, 0, 'C');
        $this->pdf->Cell($w[3] + $w[4], 10, '', 1, 1, 'C');
    }

    private function renderFooter(): void
    {
        $this->pdf->Ln(20);

        $this->pdf->SetFont('arial', 'B', 12);

        $this->pdf->Cell(60, 10, 'المدير المالي', 0, 0, 'C');
        $this->pdf->Cell(60, 10, 'المدير الإداري', 0, 0, 'C');
        $this->pdf->Cell(60, 10, 'اعتماد المدير العام', 0, 1, 'C');

        $this->pdf->Ln(15);

        // Signatures lines
        $this->pdf->Cell(60, 10, '....................', 0, 0, 'C');
        $this->pdf->Cell(60, 10, '....................', 0, 0, 'C');
        $this->pdf->Cell(60, 10, '....................', 0, 1, 'C');
    }
}
