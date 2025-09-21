<?php

namespace App\Services\Pdf;

use App\Models\DoctorVisit;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class LabThermalReceipt extends MyCustomTCPDF
{
    protected DoctorVisit $visit;
    protected array $labRequestsToPrint;
    protected Setting $appSettings;
    protected bool $isCompanyPatient;
    protected string $cashierName;
    protected string $fontName;
    protected bool $isRTL;
    protected string $alignStart;
    protected string $alignEnd;
    protected string $alignCenter;
    protected float $lineHeight;

    public function __construct(DoctorVisit $visit, array $labRequestsToPrint = [])
    {
        parent::__construct('إيصال مختبر', $visit);
        
        $this->visit = $visit;
        $this->labRequestsToPrint = $labRequestsToPrint ?: $visit->patientLabRequests->toArray();
        $this->appSettings = Setting::instance();
        $this->isCompanyPatient = !empty($visit->patient->company_id);
        $this->cashierName = Auth::user()?->name ?? $visit->user?->name ?? $this->labRequestsToPrint[0]['deposit_user']?->name ?? 'النظام';
        
        // Set thermal defaults
        $thermalWidth = (float) ($this->appSettings?->thermal_printer_width ?? 76);
        $this->setThermalDefaults($thermalWidth);
        
        // Set font and alignment properties
        $this->fontName = 'ae_alhor'; // Use the converted Arabic font
        $this->setRTL(true);
        $this->isRTL = true; // Always RTL for Arabic
        $this->alignStart = 'R'; // Right alignment for RTL
        $this->alignEnd = 'L'; // Left alignment for RTL
        $this->alignCenter = 'C';
        $this->lineHeight = 3.5;
    }

    public function generate(): string
    {
        $this->AddPage();
        $this->generateHeader();
        $this->generateReceiptInfo();
        $this->generateRequiredTests();
        $this->generateBarcode();
        $this->generateTotalsSection();
        // $this->generateWatermark();
        $this->generateFooter();

        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $this->visit->patient->name);
        $pdfFileName = 'LabReceipt_Visit_' . $this->visit->id . '_' . $patientNameSanitized . '.pdf';
        
        return $this->Output($pdfFileName, 'S');
    }

    protected function generateHeader(): void
    {
        // Logo
        $logoData = null;
        if ($this->appSettings?->logo_base64 && str_starts_with($this->appSettings->logo_base64, 'data:image')) {
            try {
                $logoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $this->appSettings->logo_base64));
            } catch (\Exception $e) {
                // Logo data invalid, continue without logo
            }
        }

        if ($logoData) {
            $this->Image('@' . $logoData, '', (float)($this->GetY() + 1), 15, 0, '', '', 'T', false, 300, $this->alignCenter, false, false, (float)0, false, false, false);
            $this->Ln($logoData ? 10 : 1);
        }

        // Hospital/Lab name
        $this->SetFont($this->fontName, 'B', $logoData ? 8 : 9);
        $this->MultiCell(0, $this->lineHeight, $this->appSettings?->hospital_name ?: ($this->appSettings?->lab_name ?: config('app.name')), 0, $this->alignCenter, false, 1);

        // Address and contact info
        $this->SetFont($this->fontName, '', 6);
        if ($this->appSettings?->address) {
            $this->MultiCell(0, $this->lineHeight - 0.5, $this->appSettings->address, 0, $this->alignCenter, false, 1);
        }
        if ($this->appSettings?->phone) {
            $this->MultiCell(0, $this->lineHeight - 0.5, "هاتف: " . $this->appSettings->phone, 0, $this->alignCenter, false, 1);
        }
        if ($this->appSettings?->vatin) {
            $this->MultiCell(0, $this->lineHeight - 0.5, "ر.ض: " . $this->appSettings->vatin, 0, $this->alignCenter, false, 1);
        }

        $this->Ln(1);
        $this->Cell(0, 0.1, '', 'T', 1, 'C');
        $this->Ln(1);
    }

    protected function generateReceiptInfo(): void
    {
        $this->SetFont($this->fontName, 'B', 12);
        
        // Patient name (first line, left aligned)
        $this->Cell(0, $this->lineHeight + 1, 'اسم المريض/ '.$this->visit->patient->name, 0, 1, $this->alignStart);
        
        // Doctor name (second line, left aligned)
        if ($this->visit->doctor) {
            $this->SetFont($this->fontName, '', 12);
            $this->Cell(0, $this->lineHeight, 'اسم الطبيب/ '.$this->visit->doctor->name, 0, 1, $this->alignStart);
        }
        $this->Ln(5);
        
        // Visit number in the middle and date on the right
        $this->SetFont($this->fontName, 'B', 10);
        $visitNumber = " الكود: " . $this->visit->patient->visit_number;
        $date = Carbon::now()->format('Y/m/d H:i A') . ' التاريخ ';
        
        // Calculate positions for center and right alignment
        $pageWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $visitNumberWidth = $this->GetStringWidth($visitNumber);
        $dateWidth = $this->GetStringWidth($date);
        
        // Position visit number in center
        $centerX = ($pageWidth - $visitNumberWidth) / 2;
        $this->SetX($this->getMargins()['left'] + $centerX);
        $this->Cell($visitNumberWidth, $this->lineHeight, $visitNumber, 0, 0, 'C');
        
        $this->Ln(5);
        $this->SetFont($this->fontName, '', size: 8);
        // Position date on the right
        // $this->SetX($this->getMargins()['left'] + $pageWidth - $dateWidth);
        $this->Cell($dateWidth, $this->lineHeight, $date, 0, 1, 'R');
        
        $this->Ln(5);
    }

    protected function generateBarcode(): void
    {
        if ($this->appSettings?->barcode && !empty($this->labRequestsToPrint[0]['id'])) {
            $this->Ln(5);
            $barcodeValue = (string) $this->labRequestsToPrint[0]['id'];
            $style = [
                'position' => '',
                'align' => 'C',
                'stretch' => false,
                'fitwidth' => true,
                'cellfitalign' => '',
                'border' => false,
                'hpadding' => 'auto',
                'vpadding' => 'auto',
                'fgcolor' => [0, 0, 0],
                'bgcolor' => false,
                'text' => true,
                'font' => $this->fontName,
                'fontsize' => 5,
                'stretchtext' => 4
            ];
            $this->write1DBarcode($barcodeValue, 'C128B', '', '', '', (float)10, (float)0.3, $style, 'N');
            $this->Ln(5);
        }

        $this->Ln(5);
        $this->Cell(0, 0.1, '', 'T', 1, 'C');
        $this->Ln(0.5);
    }

    protected function generateRequiredTests(): void
    {
        // Title: "الفحوصات المطلوبة"
        $this->SetFont($this->fontName, 'B', 15);
        $this->Cell(0, $this->lineHeight + 1, 'الفحوصات المطلوبة', 0, 1, $this->alignCenter);
        $this->Ln(1);

        // Collect all test names
        $testNames = [];
        foreach ($this->labRequestsToPrint as $lr) {
            $testName = $lr['main_test']['main_test_name'] ?? 'فحص غير معروف';
            $quantity = (int) ($lr['count'] ?? 1);
            
            // Add quantity if more than 1
            if ($quantity > 1) {
                $testName .= " (×{$quantity})";
            }
            
            $testNames[] = $testName;
        }

        // Join test names with commas and display in full width
        $allTestsText = implode('، ', $testNames);
        
        $this->SetFont($this->fontName, '', 7);
        $pageUsableWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $this->MultiCell($pageUsableWidth, $this->lineHeight, $allTestsText, 0, $this->alignStart, false, 1);

        $this->Ln(2);
        $this->Cell(0, 0.1, '', 'T', 1, 'C');
        $this->Ln(5);
    }

    protected function generateTotalsSection(): void
    {
        $this->SetFont($this->fontName, '', 10);
        $pageUsableWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];

        // Calculate totals
        $subTotalLab = 0;
        $totalDiscountOnLab = 0;
        $hasDiscount = false;

        foreach ($this->labRequestsToPrint as $lr) {
            $quantity = (int) ($lr['count'] ?? 1);
            $unitPrice = (float) ($lr['price'] ?? 0);
            $itemGrossTotal = $unitPrice * $quantity;
            $subTotalLab += $itemGrossTotal;

            $itemDiscountPercent = (float) ($lr['discount_per'] ?? 0);
            if ($itemDiscountPercent > 0) {
                $hasDiscount = true;
                $itemDiscountAmount = ($itemGrossTotal * $itemDiscountPercent) / 100;
                $totalDiscountOnLab += $itemDiscountAmount;
            }
        }

        $totalActuallyPaidForTheseLabs = array_sum(array_column($this->labRequestsToPrint, 'amount_paid'));

        // Display totals - simplified version
        $this->drawThermalTotalRow('الإجمالي:', $subTotalLab, $pageUsableWidth);
        
        // Only show discount if there's actually a discount
        if ($hasDiscount && $totalDiscountOnLab > 0) {
            $this->drawThermalTotalRow('الخصم:', -$totalDiscountOnLab, $pageUsableWidth);
        }

        $this->drawThermalTotalRow('المدفوع:', $totalActuallyPaidForTheseLabs, $pageUsableWidth);

        $this->Ln(5);
    }

    protected function generateWatermark(): void
    {
        if ($this->appSettings?->show_water_mark) {
            $pageUsableWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
            $this->SetFont($this->fontName, 'B', 30);
            $this->SetTextColor(220, 220, 220);
            $this->Rotate(45, $this->GetX() + ($pageUsableWidth / 3), $this->GetY() + 10);
            $this->Text($this->GetX() + ($pageUsableWidth / 4), $this->GetY(), $this->isCompanyPatient ? $this->visit->patient->company->name : "PAID");
            $this->Rotate(0);
            $this->SetTextColor(0, 0, 0);
        }
    }

    protected function generateFooter(): void
    {
        $this->Ln(3);
        $this->SetFont($this->fontName, 'I', 6);
        $footerMessage = $this->appSettings?->receipt_footer_message ?: 'شكراً لزيارتكم!';
        $this->MultiCell(0, $this->lineHeight - 1, $footerMessage, 0, $this->alignCenter, false, 1);
        $this->Ln(3);
    }

    protected function drawThermalTotalRow(string $label, float $amount, float $pageUsableWidth): void
    {
        $labelWidth = $pageUsableWidth * 0.60;
        $amountWidth = $pageUsableWidth * 0.40;

        $this->Cell($labelWidth, $this->lineHeight, $label, 0, 0, $this->alignStart);
        $this->Cell($amountWidth, $this->lineHeight, number_format($amount, 2), 0, 1, $this->alignEnd);
    }
}
