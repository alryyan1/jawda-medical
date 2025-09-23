<?php

namespace App\Services\Pdf;

use Carbon\Carbon;

class LabInvoice extends MyCustomTCPDF
{
    protected array $tests;
    protected string $patientName;
    protected string $hospitalName;
    protected float $totalPaid;
    protected Carbon $date;

    public function __construct(array $tests, string $patientName, string $hospitalName, float $totalPaid, ?Carbon $date = null)
    {
        parent::__construct('  ');

        $this->tests = $tests;
        $this->patientName = $patientName;
        $this->hospitalName = $hospitalName;
        $this->totalPaid = $totalPaid;
        $this->date = $date ?? Carbon::now();

        $this->SetCreator('Jawda Medical System');
        $this->SetAuthor('Jawda Medical System');
        $this->SetTitle('Laboratory Invoice');
        $this->SetSubject('Laboratory Invoice');
        $this->isLab = true;
        $this->SetMargins(15, 20, 15);
        $this->SetAutoPageBreak(true, 20);
    }

    public function generate(): string
    {
        $this->AddPage('P', 'A4');

        // Hospital name (center)
        $this->SetFont('arial', 'B', 16);
        $this->SetTextColor(0, 0, 0);
        $this->Cell(0, 10, $this->hospitalName, 0, 1, 'C');

        // Title: فاتوره (center)
        $this->SetFont('arial', 'B', 20);
        $this->SetTextColor(40, 40, 40);
        $this->Cell(0, 12, 'فاتوره', 0, 1, 'C');
        // Decorative line
        $this->SetDrawColor(70, 130, 180);
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), 195, $this->GetY());

        $this->Ln(4);
        $this->SetFont('arial', '', 11);
        $this->Cell(0, 8, 'اسم المريض: ' . $this->patientName, 0, 0, 'R');
        $this->Ln(0);

        $this->SetXY(15, $this->GetY());
        $this->Cell(0, 8, 'التاريخ: ' . $this->date->format('Y-m-d'), 0, 1, 'L');

        $this->Ln(6);

        $this->SetFont('arial', 'B', 12);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(120, 8, 'اسم الفحص', 1, 0, 'C', true);
        $this->Cell(45, 8, 'السعر', 1, 1, 'C', true);

        $this->SetFont('arial', '', 11);
        $total = 0.0;
        foreach ($this->tests as $index => $test) {
            $name = (string)($test['name'] ?? '-');
            $price = (float)($test['price'] ?? 0);
            $total += $price;
            if ($index % 2 === 0) {
                $this->SetFillColor(248, 249, 250);
            } else {
                $this->SetFillColor(255, 255, 255);
            }
            $this->Cell(120, 8, $name, 1, 0, 'R', true);
            $this->Cell(45, 8, number_format($price, 2), 1, 1, 'C', true);
        }

        $this->Ln(6);
        $this->SetFont('arial', 'B', 12);
        $this->SetFillColor(240, 240, 240);
        $this->Cell(120, 8, 'الإجمالي', 1, 0, 'R', true);
        $this->Cell(45, 8, number_format($total, 2), 1, 1, 'C', true);

        $this->Cell(120, 8, 'المدفوع', 1, 0, 'R', true);
        $this->Cell(45, 8, number_format($this->totalPaid, 2), 1, 1, 'C', true);

        $filename = 'lab_invoice_' . date('Y-m-d_H-i-s') . '.pdf';
        return $this->Output($filename, 'S');
    }
}


