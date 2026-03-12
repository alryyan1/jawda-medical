<?php

namespace App\Services\Pdf;

use App\Models\RequestedSurgery;
use Carbon\Carbon;
use App\Models\Setting;
use TCPDF;

class SurgeryInvoiceA5 extends TCPDF
{
    protected RequestedSurgery $requestedSurgery;

    /** Professional color palette */
    private const COLOR_HEADER_BG = [30, 58, 95];      // Navy
    private const COLOR_BORDER = [180, 185, 190];
    private const COLOR_LABEL = [70, 75, 80];
    private const COLOR_ACCENT = [40, 90, 160];

    public function __construct(RequestedSurgery $requestedSurgery)
    {
        parent::__construct('P', 'mm', 'A5', true, 'UTF-8', false, false);

        $this->requestedSurgery = $requestedSurgery->load([
            'surgery',
            'doctor',
            'admission.patient',
            'user',
        ]);

        $settings = Setting::first();
        $this->SetCreator(config('app.name', 'Jawda'));
        $this->SetAuthor($settings?->hospital_name ?? config('app.name'));
        $this->SetTitle('فاتورة عملية جراحية');

        $this->setLanguageArray(['a_meta_charset' => 'UTF-8', 'a_meta_dir' => 'rtl', 'a_meta_language' => 'ar', 'w_page' => 'صفحة']);
        $this->setRTL(true);

        $this->SetMargins(15, 8, 15);
        $this->SetHeaderMargin(8);
        $this->SetFooterMargin(8);
        $this->SetAutoPageBreak(true, 20);
    }

    public function Header()
    {
        $settings = Setting::first();
        $logo_name = $settings?->header_base64;
        $logo_path = public_path();
        $pageWidth = $this->getPageWidth();
        $lMargin = $this->getMargins()['left'];
        $contentWidth = $pageWidth - $lMargin - $this->getMargins()['right'];

        // Top accent line
        $this->SetFillColor(...self::COLOR_HEADER_BG);
        $this->Rect(0, 0, $pageWidth, 3, 'F');

        $this->SetY(8);

        // Logos
        $logoSize = 20;
        if ($logo_name && file_exists($logo_path . '/' . $logo_name)) {
            $this->Image($logo_path . '/' . $logo_name, $lMargin, 10, $logoSize, $logoSize);
            $this->Image($logo_path . '/' . $logo_name, $pageWidth - $lMargin - $logoSize, 10, $logoSize, $logoSize);
        }

        // بسم الله الرحمن الرحيم
        $this->SetFont('arial', '', 10);
        $this->SetTextColor(90, 95, 100);
        $this->Cell(0, 5, 'بسم الله الرحمن الرحيم', 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);

        // Hospital name
        $hospitalName = $settings?->hospital_name ?? 'مركز ون كير التخصصي لجراحة اليوم الواحد';
        $hospitalNameEn = 'One Care Specialized Center for One Day-Surgery';

        $this->SetFont('arial', 'B', 13);
        $this->Cell(0, 6, $hospitalName, 0, 1, 'C');
        $this->SetFont('arial', '', 8);
        $this->SetTextColor(85, 90, 95);
        $this->Cell(0, 4, $hospitalNameEn, 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);

        // Invoice title block
        $this->Ln(2);
        $boxWidth = 35;
        $boxX = ($pageWidth - $boxWidth) / 2;
        $this->SetFillColor(248, 250, 252);
        $this->SetDrawColor(...self::COLOR_BORDER);
        $this->SetLineWidth(0.15);
        $this->RoundedRect($boxX, $this->GetY(), $boxWidth, 9, 1.5, '1111', 'DF');
        $this->SetY($this->GetY() + 2.5);
        $this->SetFont('arial', 'B', 11);
        $this->SetTextColor(...self::COLOR_HEADER_BG);
        $this->Cell(0, 5, 'فاتورة عملية جراحية', 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);
        $this->Ln(2);
    }

