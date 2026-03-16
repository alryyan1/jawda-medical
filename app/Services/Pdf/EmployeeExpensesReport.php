<?php

namespace App\Services\Pdf;

use App\Models\EmployeeExpense;
use App\Models\Setting;
use App\Services\Pdf\MyCustomTCPDF;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EmployeeExpensesReport
{
    private $pdf;
    private $isRTL;
    private $date;

    public function handle(string $date): string
    {
        $this->date = $date;
        $expenses = EmployeeExpense::with(['employee.department', 'recorded_by'])
            ->whereDate('date', $date)
            ->get();

        $groupedExpenses = $expenses->groupBy(function($expense) {
            return $expense->employee->department->name ?? ($this->isRTL ? 'عام' : 'General');
        });

        if ($groupedExpenses->isEmpty()) {
            throw new \Exception('No expenses found for the selected date.');
        }

        return $this->generatePdf($groupedExpenses);
    }

    private function generatePdf($groupedExpenses): string
    {
        $this->pdf = new MyCustomTCPDF('Daily Employee Expenses', null, 'P', 'mm', 'A4', true, 'UTF-8', false, false, "Date: $this->date");
        
        // Remove headers/footers to save space and set tight margins
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(10, 10, 10); // L, T, R
        $this->pdf->SetAutoPageBreak(true, 10);
        
        $this->pdf->AddPage();
        $this->isRTL = $this->pdf->getRTL();

        $this->addReportHeader();

        $totalAll = 0;

        foreach ($groupedExpenses as $department => $expenses) {
            $deptTotal = $this->renderDepartmentTable($department, $expenses);
            $totalAll += $deptTotal;
            $this->pdf->Ln(5);
        }

        $this->addReportFooter($totalAll);

        return $this->pdf->Output('EmployeeExpenses_Report_' . $this->date . '.pdf', 'S');
    }

    private function addReportHeader()
    {
        $this->pdf->SetFont($this->pdf->getDefaultFontFamily(), 'B', 14);
        $title = $this->isRTL ? 'تقرير مصروفات الموظفين اليومية' : 'Daily Employee Expenses Report';
        $this->pdf->Cell(0, 8, $title, 0, 1, 'C');
        
        $this->pdf->SetFont($this->pdf->getDefaultFontFamily(), '', 10);
        $dateText = ($this->isRTL ? 'التاريخ: ' : 'Date: ') . $this->date;
        $this->pdf->Cell(0, 6, $dateText, 0, 1, 'C');
        $this->pdf->Ln(2);
    }

    private function renderDepartmentTable($department, $expenses): float
    {
        $this->pdf->SetFont($this->pdf->getDefaultFontFamily(), 'B', 11);
        $this->pdf->SetFillColor(245, 245, 245);
        $this->pdf->SetTextColor(0, 0, 0);
        
        $deptTitle = ($this->isRTL ? 'قسم: ' : 'Department: ') . $department;
        $this->pdf->Cell(0, 7, $deptTitle, 'TLR', 1, $this->isRTL ? 'R' : 'L', true);

        // Table Header
        $headers = $this->isRTL 
            ? ['الرقم', 'المسمى الوظيفي', 'الأسم', 'المبلغ', 'بنك', 'كاش', 'الوقت']
            : ['#', 'Job Title', 'Name', 'Amount', 'Bank', 'Cash', 'Time'];
        
        $colWidths = [12, 35, 48, 25, 20, 20, 30];
        $aligns = ['C', 'C', $this->isRTL ? 'R' : 'L', 'C', 'C', 'C', 'C'];
        
        $this->pdf->SetTableDefinition($headers, $colWidths, $aligns);
        $this->pdf->SetFillColor(230, 230, 230);
        $this->pdf->DrawTableHeader(null, null, null, 7);

        $this->pdf->SetFont($this->pdf->getDefaultFontFamily(), '', 10);
        $deptTotal = 0;
        $count = 1;

        foreach ($expenses as $expense) {
            $amount = $expense->amount;
            $deptTotal += $amount;

            $rowData = [
                $count++,
                $expense->employee->job_title ?? '-',
                $expense->employee->name,
                number_format($amount, 2),
                number_format($expense->bank_amount, 2),
                number_format($expense->cash_amount, 2),
                $expense->created_at->format('h:i A')
            ];
            
            $this->pdf->DrawTableRow($rowData, $colWidths, $aligns, false, 6, 10);
        }

        // Department Summary Row
        $this->pdf->SetFont($this->pdf->getDefaultFontFamily(), 'B', 10);
        $this->pdf->SetFillColor(240, 240, 240);
        $summaryRow = [
            $this->isRTL ? 'المجموع' : 'Total',
            '',
            '',
            number_format($deptTotal, 2),
            '',
            '',
            ''
        ];
        $this->pdf->DrawTableRow($summaryRow, $colWidths, $aligns, true, 7, 10);

        return $deptTotal;
    }

    private function addReportFooter($totalAll)
    {
        // Grand Total
        $this->pdf->Ln(5);
        $this->pdf->SetFillColor(220, 230, 241);
        $this->pdf->SetFont($this->pdf->getDefaultFontFamily(), 'B', 13);
        
        $grandTotalLabel = $this->isRTL ? 'إجمالي كشف الحوافز والبدلات' : 'Grand Total Incentives';
        $this->pdf->Cell(120, 12, $grandTotalLabel, 1, 0, $this->isRTL ? 'R' : 'L', true);
        $this->pdf->Cell(70, 12, number_format($totalAll, 2), 1, 1, 'C', true);

        // Signatures
        $this->pdf->Ln(15);
        $this->pdf->SetFont($this->pdf->getDefaultFontFamily(), 'B', 11);
        
        $width = 190 / 3;
        $this->pdf->Cell($width, 10, ($this->isRTL ? 'إعداد / شؤون الموظفين' : 'Prepared By'), 0, 0, 'C');
        $this->pdf->Cell($width, 10, ($this->isRTL ? 'المدير المالي' : 'Financial Manager'), 0, 0, 'C');
        $this->pdf->Cell($width, 10, ($this->isRTL ? 'المدير العام' : 'General Manager'), 0, 1, 'C');
        
        $this->pdf->Ln(10);
        $this->pdf->Cell($width, 10, '..........................', 0, 0, 'C');
        $this->pdf->Cell($width, 10, '..........................', 0, 0, 'C');
        $this->pdf->Cell($width, 10, '..........................', 0, 1, 'C');
    }
}
