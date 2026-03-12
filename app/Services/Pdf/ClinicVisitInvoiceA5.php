<?php

namespace App\Services\Pdf;

use App\Models\DoctorVisit;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use TCPDF;

class ClinicVisitInvoiceA5 extends TCPDF
{
    protected DoctorVisit $visit;

    private const COLOR_TEXT = [0, 0, 0];
    private const COLOR_LINE = [85, 85, 85];
    private const COLOR_LABEL = [45, 45, 45];
    private const FS_SMALL = 8;
    private const FS_NORMAL = 10;
    private const FS_TITLE = 13;
    private const FS_SUBTITLE = 11;

    public function __construct(DoctorVisit $visit)
    {
        parent::__construct('L', 'mm', 'A5', true, 'UTF-8', false, false);

        $this->visit = $visit->load([
            'patient.country',
            'doctor',
            'requestedServices.service',
            'createdByUser',
        ]);

        $settings = Setting::first();
        $this->SetCreator(config('app.name', 'Jawda'));
        $this->SetAuthor($settings?->hospital_name ?? config('app.name'));
        $this->SetTitle('فاتورة عيادة');

        $this->setLanguageArray(['a_meta_charset' => 'UTF-8', 'a_meta_dir' => 'rtl', 'a_meta_language' => 'ar', 'w_page' => 'صفحة']);
        $this->setRTL(true);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->setCellPaddings(0, 0, 0, 0);
        $this->setCellHeightRatio(1.1);

        $this->SetMargins(10, 8, 10);
        $this->SetAutoPageBreak(true, 10);
    }

