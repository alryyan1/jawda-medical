<?php

namespace App\Services\Pdf;

use App\Http\Controllers\Api\ReportController;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use TCPDF;

class DepartmentRevenueExpensesReport
{
    private TCPDF $pdf;

    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->initializePdf();
    }

    private function initializePdf(): void
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->setFontSubsetting(true);
        $this->pdf->SetFont('arial', 'B', 10);

        $this->pdf->SetCreator('Jawda Medical System');
        $this->pdf->SetAuthor('Jawda Medical System');
        $this->pdf->SetTitle($this->data['title']);
        $this->pdf->SetSubject('Department Revenue vs Expenses Report');

        $this->pdf->SetMargins(15, 20, 15);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetFooterMargin(10);
        $this->pdf->SetAutoPageBreak(true, 25);
        $this->pdf->setRTL(true);

        $this->pdf->AddPage();
    }

    public function generate(): string
    {
        $this->renderTitle();
        $this->renderSummaryCards();
        $this->renderTable();
        $this->renderUnassignedExpense();
        $this->renderFooter();

        return $this->pdf->Output('', 'S');
    }

    private function renderTitle(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 15;
        $usableWidth = $pageWidth - (2 * $margin);

        $this->pdf->SetY(20);
        $this->pdf->SetFont('arial', 'B', 16);
        $this->pdf->SetTextColor(45, 55, 72);
        $this->pdf->Cell(0, 10, $this->data['title'], 0, 1, 'C');

        $this->pdf->SetDrawColor(45, 55, 72);
        $this->pdf->SetLineWidth(0.5);
        $lineY = $this->pdf->GetY();
        $this->pdf->Line($pageWidth / 2 - 40, $lineY, $pageWidth / 2 + 40, $lineY);
        $this->pdf->Ln(4);

        $this->pdf->SetFont('arial', '', 10);
        $this->pdf->SetTextColor(107, 114, 128);
        $periodText = "الفترة من {$this->data['from']} إلى {$this->data['to']}  |  تاريخ الطباعة: ".Carbon::now()->format('Y-m-d H:i');
        $this->pdf->Cell($usableWidth, 6, $periodText, 0, 1, 'C');
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Ln(4);
    }

    private function renderSummaryCards(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 15;
        $usableWidth = $pageWidth - (2 * $margin);
        $summary = $this->data['summary'];
        $cardWidth = $usableWidth / 3;

        $green = [6, 95, 70];
        $greenBg = [236, 253, 245];
        $red = [153, 27, 27];
        $redBg = [254, 242, 242];

        $cards = [
            ['label' => 'إجمالي الإيراد', 'value' => $summary['revenue_total'], 'bg' => $greenBg, 'fg' => $green],
            ['label' => 'إجمالي المصروفات', 'value' => $summary['expense_total'], 'bg' => $redBg, 'fg' => $red],
            ['label' => 'صافي الإيراد', 'value' => $summary['net'], 'bg' => $summary['net'] >= 0 ? $greenBg : $redBg, 'fg' => $summary['net'] >= 0 ? $green : $red],
        ];

        $this->pdf->SetDrawColor(230, 230, 230);
        $this->pdf->SetLineWidth(0.3);

        // Label row
        $this->pdf->SetFont('arial', '', 9);
        $this->pdf->SetTextColor(107, 114, 128);
        foreach ($cards as $i => $card) {
            $this->pdf->SetFillColor($card['bg'][0], $card['bg'][1], $card['bg'][2]);
            $this->pdf->Cell($cardWidth, 7, $card['label'], 1, $i === count($cards) - 1 ? 1 : 0, 'C', true);
        }

        // Value row
        $this->pdf->SetFont('arial', 'B', 14);
        foreach ($cards as $i => $card) {
            $this->pdf->SetFillColor($card['bg'][0], $card['bg'][1], $card['bg'][2]);
            $this->pdf->SetTextColor($card['fg'][0], $card['fg'][1], $card['fg'][2]);
            $this->pdf->Cell($cardWidth, 11, number_format($card['value'], 0), 1, $i === count($cards) - 1 ? 1 : 0, 'C', true);
        }

        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Ln(6);
    }

    private function renderTable(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 15;
        $usableWidth = $pageWidth - (2 * $margin);
        $rows = $this->data['rows'];
        $summary = $this->data['summary'];

        $this->pdf->SetFont('arial', 'B', 13);
        $this->pdf->SetTextColor(45, 55, 72);
        $titleText = 'تفاصيل الأقسام';
        $this->pdf->Cell($usableWidth, 8, $titleText, 0, 1, 'R');
        $this->pdf->SetDrawColor(45, 55, 72);
        $this->pdf->SetLineWidth(1);
        $lineY = $this->pdf->GetY();
        $titleWidth = $this->pdf->GetStringWidth($titleText);
        $this->pdf->Line($pageWidth - $margin - $titleWidth, $lineY, $pageWidth - $margin, $lineY);
        $this->pdf->Ln(4);

        $colWidths = [
            'name' => $usableWidth * 0.26,
            'rev_cash' => $usableWidth * 0.13,
            'rev_bank' => $usableWidth * 0.13,
            'rev_total' => $usableWidth * 0.15,
            'expense' => $usableWidth * 0.15,
            'net' => $usableWidth * 0.18,
        ];

        $this->pdf->SetDrawColor(220, 223, 230);
        $this->pdf->SetLineWidth(0.3);
        $this->pdf->SetFont('arial', 'B', 10);
        $this->pdf->SetFillColor(45, 55, 72);
        $this->pdf->SetTextColor(255, 255, 255);
        $this->pdf->Cell($colWidths['name'], 8, 'القسم', 1, 0, 'C', true);
        $this->pdf->Cell($colWidths['rev_cash'], 8, 'إيراد نقدي', 1, 0, 'C', true);
        $this->pdf->Cell($colWidths['rev_bank'], 8, 'إيراد بنكي', 1, 0, 'C', true);
        $this->pdf->Cell($colWidths['rev_total'], 8, 'إجمالي الإيراد', 1, 0, 'C', true);
        $this->pdf->Cell($colWidths['expense'], 8, 'المصروفات', 1, 0, 'C', true);
        $this->pdf->Cell($colWidths['net'], 8, 'صافي الإيراد', 1, 1, 'C', true);
        $this->pdf->SetTextColor(0, 0, 0);

        if (empty($rows)) {
            $this->pdf->SetFont('arial', 'B', 10);
            $this->pdf->SetFillColor(248, 249, 250);
            $this->pdf->SetTextColor(107, 114, 128);
            $this->pdf->Cell($usableWidth, 10, 'لا توجد بيانات لهذه الفترة', 1, 1, 'C', true);
            $this->pdf->SetTextColor(0, 0, 0);
        } else {
            $rowFill = false;
            foreach ($rows as $row) {
                $name = $row['name'].($row['is_lab'] ? ' (معمل)' : '');

                $this->pdf->SetFillColor($rowFill ? 252 : 255, $rowFill ? 252 : 255, $rowFill ? 253 : 255);
                $this->pdf->SetFont('arial', 'B', 9);
                $this->pdf->Cell($colWidths['name'], 7, $name, 1, 0, 'R', true);

                $this->pdf->SetFont('arial', '', 9);
                $this->pdf->Cell($colWidths['rev_cash'], 7, number_format($row['revenue']['cash'], 0), 1, 0, 'C', true);
                $this->pdf->Cell($colWidths['rev_bank'], 7, number_format($row['revenue']['bank'], 0), 1, 0, 'C', true);

                $this->pdf->SetFont('arial', 'B', 9);
                $this->pdf->Cell($colWidths['rev_total'], 7, number_format($row['revenue']['total'], 0), 1, 0, 'C', true);

                $this->pdf->SetFont('arial', '', 9);
                $this->pdf->Cell($colWidths['expense'], 7, number_format($row['expense']['total'], 0), 1, 0, 'C', true);

                $netColor = $row['net'] >= 0 ? [16, 120, 86] : [185, 28, 28];
                $this->pdf->SetFont('arial', 'B', 9);
                $this->pdf->SetTextColor($netColor[0], $netColor[1], $netColor[2]);
                $this->pdf->Cell($colWidths['net'], 7, number_format($row['net'], 0), 1, 1, 'C', true);
                $this->pdf->SetTextColor(0, 0, 0);

                $rowFill = ! $rowFill;
            }
        }

        // Totals row - emphasized
        $this->pdf->SetFont('arial', 'B', 10);
        $this->pdf->SetFillColor(240, 245, 250);
        $this->pdf->SetTextColor(45, 55, 72);
        $this->pdf->Cell($colWidths['name'], 8, 'الإجمالي', 1, 0, 'R', true);
        $this->pdf->Cell($colWidths['rev_cash'], 8, number_format($summary['revenue_cash'], 0), 1, 0, 'C', true);
        $this->pdf->Cell($colWidths['rev_bank'], 8, number_format($summary['revenue_bank'], 0), 1, 0, 'C', true);
        $this->pdf->Cell($colWidths['rev_total'], 8, number_format($summary['revenue_total'], 0), 1, 0, 'C', true);
        $this->pdf->Cell($colWidths['expense'], 8, number_format($summary['expense_total'], 0), 1, 0, 'C', true);

        $netColor = $summary['net'] >= 0 ? [16, 120, 86] : [185, 28, 28];
        $this->pdf->SetTextColor($netColor[0], $netColor[1], $netColor[2]);
        $this->pdf->Cell($colWidths['net'], 8, number_format($summary['net'], 0), 1, 1, 'C', true);
        $this->pdf->SetTextColor(0, 0, 0);

        $this->pdf->Ln(8);
    }

    private function renderUnassignedExpense(): void
    {
        $unassigned = $this->data['unassignedExpense'];
        if (($unassigned['total'] ?? 0) <= 0) {
            return;
        }

        $pageWidth = $this->pdf->getPageWidth();
        $margin = 15;
        $usableWidth = $pageWidth - (2 * $margin);

        $this->pdf->SetFillColor(255, 251, 235);
        $this->pdf->SetDrawColor(252, 211, 77);
        $this->pdf->SetLineWidth(0.3);
        $this->pdf->SetFont('arial', 'B', 9);
        $this->pdf->SetTextColor(146, 64, 14);
        $this->pdf->Cell($usableWidth, 9, 'مصروفات غير مرتبطة بأي قسم: '.number_format($unassigned['total'], 0), 1, 1, 'C', true);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Ln(4);
    }

    private function renderFooter(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $this->pdf->SetY(-18);

        $this->pdf->SetDrawColor(220, 223, 230);
        $this->pdf->SetLineWidth(0.3);
        $this->pdf->Line(15, $this->pdf->GetY() - 3, $pageWidth - 15, $this->pdf->GetY() - 3);

        $this->pdf->SetFont('arial', 'B', 8);
        $this->pdf->SetTextColor(107, 114, 128);
        $footerText = 'صفحة '.$this->pdf->getAliasNumPage().' من '.$this->pdf->getAliasNbPages().'  |  تم الإنشاء بواسطة نظام جوادة الطبي';
        $this->pdf->Cell(0, 6, $footerText, 0, 0, 'C');
        $this->pdf->SetTextColor(0, 0, 0);
    }

    public static function generateFromRequest(Request $request): array
    {
        try {
            $validated = $request->validate([
                'date_from' => 'required|date_format:Y-m-d',
                'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            ]);

            $dataResponse = app(ReportController::class)->departmentRevenueExpenses($request);
            $jsonData = json_decode($dataResponse->getContent(), true);

            $pdfData = [
                'title' => 'تقرير إيرادات ومصروفات الأقسام',
                'from' => $jsonData['report_period']['from'] ?? $validated['date_from'],
                'to' => $jsonData['report_period']['to'] ?? $validated['date_to'],
                'rows' => $jsonData['data'] ?? [],
                'summary' => $jsonData['summary'] ?? [
                    'revenue_cash' => 0, 'revenue_bank' => 0, 'revenue_total' => 0,
                    'expense_cash' => 0, 'expense_bank' => 0, 'expense_total' => 0,
                    'net' => 0,
                ],
                'unassignedExpense' => $jsonData['unassigned_expense'] ?? ['cash' => 0, 'bank' => 0, 'total' => 0],
            ];

            $report = new self($pdfData);
            $pdfContent = $report->generate();

            $pdfFileName = 'department-revenue-expenses-'.$pdfData['from'].'-to-'.$pdfData['to'].'.pdf';

            return [
                'content' => $pdfContent,
                'filename' => $pdfFileName,
                'status' => 200,
            ];
        } catch (\Exception $e) {
            Log::error('Department Revenue Expenses PDF Generation Error: '.$e->getMessage());

            return ['error' => $e->getMessage(), 'status' => 500];
        }
    }
}
