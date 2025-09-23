<?php

namespace App\Services\Pdf;

use App\Models\DoctorVisit;
use App\Models\Service;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class ThermalServiceReceiptReport extends MyCustomTCPDF
{
    protected DoctorVisit $visit;
    protected array $requestedServicesToPrint;
    protected Setting $appSettings;
    protected bool $isCompanyPatient;
    protected string $cashierName;
    protected string $fontName;
    protected bool $isRTL;
    protected string $alignStart;
    protected string $alignEnd;
    protected string $alignCenter;
    protected float $lineHeight;

    public function __construct(DoctorVisit $visit, array $requestedServicesToPrint = [])
    {
        parent::__construct('إيصال خدمات', $visit);
        
        $this->visit = $visit;
        $this->requestedServicesToPrint = $requestedServicesToPrint ?: $visit->requestedServices->load('service')->toArray();
        // \Log::info('ThermalServiceReceiptReport constructor', ['requestedServicesToPrint' => $this->requestedServicesToPrint]);
        $this->appSettings = Setting::instance();
        $this->isCompanyPatient = !empty($visit->patient->company_id);
        $this->cashierName = Auth::user()?->name ?? $visit->user?->name ?? 'النظام';
        
        // Set thermal defaults
        $thermalWidth = (float) ($this->appSettings?->thermal_printer_width ?? 76);
        $this->setThermalDefaults($thermalWidth);
        
        // Set custom margins
        $this->SetMargins(5, 20, 5); // Left, Top, Right margins
        
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
        $this->generateRequiredServices();
        $this->generateTotalsSection();
        $this->generateBarcode();
        $this->generateFooter();

        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $this->visit->patient->name);
        $pdfFileName = 'ServiceReceipt_Visit_' . $this->visit->id . '_' . $patientNameSanitized . '.pdf';
        
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
        $this->SetFont($this->fontName, 'B', 15);
        $this->MultiCell(0, $this->lineHeight, $this->appSettings?->hospital_name ?: ($this->appSettings?->lab_name ?: config('app.name')), 0, $this->alignCenter, false, 1);

        // Address and contact info
        $this->SetFont($this->fontName, '', 9);
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
        $this->Cell(0, $this->lineHeight + 1, 'الاسم / '.$this->visit->patient->name, 0, 1, $this->alignStart);
        
        // Doctor name (second line, left aligned)
        if ($this->visit->doctor) {
            $this->SetFont($this->fontName, '', 12);
            $this->Cell(0, $this->lineHeight, 'اسم الطبيب/ '.$this->visit->doctor->name, 0, 1, $this->alignStart);
        }
        $this->Ln(5);
        
        // Visit number in the middle and date on the right
        $this->SetFont($this->fontName, 'B', 13);
        $visitNumber = "ID " . $this->visit->number;
        $date = Carbon::now()->format('Y/m/d H:i A') . ' التاريخ ';
        
        // Calculate positions for center and right alignment
        $pageWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $visitNumberWidth = $this->GetStringWidth($visitNumber);
        $dateWidth = $this->GetStringWidth($date);
        
        // Position visit number in center
        $centerX = ($pageWidth - $visitNumberWidth) / 2;
        $this->SetX($this->getMargins()['left'] + $centerX);
        $this->Cell($visitNumberWidth, $this->lineHeight, $visitNumber, 0, 0, 'C');
        
        $this->Ln(10);
        $this->SetFont($this->fontName, '', size: 7);
        // Position date on the right
        $this->Cell(0, $this->lineHeight, $date, 0, 1, 'L');
        
        $this->Ln(5);

        // Company details (if present) - shown after date
        if ($this->isCompanyPatient && $this->visit->patient) {
            $this->SetFont($this->fontName, 'B', 10);
            $this->MultiCell(0, $this->lineHeight, 'معلومات شركة المريض', 0, $this->alignStart, false, 1);
            $this->SetFont($this->fontName, '', 9);

            // Company name
            if (!empty($this->visit->patient->company?->name)) {
                $this->MultiCell(0, $this->lineHeight, 'اسم الشركة: ' . $this->visit->patient->company->name, 0, $this->alignStart, false, 1);
            }
            // Subcompany name
            if (!empty($this->visit->patient->subcompany?->name)) {
                $this->MultiCell(0, $this->lineHeight, 'اسم الشركة الفرعية: ' . $this->visit->patient->subcompany->name, 0, $this->alignStart, false, 1);
            }
            // Insurance number
            if (!empty($this->visit->patient->insurance_no)) {
                $this->MultiCell(0, $this->lineHeight, 'رقم التأمين: ' . $this->visit->patient->insurance_no, 0, $this->alignStart, false, 1);
            }
            // Guarantor
            if (!empty($this->visit->patient->guarantor)) {
                $this->MultiCell(0, $this->lineHeight, 'الضامن: ' . $this->visit->patient->guarantor, 0, $this->alignStart, false, 1);
            }
            // Company relation
            if (!empty($this->visit->patient->company_relation?->name)) {
                $this->MultiCell(0, $this->lineHeight, 'اسم العلاقة: ' . $this->visit->patient->company_relation->name, 0, $this->alignStart, false, 1);
            }

            $this->Ln(2);
            $this->Cell(0, 0.1, '', 'T', 1, 'C');
            $this->Ln(3);
        }
    }

    protected function generateBarcode(): void
    {
        if ($this->appSettings?->barcode && !empty($this->requestedServicesToPrint[0]['id'])) {
            $barcodeValue = (string) $this->visit->id;
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
                'fontsize' => 10,
                'stretchtext' => 4
            ];
            $this->write1DBarcode($barcodeValue, 'C128B', '50', '', '', (float)15, (float)0.3, $style, 'N');
        }

        $this->Ln(5);
        $this->Cell(0, 0.1, '', 'T', 1, 'C');
        $this->Ln(0.5);
    }

    protected function generateRequiredServices(): void
    {
        // Title: "الخدمات المطلوبة"
        $this->SetFont($this->fontName, 'B', 15);
        $this->Cell(0, $this->lineHeight + 1, 'الخدمات المطلوبة', 0, 1, 'R');
        $this->Ln(1);

        // Collect all service names
        $serviceNames = [];
        foreach ($this->requestedServicesToPrint as $rs) {
            // $service = Service::find($rs['service_id']);
            $serviceName = $rs['service']['name'] ?? 'خدمة غير معروفة';
            $quantity = (int) ($rs['count'] ?? 1);
            
            // Add quantity if more than 1
            if ($quantity > 1) {
                $serviceName .= " (×{$quantity})";
            }
            
            $serviceNames[] = $serviceName;
        }

        // Join service names with commas and display in full width
        $allServicesText = implode('، ', $serviceNames);
        
        $this->SetFont($this->fontName, '', 7);
        $pageUsableWidth = $this->getPageWidth() - $this->getMargins()['right'];
        $this->MultiCell(0, $this->lineHeight, $allServicesText, 0, 'L', false, 1);

        $this->Ln(2);
        $this->Cell(0, 0.1, '', 'T', 1, 'C');
        $this->Ln(5);
    }

    protected function generateTotalsSection(): void
    {
        $this->SetFont($this->fontName, '', 10);
        $pageUsableWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];

        // Calculate totals
        $subTotalServices = 0;
        $totalDiscountOnServices = 0;
        $totalEnduranceOnServices = 0;
        $hasDiscount = false;

        foreach ($this->requestedServicesToPrint as $rs) {
            $quantity = (int) ($rs['count'] ?? 1);
            $unitPrice = (float) ($rs['price'] ?? 0);
            $itemGrossTotal = $unitPrice * $quantity;
            $subTotalServices += $itemGrossTotal;

            $itemDiscountPercent = (float) ($rs['discount_per'] ?? 0);
            $itemDiscountFixed = (float) ($rs['discount'] ?? 0);
            $itemDiscountAmount = (($itemGrossTotal * $itemDiscountPercent) / 100) + $itemDiscountFixed;
            $totalDiscountOnServices += $itemDiscountAmount;

            if ($itemDiscountAmount > 0) {
                $hasDiscount = true;
            }

            if ($this->isCompanyPatient) {
                $itemEndurance = (float) ($rs['endurance'] ?? 0) * $quantity;
                $totalEnduranceOnServices += $itemEndurance;
            }
        }

        $totalActuallyPaidForTheseServices = array_sum(array_column($this->requestedServicesToPrint, 'amount_paid'));

        // Display totals - simplified version
        $this->drawThermalTotalRow('الإجمالي:', $subTotalServices, $pageUsableWidth);
        
        // Only show discount if there's actually a discount
        if ($hasDiscount && $totalDiscountOnServices > 0) {
            $this->drawThermalTotalRow('الخصم:', -$totalDiscountOnServices, $pageUsableWidth);
        }

        // Show company endurance if applicable
        if ($this->isCompanyPatient && $totalEnduranceOnServices > 0) {
            $this->drawThermalTotalRow('تحمل الشركة:', -$totalEnduranceOnServices, $pageUsableWidth);
        }

        $this->drawThermalTotalRow('المدفوع:', $totalActuallyPaidForTheseServices, $pageUsableWidth);

        $this->Ln(5);
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