    public function generate()
    {
        $this->AddPage();

        $lMargin = $this->getMargins()['left'];
        $rMargin = $this->getMargins()['right'];
        $contentWidth = $this->getPageWidth() - $lMargin - $rMargin;
        $pageWidth = (float) $this->getPageWidth();

        $patient = $this->visit->patient;
        $doctor = $this->visit->doctor;
        $settings = Setting::first();
        $logoName = $settings?->header_base64;
        $logoPath = public_path();

        // Visit date/time: prefer visit_date + visit_time, fallback to created_at
        $visitDateTime = $this->visit->visit_date
            ? Carbon::parse($this->visit->visit_date->format('Y-m-d') . ' ' . ($this->visit->visit_time ?? '00:00:00'))
            : Carbon::parse($this->visit->created_at);
        $visitDateTimeStr = $visitDateTime->format('Y/m/d h:i A');

        $fileNo = $patient->file_id ?? $patient->id;
        $patientCode = (string) $patient->id;
        $doctorName = $doctor?->name ?? '—';
        $patientName = $patient->name ?? '—';
        $nationality = $patient->country?->name ?? '—';
        $contactNo = $patient->phone ?? '—';
        $sexAndAge = ($patient->gender ?? '—') . ' & ' . ($patient->full_age ?? 'N/A');
        $printDateTime = Carbon::now()->format('Y/m/d h:i A');
        $printedBy = Auth::user()?->name ?? $this->visit->createdByUser?->name ?? '—';
        $hospitalName = $settings?->hospital_name ?? 'مركز ون كير التخصصي لجراحة اليوم الواحد';

        $clip = static fn (?string $text, int $max = 42): string => mb_strimwidth((string) ($text ?? '—'), 0, $max, '...');
        $money = static fn (float $value): string => number_format($value, 0, '.', '');

        $this->SetTextColor(...self::COLOR_TEXT);
        $this->SetDrawColor(...self::COLOR_LINE);
        $this->SetLineWidth(0.18);

        // Apply logo behavior the same way as LabResultReport settings-driven logic.
        $this->addLogoFromSettings($settings, $logoName, $logoPath, $contentWidth);

        // Top timestamp (left) and titles (center)
        $this->SetFont('arial', '', self::FS_SMALL);
        $this->Cell($contentWidth, 4, $visitDateTimeStr, 0, 1, 'L');
        $this->SetFont('arial', 'B', self::FS_TITLE);
        $this->Cell($contentWidth, 7, $hospitalName, 0, 1, 'C');
        $this->SetFont('arial', '', self::FS_SUBTITLE);
        $this->Cell($contentWidth, 6, 'Invoice فاتوره', 0, 1, 'C');
        $this->Line($lMargin, $this->GetY(), $lMargin + $contentWidth, $this->GetY());
        $this->Ln(2);

        // Two-column details block
        $colGap = 6;
        $colWidth = ($contentWidth - $colGap) / 2;
        $rowH = 5.6;
        $engLabelW = 27;
        $engValueW = $colWidth - $engLabelW;
        $arLabelW = 27;
        $arValueW = $colWidth - $arLabelW;

        $leftRows = [
            ['File No :', (string) $fileNo],
            ['P. Name :', $patientName],
            ['Nationality :', $nationality],
            ['Contact No :', $contactNo],
            ['Sex & Age :', $sexAndAge],
        ];
        $rightRows = [
            ['كود المريض', $patientCode],
            ['الطبيب', $doctorName],
            ['التاريخ و الزمن', $visitDateTimeStr],
            ['', ''],
            ['', ''],
        ];

        for ($i = 0; $i < 5; $i++) {
            // Left (English)
            $this->SetFont('arial', 'B', 9);
            $this->SetTextColor(...self::COLOR_LABEL);
            $this->Cell($engLabelW, $rowH, $leftRows[$i][0], 0, 0, 'L');
            $this->SetFont('arial', '', self::FS_NORMAL);
            $this->SetTextColor(...self::COLOR_TEXT);
            $this->Cell($engValueW, $rowH, $clip((string) $leftRows[$i][1], 36), 0, 0, 'L');

            // Gap between columns
            $this->Cell($colGap, $rowH, '', 0, 0, 'L');

            // Right (Arabic)
            $this->SetFont('arial', 'B', 9);
            $this->SetTextColor(...self::COLOR_LABEL);
            $this->Cell($arLabelW, $rowH, $rightRows[$i][0], 0, 0, 'R');
            $this->SetFont('arial', '', self::FS_NORMAL);
            $this->SetTextColor(...self::COLOR_TEXT);
            $this->Cell($arValueW, $rowH, $clip((string) $rightRows[$i][1], 34), 0, 1, 'R');
        }

        // Requested services table should follow strict left-to-right column flow
        // to keep Name/Price headers and values in the correct columns.
        $this->setRTL(false);

        // Requested services heading
        $this->Ln(2);
        $this->SetFont('arial', 'B', self::FS_SUBTITLE);
        $this->SetTextColor(...self::COLOR_TEXT);
        $this->Cell($colWidth, 5, 'Requested Services', 0, 0, 'C');
        $this->Cell($colGap, 5, '', 0, 0, 'L');
        $this->Cell($colWidth, 5, 'الخدمات المطلوبة', 0, 1, 'C');

        // Services table (plain lines, no fills)
        $tableY = $this->GetY();
        $nameW = $contentWidth - 42;
        $priceW = 42;
        $tableX = $lMargin;
        $tableEndX = $tableX + $contentWidth;
        $nameEndX = $tableX + $nameW;

        $this->Line($tableX, $tableY, $tableEndX, $tableY);
        $this->SetFont('arial', 'B', self::FS_NORMAL);
        $this->Cell($nameW, 5, 'Name', 0, 0, 'C');
        $this->Cell($priceW, 5, 'Price', 0, 1, 'C');
        $tableY = $this->GetY();
        $this->Line($tableX, $tableY, $tableEndX, $tableY);
        $this->SetFont('arial', 'B', 9);
        $this->Cell($nameW, 5, 'الاسم', 0, 0, 'C');
        $this->Cell($priceW, 5, 'السعر', 0, 1, 'C');
        $tableY = $this->GetY();
        $this->Line($tableX, $tableY, $tableEndX, $tableY);

        $grandTotal = 0;
        $totalDiscount = 0;
        $totalPaid = 0;
        $rowY = $this->GetY();

        foreach ($this->visit->requestedServices as $rs) {
            $serviceName = $rs->service?->name ?? '—';
            $price = (float) ($rs->price ?? 0);
            $count = (int) ($rs->count ?? 1);
            $lineTotal = $price * $count;

            $discountPer = (float) ($rs->discount_per ?? 0);
            $discountFixed = (float) ($rs->discount ?? 0);
            $lineDiscount = ($lineTotal * $discountPer / 100) + $discountFixed;
            $amountPaid = (float) ($rs->amount_paid ?? 0);

            $grandTotal += $lineTotal;
            $totalDiscount += $lineDiscount;
            $totalPaid += $amountPaid;

            $this->SetFont('arial', '', 9);
            $this->Cell($nameW, 7, $clip($serviceName, 52), 0, 0, 'C');
            $this->Cell($priceW, 7, $money($lineTotal), 0, 1, 'C');
            $rowY = $this->GetY();
            $this->Line($tableX, $rowY, $tableEndX, $rowY);
        }

        if ($this->visit->requestedServices->isEmpty()) {
            $this->Ln(7);
            $rowY = $this->GetY();
            $this->Line($tableX, $rowY, $tableEndX, $rowY);
        }

        $this->Line($tableX, $tableY - 10, $tableX, $rowY);
        $this->Line($nameEndX, $tableY - 10, $nameEndX, $rowY);
        $this->Line($tableEndX, $tableY - 10, $tableEndX, $rowY);

        // Bottom info blocks
        $this->Ln(5);
        $y = $this->GetY();
        $this->SetFont('arial', '', self::FS_NORMAL);

        // Left: print metadata (rendered in-row using Cell, no absolute positioning)
        $metaEngW = 33;
        $metaValW = 34;
        $metaArW = 25;
        $metaBlockW = $metaEngW + $metaValW + $metaArW;

        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($metaEngW, 6, 'Print Date & Time', 0, 0, 'L');
        $this->SetFont('arial', '', 9);
        $this->SetTextColor(...self::COLOR_TEXT);
        $this->Cell($metaValW, 6, $clip($printDateTime, 22), 0, 0, 'L');
        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($metaArW, 6, 'تاريخ الطباعة والزمن', 0, 0, 'R');

        $sumEngW = 26;
        $sumValW = 20;
        $sumArW = 32;
        $sumBlockW = $sumEngW + $sumValW + $sumArW;
        $gapW = max(0, $contentWidth - $metaBlockW - $sumBlockW);

        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($gapW, 6, '', 0, 0, 'L');
        $this->Cell($sumEngW, 6, 'Grand Total', 0, 0, 'L');
        $this->SetFont('arial', '', 9);
        $this->SetTextColor(...self::COLOR_TEXT);
        $this->Cell($sumValW, 6, $money($grandTotal), 0, 0, 'R');
        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($sumArW, 6, 'المبلغ الاجمالي', 0, 1, 'R');

        // Row 2
        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($metaEngW, 6, 'Printed By', 0, 0, 'L');
        $this->SetFont('arial', '', 9);
        $this->SetTextColor(...self::COLOR_TEXT);
        $this->Cell($metaValW, 6, $clip($printedBy, 20), 0, 0, 'L');
        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($metaArW, 6, 'طبعت بواسطه', 0, 0, 'R');

        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($gapW, 6, '', 0, 0, 'L');
        $this->Cell($sumEngW, 6, 'Discount', 0, 0, 'L');
        $this->SetFont('arial', '', 9);
        $this->SetTextColor(...self::COLOR_TEXT);
        $this->Cell($sumValW, 6, $money($totalDiscount), 0, 0, 'R');
        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($sumArW, 6, 'الخصم', 0, 1, 'R');

        // Row 3 (totals only, leave left block blank)
        $this->Cell($metaBlockW + $gapW, 6, '', 0, 0, 'L');
        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($sumEngW, 6, 'Paid Amount', 0, 0, 'L');
        $this->SetFont('arial', '', 9);
        $this->SetTextColor(...self::COLOR_TEXT);
        $this->Cell($sumValW, 6, $money($totalPaid), 0, 0, 'R');
        $this->SetFont('arial', 'B', 9);
        $this->SetTextColor(...self::COLOR_LABEL);
        $this->Cell($sumArW, 6, 'المبلغ المدفوع', 0, 1, 'R');

        // Barcode at bottom center
        $barcodeValue = (string) $this->visit->id;
        $barcodeY = max($y + 20, $this->getPageHeight() - 26);
        $barcodeW = 38;
        $barcodeX = ($pageWidth - $barcodeW) / 2;
        $style = ['position' => 'S', 'align' => 'C', 'stretch' => false, 'fitwidth' => true, 'cellfitalign' => '', 'border' => false];
        $this->write1DBarcode($barcodeValue, 'C128', $barcodeX, $barcodeY, $barcodeW, 11, 0.38, $style, 'N');
        $this->SetFont('courier', '', 8);
        $this->SetTextColor(...self::COLOR_TEXT);
        $this->MultiCell($barcodeW, 4, $barcodeValue, 0, 'C', 0, 1, $barcodeX, $barcodeY + 11.5, true);

        // Restore RTL default for any future content in this document class.
        $this->setRTL(true);

        return $this->Output('clinic_invoice_visit_' . $this->visit->id . '.pdf', 'S');
    }

