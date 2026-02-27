<?php

namespace App\Services\Pdf;

use App\Models\RequestedSurgery;
use Carbon\Carbon;
use App\Models\Setting;

class SurgeryFinanceReport extends MyCustomTCPDF
{
    protected RequestedSurgery $requestedSurgery;

    public function __construct(RequestedSurgery $requestedSurgery)
    {
        parent::__construct(
            'توزيع نسب العمليات',
            null,
            'P',
            'mm',
            'A4',
            true,
            'UTF-8',
            false
        );

        $this->requestedSurgery = $requestedSurgery->load(['surgery', 'doctor', 'admission.patient', 'finances.financeCharge', 'finances.doctor']);

        $this->SetMargins(15, 5, 15);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(5);
        $this->SetAutoPageBreak(true, 5);
    }

    public function Header()
    {
        /** @var Setting $settings */
        $settings = Setting::first();
        $logo_name = $settings?->header_base64;
        $logo_path = public_path();
        $contentWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];

        // Add Logo implementation from LabResultReport
        $this->addLogo($this, $settings, $logo_name, $logo_path, $contentWidth, false);

        $this->SetFont('arial', '', 18, '', true);

        $this->Ln(45);
        $this->SetFont('arial', 'B', 16);
        $this->Cell(0, 10, 'تقرير توزيع نسب العمليات', 0, 1, 'C');
    }

    public function generate()
    {
        $this->AddPage();

        $isRTL = $this->getRTL();
        $lMargin = $this->getMargins()['left'];
        $rMargin = $this->getMargins()['right'];
        $contentWidth = $this->getPageWidth() - $lMargin - $rMargin;

        $patient = $this->requestedSurgery->admission->patient;
        $surgery = $this->requestedSurgery->surgery;

        /** @var Setting $settings */
        $settings = Setting::first();
        $footer_name = $settings?->footer_base64;
        $logo_path = public_path();

        // Logo is now handled in Header() which is called by AddPage()

        // --- Info Block (Professional Box styling) ---
        $this->SetY(65);
        $boxY = $this->GetY();
        $boxHeight = 22;

        $this->SetFillColor(248, 249, 250); // Light gray background
        $this->SetDrawColor(220, 224, 228); // Subtle border
        $this->SetLineWidth(0.3);
        $this->RoundedRect($lMargin, $boxY, $contentWidth, $boxHeight, 2, '1111', 'DF');

        $this->SetY($boxY + 3);
        $this->SetFont('arial', 'B', 10);
        // Dark gray for labels

        $colWidth = ($contentWidth / 2) - 5;
        $labelWidth = 25;
        $rowHeight = 7;

        // Row 1: Patient Name & Date
        $this->Cell($labelWidth, $rowHeight, 'اسم المريض:', 0, 0, 'R');
        $this->SetFont('arial', 'B', 13);
        $this->SetTextColor(0, 0, 0); // Black for values
        $this->Cell($colWidth - $labelWidth, $rowHeight, $patient->name, 0, 0, 'R');

        $this->SetX($lMargin + $colWidth + 10);
        $this->SetFont('arial', 'B', 10);

        $this->Cell($labelWidth, $rowHeight, 'التاريخ:', 0, 0, 'R');
        $this->SetFont('arial', '', 13);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($colWidth - $labelWidth, $rowHeight, Carbon::now()->format('Y/m/d'), 0, 1, 'R');

        // Row 2: Surgery Type & Price
        $this->SetFont('arial', 'B', 10);

        $this->Cell($labelWidth, $rowHeight, 'نوع العملية:', 0, 0, 'R');
        $this->SetFont('arial', 'B', 13);
        $this->SetTextColor(0, 0, 0);
        $this->Cell($colWidth - $labelWidth, $rowHeight, $surgery->name, 0, 0, 'R');

        $this->SetX($lMargin + $colWidth + 10);
        $this->SetFont('arial', 'B', 10);

        $this->Cell($labelWidth, $rowHeight, 'قيمة العملية:', 0, 0, 'R');
        $this->SetFont('arial', 'B', 12);
        $this->Cell($colWidth - $labelWidth, $rowHeight, number_format($this->requestedSurgery->total_price, 0) . ' SDG', 0, 1, 'R');

        $this->SetTextColor(0, 0, 0); // Reset text color
        $this->SetY($boxY + $boxHeight + 8);

        // --- Finances Table ---
        $headers = ['البند / التكلفة', 'الطبيب / المستفيد', 'المبلغ (SDG)', 'التوقيع', 'ملاحظات'];
        $widths = [
            $contentWidth * 0.30,
            $contentWidth * 0.30,
            $contentWidth * 0.15,
            $contentWidth * 0.12,
            $contentWidth * 0.13
        ];
        $alignments = ['R', 'R', 'C', 'C', 'C'];

        $this->SetTableDefinition($headers, $widths, $alignments);

        // Custom Table Header (Dark Theme)
        $this->SetFont('arial', 'B', 10);
        $this->SetFillColor(230, 235, 240); // Soft blue-gray header
        $this->SetTextColor(30, 40, 50);
        $this->SetDrawColor(200, 205, 210);
        $this->SetLineWidth(0.2);

        for ($i = 0; $i < count($headers); ++$i) {
            $this->Cell($widths[$i], 9, $headers[$i], 1, 0, $alignments[$i], 1);
        }
        $this->Ln(9);

        // Table Rows
        $this->SetTextColor(0, 0, 0);
        $fillColor = [252, 253, 255]; // Ultra light alternating row
        $fill = false;
        $totalAmount = 0;

        foreach ($this->requestedSurgery->finances as $finance) {
            $rowData = [
                $finance->financeCharge->name,
                $finance->doctor ? $finance->doctor->name : '-',
                number_format($finance->amount, 0),
                '',
                ''
            ];
            $this->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
            $this->DrawTableRow($rowData, null, null, $fill, 8, 10);
            $fill = !$fill;
            $totalAmount += $finance->amount;
        }

        // --- Summary Row (Totals) ---
        $this->Ln(1); // Small gap before totals
        $this->SetFont('arial', 'B', 12);

        // Draw the "Total" label spanning 2 columns
        // $this->SetFillColor(41, 98, 255);
        $this->SetTextColor(255, 255, 255); // White text on blue background
        $this->Cell($widths[0] + $widths[1], 10, 'الإجمالي', 1, 0, 'C', true);

        // Draw the total value
        $this->SetFillColor(240, 244, 248); // Light blue background for value
        $this->SetTextColor(0, 0, 0);
        $this->Cell($widths[2], 10, number_format($totalAmount, 0), 1, 0, 'C', true);

        // Empty cells for remaining space
        $this->SetFillColor(255, 255, 255);
        $this->Cell($widths[3], 10, '', 1, 0, 'C', true);
        $this->Cell($widths[4], 10, '', 1, 1, 'C', true);

        $this->Ln(5);

        // --- Signatures Area ---
        $this->SetFont('arial', 'B', 13);
        $this->SetTextColor(50, 50, 50);

        $sigBlockWidth = 80;

        // Manager Signature (Left side in LTR, Right side in RTL)
        // $this->Ln(5);
        $this->Cell(30, 5, 'إعتماد المدير العام:', 0, 1, 'C');
        $this->Cell(30, 5, '........................................', 0, 1, 'C');

        // Add Footer using LabResultReport implementation
        // $this->renderFooter($this, $patient, $contentWidth, $settings, $footer_name, $logo_path);

        return $this->Output('surgery_report_' . $this->requestedSurgery->id . '.pdf', 'S');
    }

    private function addLogo($pdf, $settings, $logo_name, $logo_path, $page_width, $base64, bool $isWhatsappContext = false): void
    {
        if (!$settings) return;

        // Determine visibility
        $shouldShow = true;
        if ($settings->show_logo_only_whatsapp) {
            $shouldShow = (bool)$isWhatsappContext;
        } elseif ($settings->show_logo !== null) {
            $shouldShow = (bool)$settings->show_logo;
        } else {
            $shouldShow = (bool)$settings->is_logo || (bool)$settings->is_header;
        }

        if (!$shouldShow) return;

        $type = $settings->pdf_header_type ?? ($settings->is_logo ? 'logo' : 'full_width');

        if ($type === 'logo') {
            $position = $settings->pdf_header_logo_position ?? 'left';
            $width = $settings->pdf_header_logo_width ?? 40;
            $height = $settings->pdf_header_logo_height ?? 40;
            $xOffset = $settings->pdf_header_logo_x_offset ?? 5;
            $yOffset = $settings->pdf_header_logo_y_offset ?? 5;

            $x = ($position === 'right') ? ($pdf->getPageWidth() - PDF_MARGIN_RIGHT - $width - $xOffset) : (PDF_MARGIN_LEFT + $xOffset - 10); // Adjusting for internal TCPDF relative position

            // If we are using old defaults where it was 5 on both sides
            if ($settings->is_logo && !isset($settings->pdf_header_logo_position)) {
                // Classic behavior
                $pdf->Image($logo_path . '/' . $logo_name, 5, 5, 40, 40);
                $pdf->Image($logo_path . '/' . $logo_name, 165, 5, 40, 40);
            } else {
                $pdf->Image(
                    $logo_path . '/' . $logo_name,
                    200,
                    0,
                    100,
                    50,
                    '',
                    '',
                    '',
                    true,
                    200,
                    '',
                    false,
                    false,
                    0,
                    false,
                    false,
                    false
                );
            }
        } elseif ($type === 'full_width') {
            $width = $settings->pdf_header_image_width ?? ($page_width + 10);
            $height = $settings->pdf_header_image_height ?? 30;
            $xOffset = $settings->pdf_header_image_x_offset ?? 10;
            $yOffset = $settings->pdf_header_image_y_offset ?? 10;

            $pdf->Image(
                $logo_path . '/' . $logo_name,
                0,
                7,
                $width + 10,
                30,
                '',
                '',
                '',
                true,
                300,
                '',
                false,
                false,
                0,
                false,
                false,
                false
            );
        }
    }

    private function renderFooter($pdf, $patient, $page_width, $settings, $footer_name, $logo_path): void
    {
        $this->SetY(-35); // Position at bottom
        $pdf->SetFont('arial', '', 9, '', true);

        $col = $page_width / 6;
        $user = auth()->user();
        $pdf->cell(20, 5, "Sign: ", 0, 1, 'L');
        $pdf->cell($col, 5, $user ? $user->name : 'System', 0, 0, 'L');
        $pdf->cell($col, 5, " ", 0, 0, 'L');
        $pdf->cell($col, 5, " ", 0, 0, 'L');
        $pdf->cell($col, 5, " ", 0, 0, 'L');
        $pdf->cell($col, 5, "No ", 0, 0, 'R');
        $pdf->cell($col, 5, $patient->visit_number ?? $patient->id, 0, 1, 'C');

        if ($settings?->footer_content != null) {
            $pdf->SetFont('arial', '', 10, '', true);
            $pdf->MultiCell($page_width - 25, 5, $settings->footer_content, 0, 'C', 0, 1, '', '', true);
        }

        $y = $pdf->getY();
        if ($settings?->is_footer && $footer_name) {
            $pdf->Image($logo_path . '/' . $footer_name, 10, $y + 5, $page_width + 10, 10);
        }
    }
}