    public function generate()
    {
        $this->AddPage();

        $lMargin = $this->getMargins()['left'];
        $rMargin = $this->getMargins()['right'];
        $contentWidth = $this->getPageWidth() - $lMargin - $rMargin;
        $pageWidth = $this->getPageWidth();

        $patient = $this->requestedSurgery->admission->patient;
        $surgery = $this->requestedSurgery->surgery;
        $invoiceNo = 'INV-' . str_pad((string) $this->requestedSurgery->id, 6, '0', STR_PAD_LEFT);

        $this->SetY(55);

        // --- Invoice info & Patient details (two-column formal layout) ---
        $boxY = $this->GetY();
        $boxHeight = 28;
        $this->SetFillColor(250, 251, 252);
        $this->SetDrawColor(...self::COLOR_BORDER);
        $this->SetLineWidth(0.2);
        $this->RoundedRect($lMargin, $boxY, $contentWidth, $boxHeight, 2, '1111', 'DF');

        $colWidth = $contentWidth / 2;
        $labelW = 28;
        $rowH = 6;

        $this->SetY($boxY + 5);

        // Right column: Patient & Service
        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($labelW, $rowH, 'رقم الفاتورة:', 0, 0, 'R');
        $this->SetFont('arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($colWidth - $labelW - 5, $rowH, $invoiceNo, 0, 0, 'R');

        $this->SetX($lMargin + $colWidth + 5);
        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($labelW, $rowH, 'التاريخ:', 0, 0, 'R');
        $this->SetFont('arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($colWidth - $labelW - 5, $rowH, Carbon::now()->format('Y/m/d'), 0, 1, 'R');

        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($labelW, $rowH, 'اسم المريض:', 0, 0, 'R');
        $this->SetFont('arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($colWidth - $labelW - 5, $rowH, $patient->name, 0, 0, 'R');

        $this->SetX($lMargin + $colWidth + 5);
        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($labelW, $rowH, 'رقم المريض:', 0, 0, 'R');
        $this->SetFont('arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($colWidth - $labelW - 5, $rowH, (string) $patient->id, 0, 1, 'R');

        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($labelW, $rowH, 'نوع الخدمة:', 0, 0, 'R');
        $this->SetFont('arial', '', 10);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($contentWidth - $labelW - 5, $rowH, $surgery?->name ?? '—', 0, 1, 'R');

        $this->Ln(8);

        // --- Items Table ---
        $colItem = $contentWidth * 0.68;
        $colAmount = $contentWidth * 0.32;
        $widths = [$colItem, $colAmount];

        // Table header (formal navy)
        $this->SetFont('arial', 'B', 10);
        $this->SetFillColor(...self::COLOR_HEADER_BG);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(...self::COLOR_HEADER_BG);
        $this->SetLineWidth(0.25);
        $this->Cell($widths[0], 9, 'البيان', 1, 0, 'R', true);
        $this->Cell($widths[1], 9, 'المبلغ (SDG)', 1, 1, 'C', true);

        // Data row
        $surgeryName = $surgery?->name ?? '—';
        $initialPrice = (float) ($this->requestedSurgery->initial_price ?? 0);

        $this->SetTextColor(0, 0, 0);
        $this->SetFont('arial', '', 10);
        $this->SetFillColor(255, 255, 255);
        $this->SetDrawColor(...self::COLOR_BORDER);
        $this->SetLineWidth(0.15);
        $this->Cell($widths[0], 11, $surgeryName, 1, 0, 'R', true);
        $this->Cell($widths[1], 11, number_format($initialPrice, 0, '.', ','), 1, 1, 'C', true);

        // Total row
        $this->SetFont('arial', 'B', 11);
        $this->SetFillColor(245, 247, 250);
        $this->SetDrawColor(...self::COLOR_BORDER);
        $this->Cell($widths[0], 11, 'الإجمالي', 1, 0, 'R', true);
        $this->SetFont('arial', 'B', 12);
        $this->SetTextColor(...self::COLOR_ACCENT);
        $this->Cell($widths[1], 11, number_format($initialPrice, 0, '.', ',') . ' SDG', 1, 1, 'C', true);
        $this->SetTextColor(0, 0, 0);

        $this->Ln(14);

        // --- Signature block ---
        $sigWidth = $contentWidth / 2;
        $this->SetDrawColor(...self::COLOR_BORDER);
        $this->SetLineWidth(0.2);
        $this->Line($lMargin, $this->GetY(), $lMargin + $sigWidth, $this->GetY());
        $this->Ln(5);
        $this->SetFont('arial', '', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell(0, 5, 'إدارة مكتب التنويم وشؤون المرضى', 0, 1, 'C');
        $this->SetFont('arial', '', 7);
        $this->Cell(0, 4, 'Admissions Office and Patient Affairs Management', 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);

        $this->Ln(4);

        // --- Footer note ---
        $this->SetFont('arial', '', 7);
        $this->SetTextColor(110, 115, 120);
        $this->Cell(0, 4, 'نرجو من سيادتكم مراعاة الخصم والعروض الخاصة بالمؤسسات إن وجدت.', 0, 1, 'C');
        $this->SetTextColor(0, 0, 0);

        return $this->Output('surgery_invoice_' . $this->requestedSurgery->id . '.pdf', 'S');
    }
}
