<?php

namespace App\Services\Pdf;

use App\Models\DoctorShift;
use App\Models\DoctorVisit;
use App\Models\Setting;
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
        $this->setMargins(10, 35, 10); // L, T, R
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
        $settings = Setting::first();
        $logo_name = $settings?->header_base64;
        $logo_path = public_path();

        // Logo
        if ($logo_name && file_exists($logo_path . '/' . $logo_name)) {
            $this->Image($logo_path . '/' . $logo_name, $this->getPageWidth() - 40, 5, 30);
        }

        $this->SetY(10);
        $this->setFont('arial', 'B', 18);
        $this->SetTextColor(41, 98, 255); // Professional blue
        $this->Cell($this->pageUsableWidth, 10, 'التقرير المالي لمناوبة الطبيب', 0, 1, 'C');

        $this->setFont('arial', '', 10);
        $this->SetTextColor(50, 50, 50);

        $colWidth = $this->pageUsableWidth / 4;
        
        $this->SetY(22);
        // Add a light gray background for the header info block
        $this->SetFillColor(248, 249, 250);
        $this->SetDrawColor(220, 220, 220);
        
        $this->Cell($colWidth, 8, 'التاريخ: ' . $this->doctorShift->created_at->format('Y-m-d'), 'B', 0, 'R', true);
        $this->Cell($colWidth, 8, 'المستخدم: ' . ($this->doctorShift->user->username ?? '-'), 'B', 0, 'R', true);
        $this->Cell($colWidth, 8, 'الطبيب: ' . ($this->doctorShift->doctor->name ?? '-'), 'B', 0, 'R', true);
        $this->Cell($colWidth, 8, 'وقت الفتح: ' . $this->doctorShift->created_at->format('h:i A'), 'B', 1, 'R', true);

        $this->Ln(2);
    }

    /**
     * Custom Footer
     */
    public function Footer()
    {
        $this->SetY(-15);
        
        $this->SetDrawColor(220, 220, 220);
        $this->Line(10, $this->GetY(), $this->getPageWidth() - 10, $this->GetY());
        $this->Ln(2);

        $this->SetFont('arial', 'I', 8);
        $this->SetTextColor(127, 140, 141);
        $this->Cell(0, 10, 'صفحة ' . $this->getAliasNumPage() . ' من ' . $this->getAliasNbPages(), 0, 0, 'C');
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
        $this->setFont('arial', 'B', 12);
        $this->SetFillColor(240, 244, 248); // Very light blue
        $this->SetDrawColor(189, 195, 199);
        $this->SetTextColor(44, 62, 80);

        $visitsCount = $this->doctorShift->visits->where('only_lab', 0)->count();
        $cashCredit = $this->doctorShift->doctor_credit_cash();
        $companyCredit = $this->doctorShift->doctor_credit_company();

        $width = $this->pageUsableWidth / 3;
        
        // Add slightly taller rows for better aesthetics
        $this->Cell($width, 10, 'إجمالي المرضى: ' . $visitsCount, 1, 0, 'C', true);
        $this->Cell($width, 10, 'استحقاق نقدي: ' . number_format($cashCredit, 1), 1, 0, 'C', true);
        $this->Cell($width, 10, 'استحقاق تأمين: ' . number_format($companyCredit, 1), 1, 1, 'C', true);
        $this->Ln(6);
    }

    protected function renderPatientsTable()
    {
        $this->setFont('arial', 'B', 10);
        
        // Header colors
        $this->SetFillColor(230, 235, 240); // Professional header gray-blue
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(200, 205, 210);

        // Table Columns
        $cols = [
            ['w' => 15, 't' => 'رقم', 'a' => 'C'],
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

        // Draw Header
        foreach ($cols as $c) {
            $this->Cell($c['w'], 9, $c['t'], 1, 0, 'C', true);
        }
        $this->Ln();

        // Data Rows
        $this->setFont('arial', '', 9);

        $visits = $this->doctorShift->visits->reverse()->filter(fn($v) => $v->only_lab == 0);

        $rowNum = 0;
        $alternateFillColor1 = [255, 255, 255];
        $alternateFillColor2 = [248, 249, 250];

        foreach ($visits as $visit) {
            $rowNum++;
            $currentFillColor = ($rowNum % 2 == 0) ? $alternateFillColor2 : $alternateFillColor1;
            $this->SetFillColorArray($currentFillColor);
            
            if ($visit->patient->company_id) {
                $this->SetTextColor(192, 57, 43); // Alizarin Red for insurance
            } else {
                $this->SetTextColor(40, 40, 40); // Dark gray for regular text
            }

            $h = 7;
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

            $startX = $this->GetX();
            $startY = $this->GetY();
            
            // Calc max height for cell (services can take multiple lines)
            $servicesText = ltrim($visit->services_concatinated(), " -");
            $servicesLines = $this->getNumLines($servicesText, $cols[7]['w']);
            $rowHeight = max($h, $servicesLines * 5) + 2;

            // Check page break
            if ($startY + $rowHeight > $this->getPageHeight() - $this->getMargins()['bottom']) {
                $this->AddPage();
                $startY = $this->GetY();
                $startX = $this->GetX();
            }

            foreach ($rowData as $i => $val) {
                // If the value is a number, we might want to align it right
                $align = in_array($i, [3, 4, 5, 6]) ? 'C' : $cols[$i]['a'];
                $this->MultiCell($cols[$i]['w'], $rowHeight, $val, 'LRB', $align, true, 0, null, null, true, 0, false, true, $rowHeight, 'M');
            }

            // Services with dynamic height
            $this->SetTextColor(80, 80, 80); // Lighter text for services
            $this->MultiCell($cols[7]['w'], $rowHeight, $servicesText, 'RB', 'R', true, 1, null, null, true, 0, false, true, $rowHeight, 'M');

            $this->SetTextColor(40, 40, 40);
        }

        // Totals Row
        $this->setFont('arial', 'B', 10);
        $this->SetFillColor(230, 235, 240);
        $this->SetTextColor(44, 62, 80);
        
        $totalServices = $this->doctorShift->total_services();
        $totalPaid = $this->doctorShift->total_paid_services();
        $totalBank = $this->doctorShift->total_bank();
        $totalDoctor = $this->doctorShift->doctor_credit_cash() + $this->doctorShift->doctor_credit_company();

        $this->Cell($cols[0]['w'] + $cols[1]['w'] + $cols[2]['w'], 9, 'الإجمالي العام للمناوبة', 1, 0, 'C', true);
        $this->Cell($cols[3]['w'], 9, number_format($totalServices, 1), 1, 0, 'C', true);
        $this->Cell($cols[4]['w'], 9, number_format($totalPaid - $totalBank, 1), 1, 0, 'C', true);
        $this->Cell($cols[5]['w'], 9, number_format($totalBank, 1), 1, 0, 'C', true);
        $this->Cell($cols[6]['w'], 9, number_format($totalDoctor, 1), 1, 0, 'C', true);
        $this->Cell($cols[7]['w'], 9, '', 1, 1, 'C', true);
        $this->Ln(8);
    }

    protected function renderServiceCosts()
    {
        $costs = $this->doctorShift->shift_service_costs();
        if (empty($costs)) return;

        // Check if we need a new page for costs or if there's enough space
        if ($this->GetY() + 40 > $this->getPageHeight() - 15) {
            $this->AddPage();
        }

        $this->setFont('arial', 'B', 14);
        $this->SetTextColor(41, 98, 255); // Match header color
        $this->Cell(0, 10, 'تفاصيل مصروفات الخدمات المستقطعة', 0, 1, 'R');
        $this->Ln(2);

        $col1 = $this->pageUsableWidth * 0.75;
        $col2 = $this->pageUsableWidth * 0.25;

        $this->setFont('arial', 'B', 10);
        $this->SetFillColor(240, 244, 248);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(200, 205, 210);

        $this->Cell(40, 8, 'بيان مصروف الخدمة', 1, 0, 'C', true);
        $this->Cell(40, 8, 'المبلغ الإجمالي', 1, 1, 'C', true);

        $this->SetTextColor(40, 40, 40);
        $this->setFont('arial', '', 9);
        
        $totalCosts = 0;
        foreach ($costs as $cost) {
            $this->Cell(40, 8, $cost['name'], 1, 0, 'C', false);
            $this->Cell(40, 8, number_format($cost['amount'], 1), 1, 1, 'C', false);
            $totalCosts += $cost['amount'];
        }

        // Add a total row for service costs
        $this->setFont('arial', 'B', 10);
        $this->SetFillColor(245, 245, 245);
        $this->Cell(40, 8, 'الإجمالي', 1, 0, 'C', true);
        $this->Cell(40, 8, number_format($totalCosts, 1), 1, 1, 'C', true);
    }
}
