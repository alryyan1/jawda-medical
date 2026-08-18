<?php

namespace App\Services\Pdf;

use App\Models\DoctorShift;
use App\Models\Setting;
use Illuminate\Support\Facades\URL;
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

    // Formal, restrained palette — black text on white/gray, a single muted
    // navy reserved for the interactive "حصة الطبيب" link.
    protected const COLOR_TEXT = [20, 20, 20];

    protected const COLOR_MUTED = [90, 90, 90];

    protected const COLOR_LINK = [30, 60, 110];

    protected const COLOR_HEADER_FILL = [222, 222, 222];

    protected const COLOR_ALT_FILL = [244, 244, 244];

    protected const COLOR_BORDER = [0, 0, 0];

    public function __construct(DoctorShift $doctorShift)
    {
        // 'L' for Landscape, 'mm' for millimeters, 'A4' for page format
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);

        $this->doctorShift = $doctorShift;

        // Metadata
        $this->setCreator('Jawda Medical');
        $this->setAuthor('Jawda Medical System');
        $this->setTitle('تقرير مناوبة طبيب #'.$this->doctorShift->id);

        // Margins
        $this->setMargins(10, 36, 10); // L, T, R
        $this->setHeaderMargin(5);
        $this->setFooterMargin(12);

        // Auto page break
        $this->setAutoPageBreak(true, 15);

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
        if ($logo_name && file_exists($logo_path.'/'.$logo_name)) {
            $this->Image($logo_path.'/'.$logo_name, $this->getPageWidth() - 40, 5, 30);
        }

        $this->SetY(8);
        $this->setFont('arial', 'B', 16);
        $this->SetTextColorArray(self::COLOR_TEXT);
        $this->Cell($this->pageUsableWidth, 8, 'التقرير المالي لمناوبة الطبيب', 0, 1, 'C');

        $this->setFont('arial', '', 10);
        $this->SetTextColorArray(self::COLOR_MUTED);
        $doctorLabel = $this->doctorShift->doctor->name ?? '-';
        $specialistLabel = $this->doctorShift->doctor->specialist->name ?? null;
        $subtitle = 'د. '.$doctorLabel.($specialistLabel ? ' — '.$specialistLabel : '').'   |   مناوبة رقم '.$this->doctorShift->id;
        $this->Cell($this->pageUsableWidth, 6, $subtitle, 0, 1, 'C');

        $this->Ln(2);

        // --- Info bar: bordered form-style boxes ---
        $this->SetDrawColorArray(self::COLOR_BORDER);
        $this->SetLineWidth(0.15);
        $this->SetFillColor(250, 250, 250);
        $this->setFont('arial', '', 9.5);
        $this->SetTextColorArray(self::COLOR_TEXT);

        $fields = [
            'التاريخ: '.$this->doctorShift->created_at->format('Y-m-d'),
            'المستخدم: '.($this->doctorShift->user->username ?? '-'),
            'الطبيب: '.$doctorLabel,
            'وقت الفتح: '.$this->doctorShift->created_at->format('h:i A'),
        ];

        $colWidth = $this->pageUsableWidth / count($fields);
        foreach ($fields as $i => $text) {
            $this->Cell($colWidth, 8, $text, 1, $i === count($fields) - 1 ? 1 : 0, 'C', true);
        }

        $this->Ln(2);
    }

    /**
     * Custom Footer
     */
    public function Footer()
    {
        $this->SetY(-16);

        $this->SetDrawColorArray(self::COLOR_BORDER);
        $this->SetLineWidth(0.1);
        $this->Line(10, $this->GetY(), $this->getPageWidth() - 10, $this->GetY());
        $this->Ln(2);

        $this->setFont('arial', '', 8);
        $this->SetTextColorArray(self::COLOR_MUTED);
        $this->Cell(0, 8, 'صفحة '.$this->getAliasNumPage().' من '.$this->getAliasNbPages(), 0, 0, 'C');
        $this->Cell(0, 8, 'تم الإنشاء في: '.date('Y-m-d H:i:s'), 0, 0, 'L');
        $this->Cell(0, 8, 'Jawda Medical', 0, 0, 'R');
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

        return $this->Output('clinic_report_'.$this->doctorShift->id.'.pdf', 'S');
    }

    /**
     * Financial summary rendered as a plain two-row bordered table:
     * labels on top, values beneath — no color coding.
     */
    protected function renderFinancialSummary(): void
    {
        $visitsCount = $this->doctorShift->snap_patients_count;
        $cashCredit = $this->doctorShift->doctor_credit_cash();
        $companyCredit = $this->doctorShift->doctor_credit_company();
        $netCenter = $this->doctorShift->total_paid_services() - $cashCredit - $companyCredit;
        $cashPercentage = $this->doctorShift->snap_doctor_cash_percentage;
        $insurancePercentage = $this->doctorShift->snap_doctor_insurance_percentage;

        $labels = ['إجمالي المرضى', 'نسبة النقدي', 'نسبة التأمين', 'استحقاق نقدي', 'استحقاق تأمين', 'صافي المركز'];
        $values = [
            (string) $visitsCount,
            number_format($cashPercentage, 1).'%',
            number_format($insurancePercentage, 1).'%',
            number_format($cashCredit, 1),
            number_format($companyCredit, 1),
            number_format($netCenter, 1),
        ];

        $width = $this->pageUsableWidth / count($labels);

        $this->SetDrawColorArray(self::COLOR_BORDER);
        $this->SetLineWidth(0.15);

        // Label row
        $this->setFont('arial', 'B', 10);
        $this->SetFillColorArray(self::COLOR_HEADER_FILL);
        $this->SetTextColorArray(self::COLOR_TEXT);
        foreach ($labels as $i => $label) {
            $this->Cell($width, 8, $label, 1, $i === count($labels) - 1 ? 1 : 0, 'C', true);
        }

        // Value row
        $this->setFont('arial', 'B', 13);
        $this->SetFillColor(255, 255, 255);
        foreach ($values as $i => $value) {
            $this->Cell($width, 10, $value, 1, $i === count($values) - 1 ? 1 : 0, 'C', true);
        }

        $this->Ln(5);
    }

    protected function renderPatientsTable(): void
    {
        // Table Columns
        $cols = [
            ['w' => 10, 't' => 'رقم', 'a' => 'C'],
            ['w' => 42, 't' => 'اسم المريض', 'a' => 'C'],
            ['w' => 26, 't' => 'الشركة', 'a' => 'C'],
            ['w' => 20, 't' => 'إجمالي', 'a' => 'C'],
            ['w' => 20, 't' => 'نقداً', 'a' => 'C'],
            ['w' => 20, 't' => 'بنك', 'a' => 'C'],
            ['w' => 24, 't' => 'التخفيض', 'a' => 'C'],
            ['w' => 22, 't' => 'حصة الطبيب', 'a' => 'C'],
            ['w' => 22, 't' => 'صافي المركز', 'a' => 'C'],
            ['w' => 0,  't' => 'الخدمات *', 'a' => 'C'],
        ];

        $sumW = 0;
        foreach (array_slice($cols, 0, -1) as $c) {
            $sumW += $c['w'];
        }
        $cols[count($cols) - 1]['w'] = $this->pageUsableWidth - $sumW;

        // Draw Header
        $this->setFont('arial', 'B', 10);
        $this->SetFillColorArray(self::COLOR_HEADER_FILL);
        $this->SetTextColorArray(self::COLOR_TEXT);
        $this->SetDrawColorArray(self::COLOR_BORDER);
        $this->SetLineWidth(0.15);

        foreach ($cols as $c) {
            $this->Cell($c['w'], 9, $c['t'], 'TB', 0, 'C', true);
        }
        $this->Ln();

        // Data Rows
        $this->setFont('arial', '', 9);
        $this->SetLineWidth(0.1);

        $visits = $this->doctorShift->visits->reverse()->filter(fn ($v) => $v->only_lab == 0);

        $rowNum = 0;
        $alternateFillColor1 = [255, 255, 255];
        $alternateFillColor2 = self::COLOR_ALT_FILL;

        foreach ($visits as $visit) {
            $rowNum++;
            $currentFillColor = ($rowNum % 2 == 0) ? $alternateFillColor2 : $alternateFillColor1;
            $isInsurance = (bool) $visit->patient->company_id;
            $this->SetFillColorArray($currentFillColor);
            $this->SetTextColorArray(self::COLOR_TEXT);

            $h = 7;
            $currentDoctor = $this->doctorShift->doctor;

            $doctorCredit = $currentDoctor->doctor_credit($visit, $this->doctorShift);

            $totalDiscount = 0;
            $servicesHtml = '';

            foreach ($visit->requestedServices as $idx => $rs) {
                $serviceName = $rs->service?->name ?? 'خدمة غير معروفة';

                $servicesHtml .= $serviceName;

                if ($idx < count($visit->requestedServices) - 1) {
                    $servicesHtml .= ' - ';
                }

                $price = (float) $rs->price * (int) $rs->count;
                $totalDiscount += (float) $rs->discount;
                $totalDiscount += $price * ((int) ($rs->discount_per ?? 0) / 100);
            }

            $netCenter = $visit->total_paid_services() - $doctorCredit;

            // Signed URL for this specific visit's breakdown — valid 7 days, no auth token needed
            $visitBreakdownUrl = URL::signedRoute(
                'reports.doctor-credit-breakdown',
                ['doctorShift' => $this->doctorShift->id, 'visit' => $visit->id],
                now()->addDays(7)
            );

            $companyName = $visit->patient->company->name ?? '-';
            if ($isInsurance) {
                $companyName .= ' (تأمين)';
            }

            $rowData = [
                $visit->number,
                $visit->patient->name ?? '-',
                $companyName,
                number_format($visit->total_services($currentDoctor), 1),
                number_format($visit->total_paid_services() - $visit->bankak_service(), 1),
                number_format($visit->bankak_service(), 1),
                number_format($totalDiscount, 1),
                number_format($doctorCredit, 1),  // index 7 — will be rendered as clickable
                number_format($netCenter, 1),
            ];

            $startY = $this->GetY();

            // Calc max height for cell
            $servicesTextForHeight = strip_tags($servicesHtml);
            $servicesLines = $this->getNumLines($servicesTextForHeight, $cols[9]['w']);
            $rowHeight = max($h, $servicesLines * 5) + 2;

            // Check page break
            if ($startY + $rowHeight > $this->getPageHeight() - $this->getMargins()['bottom']) {
                $this->AddPage();
                $startY = $this->GetY();
            }

            foreach ($rowData as $i => $val) {
                $align = in_array($i, [3, 4, 5, 6, 7, 8]) ? 'C' : $cols[$i]['a'];

                if ($i === 7) {
                    // Cell() has a built-in $link param — simpler and RTL-safe
                    $this->SetTextColorArray(self::COLOR_LINK);
                    $this->setFont('arial', 'BU', 9);
                    $this->Cell($cols[$i]['w'], $rowHeight, $val, 'B', 0, 'C', true, $visitBreakdownUrl);
                    $this->setFont('arial', '', 9);
                    $this->SetTextColorArray(self::COLOR_TEXT);
                } elseif ($i === 2 && $isInsurance) {
                    $this->setFont('arial', 'I', 8.5);
                    $this->MultiCell($cols[$i]['w'], $rowHeight, $val, 'B', $align, true, 0, null, null, true, 0, false, true, $rowHeight, 'M');
                    $this->setFont('arial', '', 9);
                } else {
                    $this->MultiCell($cols[$i]['w'], $rowHeight, $val, 'B', $align, true, 0, null, null, true, 0, false, true, $rowHeight, 'M');
                }
            }

            // Services with dynamic height and HTML support for strikethrough
            $this->SetTextColorArray(self::COLOR_MUTED);
            $currentX = $this->GetX();
            $currentY = $this->GetY();

            // Draw background and border first for the services cell
            $this->Cell($cols[9]['w'], $rowHeight, '', 'B', 0, 'R', true);

            // Now write HTML content over it
            $this->SetXY($currentX, $currentY);
            $this->writeHTMLCell($cols[9]['w'], $rowHeight, $currentX, $currentY, $servicesHtml, 0, 1, false, true, 'R', true);

            $this->SetTextColorArray(self::COLOR_TEXT);
        }

        // Totals Row
        $this->setFont('arial', 'B', 10);
        $this->SetFillColorArray(self::COLOR_HEADER_FILL);
        $this->SetTextColorArray(self::COLOR_TEXT);
        $this->SetLineWidth(0.15);

        $totalServices = $this->doctorShift->total_services();
        $totalPaid = $this->doctorShift->total_paid_services();
        $totalBank = $this->doctorShift->total_bank();

        $grandDiscount = 0;
        foreach ($visits as $v) {
            foreach ($v->requestedServices as $rs) {
                $price = (float) $rs->price * (int) $rs->count;
                $grandDiscount += (float) $rs->discount;
                $grandDiscount += $price * ((int) ($rs->discount_per ?? 0) / 100);
            }
        }

        $totalDoctor = $this->doctorShift->doctor_credit_cash() + $this->doctorShift->doctor_credit_company();
        $totalNetCenter = $totalPaid - $totalDoctor;

        $this->Cell($cols[0]['w'] + $cols[1]['w'] + $cols[2]['w'], 9, 'الإجمالي العام للمناوبة', 'TB', 0, 'C', true);
        $this->Cell($cols[3]['w'], 9, number_format($totalServices, 1), 'TB', 0, 'C', true);
        $this->Cell($cols[4]['w'], 9, number_format($totalPaid - $totalBank, 1), 'TB', 0, 'C', true);
        $this->Cell($cols[5]['w'], 9, number_format($totalBank, 1), 'TB', 0, 'C', true);
        $this->Cell($cols[6]['w'], 9, number_format($grandDiscount, 1), 'TB', 0, 'C', true);
        $this->Cell($cols[7]['w'], 9, number_format($totalDoctor, 1), 'TB', 0, 'C', true);
        $this->Cell($cols[8]['w'], 9, number_format($totalNetCenter, 1), 'TB', 0, 'C', true);
        $this->Cell($cols[9]['w'], 9, '', 'TB', 1, 'C', true);

        // Hint below totals row
        $this->setFont('arial', 'I', 8);
        $this->SetTextColorArray(self::COLOR_MUTED);
        $this->Cell(0, 6, '* اضغط على خلية حصة الطبيب (نص مسطّر) في أي صف لفتح تفاصيل الاحتساب للمريض', 0, 1, 'R');
        $this->Ln(4);
    }
}
