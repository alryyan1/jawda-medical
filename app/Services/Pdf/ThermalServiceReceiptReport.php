<?php

namespace App\Services\Pdf;

use App\Models\DoctorVisit;
use App\Models\Setting;
use App\Services\Pdf\MyCustomTCPDF; // existing custom TCPDF wrapper
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ThermalServiceReceiptReport
{
    /**
     * Generate thermal service receipt PDF content and filename for a visit.
     *
     * @return array{content:string, filename:string}
     */
    public function generate(DoctorVisit $visit): array
    {
        $visit->load([
            'patient:id,name,phone,company_id',
            'patient.company:id,name',
            'requestedServices.service:id,name,price',
            'doctor:id,name',
        ]);

        $appSettings = Setting::instance();
        $isCompanyPatient = !empty($visit->patient->company_id);

        $pdf = new MyCustomTCPDF('إيصال خدمات', $visit);
        $pdf->setThermalDefaults((float) ($appSettings?->thermal_printer_width ?? 76));
        $pdf->AddPage();

        $fontName = $pdf->getDefaultFontFamily();
        $isRTL = $pdf->getRTL();
        $alignRight =  'R';
        $alignLeft = $isRTL ? 'R' : 'L';
        $alignCenter = 'C';

        // Header
        $logoData = null;
        if ($appSettings?->logo_base64 && str_starts_with($appSettings->logo_base64, 'data:image')) {
            try {
                $logoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $appSettings->logo_base64));
            } catch (\Exception $e) {
            }
        }
        if ($logoData) {
            $pdf->Image('@' . $logoData, '', '', 15, 0, '', '', 'T', false, 300, $alignCenter, false, false, 0, false, false, false);
            $pdf->Ln(1);
        }
        $pdf->SetFont($fontName, 'B', $logoData ? 9 : 10);
        $pdf->MultiCell(0, 4, $appSettings?->hospital_name ?: ($appSettings?->lab_name ?: config('app.name')), 0, $alignCenter, false, 1);
        $pdf->SetFont($fontName, '', 6.5);
        if ($appSettings?->address) $pdf->MultiCell(0, 3, $appSettings->address, 0, $alignCenter, false, 1);
        if ($appSettings?->phone) $pdf->MultiCell(0, 3, "الهاتف: " . $appSettings->phone, 0, $alignCenter, false, 1);
        if ($appSettings?->vatin) $pdf->MultiCell(0, 3, "رقم ضريبي: " . $appSettings->vatin, 0, $alignCenter, false, 1);

        $pdf->Ln(1);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(1);

        // Receipt info
        $pdf->SetFont($fontName, '', 7.5);
        $receiptNumber = $visit->id;
        $pdf->Cell(0, 3.5, "رقم الفاتورة: " . $receiptNumber, 0, 1, $alignRight);
        $pdf->Cell(0, 3.5, "رقم الانتظار: " . $visit->number, 0, 1, $alignRight);
        $pdf->Cell(0, 3.5, "التاريخ: " . Carbon::now()->format('Y/m/d H:i A'), 0, 1, $alignRight);
        $pdf->Cell(0, 3.5, "المريض: " . $visit->patient->name, 0, 1, $alignRight);
        if ($visit->patient->phone) $pdf->Cell(0, 3.5, "الهاتف: " . $visit->patient->phone, 0, 1, $alignRight);
        if ($isCompanyPatient && $visit->patient->company) $pdf->Cell(0, 3.5, "الشركة: " . $visit->patient->company->name, 0, 1, $alignRight);
        if ($visit->doctor) $pdf->Cell(0, 3.5, "الطبيب: " . $visit->doctor->name, 0, 1, $alignRight);
        $pdf->Cell(0, 3.5, "الكاشير: " . (Auth::user()?->name ?? 'النظام'), 0, 1, $alignRight);

        if ($appSettings?->barcode) {
            $pdf->Ln(2);
            $style = [
                'position' => '', 'align' => 'C', 'stretch' => false, 'fitwidth' => true, 'cellfitalign' => '',
                'border' => false, 'hpadding' => 'auto', 'vpadding' => 'auto', 'fgcolor' => [0,0,0], 'bgcolor' => false,
                'text' => true, 'font' => $pdf->getDefaultFontFamily(), 'fontsize' => 6, 'stretchtext' => 4,
            ];
            $pdf->write1DBarcode((string) $visit->id, 'C128B', '', '', '', 12, 0.3, $style, 'N');
            $pdf->Ln(1);
        }

        $pdf->Ln(2);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(1);

        // Items header
        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $nameWidth = $pageUsableWidth * 0.48;
        $qtyWidth = $pageUsableWidth * 0.12;
        $priceWidth = $pageUsableWidth * 0.20;
        $totalWidth = $pageUsableWidth * 0.20;
        $pdf->SetFont($fontName, 'B', 7);
        $pdf->Cell($nameWidth, 4, 'البيان', 'B', 0, 'R');
        $pdf->Cell($qtyWidth, 4, 'كمية', 'B', 0, 'C');
        $pdf->Cell($priceWidth, 4, 'سعر', 'B', 0, 'C');
        $pdf->Cell($totalWidth, 4, 'إجمالي', 'B', 1, 'C');
        $pdf->SetFont($fontName, '', 7);

        $subTotalServices = 0;
        $totalDiscountOnServices = 0;
        $totalEnduranceOnServices = 0;

        foreach ($visit->requestedServices as $rs) {
            $serviceName = $rs->service?->name ?? 'خدمة غير معروفة';
            $quantity = (int) ($rs->count ?? 1);
            $unitPrice = (float) ($rs->price ?? 0);
            $itemGrossTotal = $unitPrice * $quantity;
            $subTotalServices += $itemGrossTotal;

            $itemDiscountPercent = (float) ($rs->discount_per ?? 0);
            $itemDiscountFixed = (float) ($rs->discount ?? 0);
            $itemDiscountAmount = (($itemGrossTotal * $itemDiscountPercent) / 100) + $itemDiscountFixed;
            $totalDiscountOnServices += $itemDiscountAmount;

            $itemNetAfterDiscount = $itemGrossTotal - $itemDiscountAmount;
            $itemEndurance = 0;
            if ($isCompanyPatient) {
                $itemEndurance = (float) ($rs->endurance ?? 0) * $quantity;
                $totalEnduranceOnServices += $itemEndurance;
            }

            $pdf->MultiCell($nameWidth, 3.5, $serviceName, 0, 'R', false, 0, '', '', true, 0, false, true, 0, 'T');
            $currentY = $pdf->GetY();
            $pdf->SetXY($pdf->getMargins()['left'] + $nameWidth, $currentY);
            $pdf->Cell($qtyWidth, 3.5, $quantity, 0, 0, 'C');
            $pdf->Cell($priceWidth, 3.5, number_format($unitPrice, 2), 0, 0, 'C');
            $pdf->Cell($totalWidth, 3.5, number_format($itemGrossTotal, 2), 0, 1, 'C');
            $pdf->SetY(max($pdf->GetY(), $currentY + ($pdf->getNumLines($serviceName, $nameWidth) * 3.5)));
        }

        $pdf->Ln(1);
        $pdf->Cell(0, 0.1, '', 'T', 1, 'C');
        $pdf->Ln(1);

        // Totals
        $pdf->SetFont($fontName, '', 7.5);
        $this->drawThermalTotalRow($pdf, 'إجمالي الخدمات:', $subTotalServices, $pageUsableWidth);
        if ($totalDiscountOnServices > 0) {
            $this->drawThermalTotalRow($pdf, 'إجمالي الخصم:', -$totalDiscountOnServices, $pageUsableWidth, 'text-red-500');
        }

        $netAfterDiscount = $subTotalServices - $totalDiscountOnServices;
        $companyWillPayOnFuture = $netAfterDiscount - ($isCompanyPatient ? $totalEnduranceOnServices : 0);
        if ($isCompanyPatient) {
            $this->drawThermalTotalRow($pdf, 'تحمل الشركة:', -$companyWillPayOnFuture, $pageUsableWidth, 'text-blue-500');
        }

        $pdf->SetFont($fontName, 'B', 8.5);
        $this->drawThermalTotalRow($pdf, 'صافي المطلوب من المريض:', $totalEnduranceOnServices, $pageUsableWidth, true);
        $pdf->SetFont($fontName, '', 7.5);

        $totalPaidByPatient = $visit->requestedServices->sum('amount_paid');
        $this->drawThermalTotalRow($pdf, 'المبلغ المدفوع:', $totalPaidByPatient, $pageUsableWidth);

        $pdf->SetFont($fontName, 'B', 8.5);
        $this->drawThermalTotalRow($pdf, 'المبلغ المتبقي للدفع:', $visit->amountRemaining(), $pageUsableWidth, ($visit->amountRemaining() != 0));

        $pdf->Ln(2);
        $pdf->Ln(3);
        $pdf->SetFont($fontName, 'I', 6.5);
        $footerMessage = $appSettings?->receipt_footer_message ?: 'شكراً لزيارتكم!';
        $pdf->MultiCell(0, 3, $footerMessage, 0, 'C', false, 1);
        $pdf->Ln(5);

        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $visit->patient->name);
        $pdfFileName = 'Receipt_Visit_' . $visit->id . '_' . $patientNameSanitized . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');

        return [
            'content' => $pdfContent,
            'filename' => $pdfFileName,
        ];
    }

    private function drawThermalTotalRow(MyCustomTCPDF $pdf, string $label, float $value, float $pageUsableWidth, bool $bold = false): void
    {
        $labelWidth = $pageUsableWidth * 0.55;
        $valueWidth = $pageUsableWidth * 0.45;
        $font = $pdf->getDefaultFontFamily();
        $pdf->SetFont($font, $bold ? 'B' : '', $bold ? 8.5 : 7.5);
        $pdf->Cell($labelWidth, 4, $label, 0, 0, 'R');
        $pdf->Cell($valueWidth, 4, number_format($value, 2), 0, 1, 'C');
    }
}


