<?php

namespace App\Services\Pdf;

use App\Models\DoctorShift;
use App\Models\DoctorVisit;
use TCPDF;

/**
 * ClinicShiftReport
 * 
 * A professional and compact PDF report for doctor shifts.
 */
class ClinicShiftReport extends TCPDF
{
    protected DoctorShift $doctorShift;
    protected float $pageUsableWidth;

    public function __construct(DoctorShift $doctorShift)
    {
        // 'L' for Landscape, 'mm' for millimeters, 'A4' for page format
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);

        $this->doctorShift = $doctorShift;

        // Metadata
        $this->setCreator('Jawda Medical');
        $this->setAuthor('Jawda Medical System');
        $this->setTitle('تقرير مناوبة طبيب #' . $this->doctorShift->id);

        // Margins
        $this->setMargins(10, 25, 10); // L, T, R
        $this->setHeaderMargin(5);
        $this->setFooterMargin(10);

        // Auto page break
        $this->setAutoPageBreak(TRUE, 15);

        // Language settings (Arabic support)
        $this->setLanguageArray([
            'a_meta_charset' => 'UTF-8',
            'a_meta_dir' => 'rtl',
            'a_meta_language' => 'ar',
            'w_page' => 'صفحة',
        ]);
        $this->setRTL(true);

