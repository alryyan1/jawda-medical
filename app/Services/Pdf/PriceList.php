<?php

namespace App\Services\Pdf;

use Carbon\Carbon;

class PriceList extends MyCustomTCPDF
{
    protected array $tests;
    protected string $hospitalName;
    protected Carbon $date;

    public function __construct(array $tests, string $hospitalName, ?Carbon $date = null)
    {
        parent::__construct('  ');

        $this->tests = $tests;
        $this->hospitalName = $hospitalName;
        $this->date = $date ?? Carbon::now();

        $this->SetCreator('Jawda Medical System');
        $this->SetAuthor('Jawda Medical System');
        $this->SetTitle('Laboratory Price List');
        $this->SetSubject('Laboratory Price List');
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

        // Title: قائمة أسعار التحاليل (center)
        $this->SetFont('arial', 'B', 20);
        $this->SetTextColor(40, 40, 40);
        $this->Cell(0, 12, 'قائمة أسعار التحاليل', 0, 1, 'C');
        
        // Decorative line
        $this->SetDrawColor(70, 130, 180);
        $this->SetLineWidth(0.3);
        $this->Line(15, $this->GetY(), 195, $this->GetY());
        $this->setAutoPageBreak(true,40);

        $this->Ln(4);
        $this->SetFont('arial', '', 11);
        $this->Cell(0, 8, 'التاريخ: ' . $this->date->format('Y-m-d'), 0, 1, 'R');

        $this->Ln(6);

        // Table header
        $this->SetFont('arial', 'B', 12);
        $this->SetFillColor(230, 230, 230);
        $this->Cell(20, 8, 'الكود', 1, 0, 'C', true);
        $this->Cell(100, 8, 'اسم الفحص', 1, 0, 'C', true);
        $this->Cell(30, 8, 'الوعاء', 1, 0, 'C', true);
        $this->Cell(25, 8, 'السعر', 1, 1, 'C', true);

        // Table content
        $this->SetFont('arial', '', 10);
        $totalTests = count($this->tests);
        $totalValue = 0.0;

        foreach ($this->tests as $index => $test) {
            $id = (string)($test['id'] ?? '-');
            $name = (string)($test['main_test_name'] ?? '-');
            $container = (string)($test['container_name'] ?? $test['container']['container_name'] ?? '-');
            $price = (float)($test['price'] ?? 0);
            $totalValue += $price;

            // Alternate row colors
            if ($index % 2 === 0) {
                $this->SetFillColor(248, 249, 250);
            } else {
                $this->SetFillColor(255, 255, 255);
            }

            $this->Cell(20, 8, $id, 1, 0, 'C', true);
            $this->Cell(100, 8, $name, 1, 0, 'R', true);
            $this->Cell(30, 8, $container, 1, 0, 'C', true);
            $this->Cell(25, 8, number_format($price, 2), 1, 1, 'C', true);
        }

        $this->Ln(6);

        // Summary section
        $this->SetFont('arial', 'B', 12);
        $this->SetFillColor(240, 240, 240);


        // Footer note
        $this->Ln(10);
        $this->SetFont('arial', 'I', 9);
        $this->SetTextColor(100, 100, 100);

        $filename = 'price_list_' . date('Y-m-d_H-i-s') . '.pdf';
        return $this->Output($filename, 'S');
    }
}
