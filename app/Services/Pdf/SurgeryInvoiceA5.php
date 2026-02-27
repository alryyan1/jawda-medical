<?php

namespace App\Services\Pdf;

use App\Models\RequestedSurgery;
use Carbon\Carbon;
use App\Models\Setting;

class SurgeryInvoiceA5 extends MyCustomTCPDF
{
    protected RequestedSurgery $requestedSurgery;

    public function __construct(RequestedSurgery $requestedSurgery)
    {
        parent::__construct(
            'فاتورة عملية جراحية',
            null,
            'P',
            'mm',
            'A5', // A5 Format
            true,
            'UTF-8',
            false
        );

        $this->requestedSurgery = $requestedSurgery->load(['surgery', 'doctor', 'admission.patient', 'user']);

        $this->SetMargins(10, 5, 10);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(5);
        $this->SetAutoPageBreak(true, 10);
    }

    public function Header()
    {
        /** @var Setting $settings */
        $settings = Setting::first();
        $logo_name = $settings?->header_base64;
        $logo_path = public_path();
        $contentWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];

        // Add Logo implementation
        $this->addLogo($this, $settings, $logo_name, $logo_path, $contentWidth, false);

        $this->SetFont('arial', '', 14, '', true);

        $this->Ln(30);
        $this->SetFont('arial', 'B', 14);
        $this->Cell(0, 10, 'فاتورة عملية جراحية', 0, 1, 'C');
    }

    public function generate()
    {
        $this->AddPage();

        $lMargin = $this->getMargins()['left'];
        $contentWidth = $this->getPageWidth() - $lMargin - $this->getMargins()['right'];

        $patient = $this->requestedSurgery->admission->patient;
        $surgery = $this->requestedSurgery->surgery;

        $this->SetY(45);

        // --- Box for Details ---
        $this->SetFillColor(248, 249, 250); // Light gray background
        $this->SetDrawColor(220, 224, 228); // Subtle border
        $this->SetLineWidth(0.3);
        $this->RoundedRect($lMargin, $this->GetY(), $contentWidth, 32, 2, '1111', 'DF');

        $this->SetY($this->GetY() + 3);
        $this->SetFont('arial', 'B', 10);

        $rowHeight = 6;
        $labelWidth = 25;
        $colWidth = ($contentWidth / 2) - 5;

        // Row 1: Patient Name & ID
        $this->Cell($labelWidth, $rowHeight, 'اسم المريض:', 0, 0, 'R');
        $this->SetFont('arial', 'B', 11);
        $this->Cell($colWidth - $labelWidth, $rowHeight, $patient->name, 0, 0, 'R');

        $this->SetX($lMargin + $colWidth + 10);
        $this->SetFont('arial', 'B', 10);
        $this->Cell($labelWidth, $rowHeight, 'رقم المريض:', 0, 0, 'R');
        $this->SetFont('arial', '', 11);
        $this->Cell($colWidth - $labelWidth, $rowHeight, $patient->id, 0, 1, 'R');

        // Row 2: Doctor & Date
        $this->SetFont('arial', 'B', 10);
        $this->Cell($labelWidth, $rowHeight, 'الطبيب:', 0, 0, 'R');
        $this->SetFont('arial', '', 11);
        $this->Cell($colWidth - $labelWidth, $rowHeight, $this->requestedSurgery->doctor?->name ?? '—', 0, 0, 'R');

        $this->SetX($lMargin + $colWidth + 10);
        $this->SetFont('arial', 'B', 10);
        $this->Cell($labelWidth, $rowHeight, 'التاريخ:', 0, 0, 'R');
        $this->SetFont('arial', '', 11);
        $this->Cell($colWidth - $labelWidth, $rowHeight, Carbon::now()->format('Y/m/d H:i'), 0, 1, 'R');

        // Row 3: User
        $this->SetFont('arial', 'B', 10);
        $this->Cell($labelWidth, $rowHeight, 'بواسطة:', 0, 0, 'R');
        $this->SetFont('arial', '', 11);
        $this->Cell($colWidth - $labelWidth, $rowHeight, $this->requestedSurgery->user?->name ?? 'System', 0, 1, 'R');

        $this->Ln(8);

        // --- Invoice Items Table ---
        $headers = ['البيان (اسم العملية)', 'المبلغ الإجمالي (SDG)'];
        $widths = [
            $contentWidth * 0.65,
            $contentWidth * 0.35
        ];
        $alignments = ['R', 'C'];

        $this->SetTableDefinition($headers, $widths, $alignments);

        // Custom Table Header (Dark Theme)
        $this->SetFont('arial', 'B', 10);
        $this->SetFillColor(230, 235, 240); // Soft blue-gray header
        $this->SetTextColor(30, 40, 50);
        $this->SetDrawColor(200, 205, 210);
        $this->SetLineWidth(0.2);

        for ($i = 0; $i < count($headers); ++$i) {
            $this->Cell($widths[$i], 8, $headers[$i], 1, 0, $alignments[$i], 1);
        }
        $this->Ln(8);

        // Table Rows
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('arial', 'B', 12);

        $this->SetFillColor(252, 253, 255);
        $this->Cell($widths[0], 12, $surgery->name, 1, 0, 'R', true);

        $this->SetFont('arial', 'B', 13);
        $this->SetTextColor(0, 50, 0); // Dark green for price
        $this->Cell($widths[1], 12, number_format($this->requestedSurgery->total_price, 0), 1, 1, 'C', true);

        // --- Summary Row (Totals) ---
        $this->Ln(1); // Small gap before totals
        $this->SetFont('arial', 'B', 11);

        $this->SetTextColor(255, 255, 255);
        $this->SetFillColor(0, 100, 200); // Blue for "Total" row header
        $this->Cell($widths[0], 10, 'الإجمالي الكلي', 1, 0, 'C', true);

        $this->SetFillColor(240, 244, 248);
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('arial', 'B', 14);
        $this->Cell($widths[1], 10, number_format($this->requestedSurgery->total_price, 0) . ' SDG', 1, 1, 'C', true);

        $this->Ln(15);

        // --- Bottom Section ---
        $this->SetFont('arial', 'B', 10);
        $this->Cell($contentWidth / 2, 5, 'توقيع المستلم', 0, 0, 'C');
        $this->Cell($contentWidth / 2, 5, 'الختم الرسمي', 0, 1, 'C');

        return $this->Output('surgery_invoice_' . $this->requestedSurgery->id . '.pdf', 'S');
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

            // Simple handling for A5 dimensions
            if ($settings->is_logo && !isset($settings->pdf_header_logo_position)) {
                $pdf->Image($logo_path . '/' . $logo_name, 5, 5, 30, 30);
                $pdf->Image($logo_path . '/' . $logo_name, 113, 5, 30, 30);
            } else {
                $pdf->Image(
                    $logo_path . '/' . $logo_name,
                    150,
                    0,
                    50,
                    30,
                    '',
                    '',
                    '',
                    true,
                    150,
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
            $height = $settings->pdf_header_image_height ?? 20;
            $xOffset = $settings->pdf_header_image_x_offset ?? 10;
            $yOffset = $settings->pdf_header_image_y_offset ?? 5;

            $pdf->Image(
                $logo_path . '/' . $logo_name,
                0,
                5,
                $width + 10,
                20,
                '',
                '',
                '',
                true,
                150,
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
}