        $this->pageUsableWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
    }

    /**
     * Custom Header
     */
    public function Header()
    {
        $this->setFont('arial', 'B', 16);
        $this->SetTextColor(44, 62, 80); // Professional Dark Blue
        $this->Cell($this->pageUsableWidth, 10, 'التقرير المالي لمناوبة الطبيب', 0, 1, 'C');

        $this->setFont('arial', '', 9);
        $this->SetTextColor(0, 0, 0);

        $colWidth = $this->pageUsableWidth / 4;
        $this->Cell($colWidth, 6, 'التاريخ: ' . $this->doctorShift->created_at->format('Y-m-d'), 0, 0, 'R');
        $this->Cell($colWidth, 6, 'المستخدم: ' . ($this->doctorShift->user->username ?? '-'), 0, 0, 'R');
        $this->Cell($colWidth, 6, 'الطبيب: ' . ($this->doctorShift->doctor->name ?? '-'), 0, 0, 'R');
        $this->Cell($colWidth, 6, 'وقت الفتح: ' . $this->doctorShift->created_at->format('h:i A'), 0, 1, 'R');

        $this->SetDrawColor(189, 195, 199);
        $this->Line(10, $this->GetY(), 10 + $this->pageUsableWidth, $this->GetY());
        $this->Ln(2);
    }

    /**
     * Custom Footer
     */
    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('arial', 'I', 8);
        $this->SetTextColor(127, 140, 141);
        $this->Cell(0, 10, 'صفحة ' . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 0, 0, 'C');
        $this->Cell(0, 10, 'تم الإنشاء في: ' . date('Y-m-d H:i:s'), 0, 0, 'L');
    }

    /**
     * Main report generation entry point
     */
    public function generate(): string
    {
        $this->AddPage();

        // --- 1. Financial Summary Block ---
        $this->renderFinancialSummary();

        // --- 2. Patients Table ---
        $this->renderPatientsTable();

        // --- 3. Service Costs Section ---
        $this->renderServiceCosts();

        return $this->Output('clinic_report_' . $this->doctorShift->id . '.pdf', 'S');
    }

    protected function renderFinancialSummary()
    {
        $this->setFont('arial', 'B', 10);
        $this->SetDrawColor(189, 195, 199);

        $visitsCount = $this->doctorShift->visits->where('only_lab', 0)->count();
        $cashCredit = $this->doctorShift->doctor_credit_cash();
        $companyCredit = $this->doctorShift->doctor_credit_company();

        $width = $this->pageUsableWidth / 3;
        $this->Cell($width, 8, 'إجمالي المرضى: ' . $visitsCount, 1, 0, 'C', false);
        $this->Cell($width, 8, 'استحقاق نقدي: ' . number_format($cashCredit, 1), 1, 0, 'C', false);
        $this->Cell($width, 8, 'استحقاق تأمين: ' . number_format($companyCredit, 1), 1, 1, 'C', false);
        $this->Ln(4);
    }

    protected function renderPatientsTable()
    {
        $this->setFont('arial', 'B', 9);
        $this->SetTextColor(0, 0, 0); // Changed to Black

        // Table Columns
        $cols = [
            ['w' => 12, 't' => 'رقم', 'a' => 'C'],
            ['w' => 50, 't' => 'اسم المريض', 'a' => 'C'],
            ['w' => 35, 't' => 'الشركة', 'a' => 'C'],
            ['w' => 22, 't' => 'إجمالي', 'a' => 'C'],
            ['w' => 22, 't' => 'نقداً', 'a' => 'C'],
            ['w' => 22, 't' => 'بنك', 'a' => 'C'],
            ['w' => 25, 't' => 'حصة الطبيب', 'a' => 'C'],
            ['w' => 0,  't' => 'الخدمات *', 'a' => 'C'],
        ];

        $sumW = 0;
        foreach (array_slice($cols, 0, -1) as $c) $sumW += $c['w'];
        $cols[count($cols) - 1]['w'] = $this->pageUsableWidth - $sumW;

        // Header
        foreach ($cols as $c) {
            $this->Cell($c['w'], 8, $c['t'], 1, 0, 'C', false);
        }
        $this->Ln();

        // Data Rows
        $this->setFont('arial', '', 8.5);

        $visits = $this->doctorShift->visits->reverse()->filter(fn($v) => $v->only_lab == 0);

        foreach ($visits as $visit) {
            if ($visit->patient->company_id) {
                $this->SetTextColor(192, 57, 43); // Alizarin Red for insurance
            }

            $h = 6;
            $currentDoctor = $this->doctorShift->doctor;

            $rowData = [
                $visit->number,
                $visit->patient->name ?? '-',
                $visit->patient->company->name ?? '-',
                number_format($visit->total_services($currentDoctor), 1),
                number_format($visit->total_paid_services() - $visit->bankak_service(), 1),
                number_format($visit->bankak_service(), 1),
                number_format($currentDoctor->doctor_credit($visit), 1),
            ];

            foreach ($rowData as $i => $val) {
                $this->Cell($cols[$i]['w'], $h, $val, 'LRB', 0, $cols[$i]['a'], false);
            }

            // Services with dynamic height
            $this->MultiCell($cols[7]['w'], $h, $visit->services_concatinated(), 'RB', 'R', false, 1, $this->GetX(), $this->GetY(), true, 0, false, true, $h, 'M');

            $this->SetTextColor(0, 0, 0);
        }

        // Totals Row
        $this->setFont('arial', 'B', 9);

        $totalServices = $this->doctorShift->total_services();
        $totalPaid = $this->doctorShift->total_paid_services();
        $totalBank = $this->doctorShift->total_bank();
        $totalDoctor = $this->doctorShift->doctor_credit_cash() + $this->doctorShift->doctor_credit_company();

        $this->Cell($cols[0]['w'] + $cols[1]['w'] + $cols[2]['w'], 8, 'الإجمالي العام للمناوبة', 1, 0, 'C', false);
        $this->Cell($cols[3]['w'], 8, number_format($totalServices, 1), 1, 0, 'C', false);
        $this->Cell($cols[4]['w'], 8, number_format($totalPaid - $totalBank, 1), 1, 0, 'C', false);
        $this->Cell($cols[5]['w'], 8, number_format($totalBank, 1), 1, 0, 'C', false);
        $this->Cell($cols[6]['w'], 8, number_format($totalDoctor, 1), 1, 0, 'C', false);
        $this->Cell($cols[7]['w'], 8, '', 1, 1, 'C', false);
        $this->Ln(5);
    }

    protected function renderServiceCosts()
    {
        $costs = $this->doctorShift->shift_service_costs();
        if (empty($costs)) return;

        // Check if we need a new page for costs or if there's enough space
        if ($this->GetY() + 40 > $this->getPageHeight() - 15) {
            $this->AddPage();
        }

        $this->setFont('arial', 'B', 12);
        $this->SetTextColor(44, 62, 80);
        $this->Cell(0, 10, 'تفصيل مصروفات الخدمات المستقطعة', 0, 1, 'R');
        $this->Ln(1);

        $col1 = $this->pageUsableWidth * 0.75;
        $col2 = $this->pageUsableWidth * 0.25;

        $this->setFont('arial', 'B', 10);
        $this->SetTextColor(0, 0, 0); // Changed to black
        $this->Cell(30, 8, 'بيان مصروف الخدمة', 1, 0, 'C', false);
        $this->Cell(30, 8, 'المبلغ الإجمالي', 1, 1, 'C', false);

        $this->SetTextColor(0, 0, 0);
        $this->setFont('arial', '', 9);
        foreach ($costs as $cost) {
            $this->Cell(30, 7, $cost['name'], 1, 0, 'C', false);
            $this->Cell(30, 7, number_format($cost['amount'], 1), 1, 1, 'C', false);
        }
    }
}