    /**
     * Settings-driven logo placement (adapted from LabResultReport::addLogo).
     */
    private function addLogoFromSettings(?Setting $settings, ?string $logoName, string $logoPath, float $pageWidth): void
    {
        if (!$settings || !$logoName) {
            return;
        }

        $fullLogoPath = $logoPath . DIRECTORY_SEPARATOR . $logoName;
        if (!file_exists($fullLogoPath)) {
            return;
        }

        // Determine visibility using the same rule set used in LabResultReport.
        if ($settings->show_logo !== null) {
            $shouldShow = (bool) $settings->show_logo;
        } else {
            $shouldShow = (bool) $settings->is_logo || (bool) $settings->is_header;
        }
        if (!$shouldShow) {
            return;
        }

        $type = $settings->pdf_header_type ?? ($settings->is_logo ? 'logo' : 'full_width');

        if ($type === 'logo') {
            // Backward-compatible behavior: if old "is_logo" mode and no explicit positioning, print both sides.
            if ($settings->is_logo && !isset($settings->pdf_header_logo_position)) {
                $this->Image($fullLogoPath, 5, 5, 24, 24);
                $this->Image($fullLogoPath, $this->getPageWidth() - 29, 5, 24, 24);
                return;
            }

            $position = $settings->pdf_header_logo_position ?? 'left';
            $width = (float) ($settings->pdf_header_logo_width ?? 24);
            $height = (float) ($settings->pdf_header_logo_height ?? 24);
            $xOffset = (float) ($settings->pdf_header_logo_x_offset ?? 5);
            $yOffset = (float) ($settings->pdf_header_logo_y_offset ?? 5);

            $x = $position === 'right'
                ? ($this->getPageWidth() - $this->getMargins()['right'] - $width - $xOffset)
                : ($this->getMargins()['left'] + $xOffset);

            $this->Image(
                $fullLogoPath,
                $x,
                $yOffset,
                $width,
                $height,
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
            return;
        }

        if ($type === 'full_width') {
            $width = (float) ($settings->pdf_header_image_width ?? ($pageWidth + 10));
            $height = (float) ($settings->pdf_header_image_height ?? 18);
            $xOffset = (float) ($settings->pdf_header_image_x_offset ?? 0);
            $yOffset = (float) ($settings->pdf_header_image_y_offset ?? 5);

            $this->Image(
                $fullLogoPath,
                $xOffset,
                $yOffset,
                $width,
                $height,
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
}
