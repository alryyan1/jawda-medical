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
        $this->fontName = $this->getDefaultFontFamily();
        $this->isRTL = $this->getRTL();
        $this->alignStart = $this->isRTL ? 'R' : 'L';
        $this->alignEnd = $this->isRTL ? 'L' : 'R';
        $this->alignCenter = 'C';
        $this->lineHeight = 3.5;
    }

    public function generate(): string
    {
        $this->AddPage();
        $this->generateHeader();
        $this->generateReceiptInfo();
        $this->generateBarcode();
        $this->generateItemsTable();
        $this->generateTotalsSection();
        $this->generateWatermark();
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
            $this->MultiCell(0, $this->lineHeight - 0.5, ($this->isRTL ? "هاتف: " : "Tel: ") . $this->appSettings->phone, 0, $this->alignCenter, false, 1);
        }
        if ($this->appSettings?->vatin) {
            $this->MultiCell(0, $this->lineHeight - 0.5, ($this->isRTL ? "ر.ض: " : "VAT: ") . $this->appSettings->vatin, 0, $this->alignCenter, false, 1);
        }

        $this->Ln(1);
        $this->Cell(0, 0.1, '', 'T', 1, 'C');
        $this->Ln(1);
    }

    protected function generateReceiptInfo(): void
    {
        $this->SetFont($this->fontName, '', 6.5);
        $receiptNumber = "LAB-" . $this->visit->id . "-" . ($this->labRequestsToPrint[0]['id'] ?? '');
        $this->Cell(0, $this->lineHeight, ($this->isRTL ? "إيصال رقم: " : "Receipt #: ") . $receiptNumber, 0, 1, $this->alignStart);
        $this->Cell(0, $this->lineHeight, ($this->isRTL ? "زيارة رقم: " : "Visit #: ") . $this->visit->id, 0, 1, $this->alignStart);
        $this->Cell(0, $this->lineHeight, ($this->isRTL ? "التاريخ: " : "Date: ") . Carbon::now()->format('Y/m/d H:i A'), 0, 1, $this->alignStart);
        $this->Cell(0, $this->lineHeight, ($this->isRTL ? "المريض: " : "Patient: ") . $this->visit->patient->name, 0, 1, $this->alignStart);
        
        if ($this->visit->patient->phone) {
            $this->Cell(0, $this->lineHeight, ($this->isRTL ? "الهاتف: " : "Phone: ") . $this->visit->patient->phone, 0, 1, $this->alignStart);
        }
        
        if ($this->isCompanyPatient && $this->visit->patient->company) {
            $this->Cell(0, $this->lineHeight, ($this->isRTL ? "الشركة: " : "Company: ") . $this->visit->patient->company->name, 0, 1, $this->alignStart);
        }
        
        if ($this->isCompanyPatient && $this->visit->patient->insurance_no) {
            $this->Cell(0, $this->lineHeight, ($this->isRTL ? "رقم التأمين: " : "Insurance No: ") . $this->visit->patient->insurance_no, 0, 1, $this->alignStart);
        }
        
        if ($this->isCompanyPatient && $this->visit->patient->subcompany) {
            $this->Cell(0, $this->lineHeight, ($this->isRTL ? "الشركة الفرعية: " : "Subcompany: ") . $this->visit->patient->subcompany->name, 0, 1, $this->alignStart);
        }
        
        if ($this->isCompanyPatient && $this->visit->patient->guarantor) {
            $this->Cell(0, $this->lineHeight, ($this->isRTL ? "الضامن: " : "Guarantor: ") . $this->visit->patient->guarantor, 0, 1, $this->alignStart);
        }
        
        if ($this->isCompanyPatient && $this->visit->patient->companyRelation) {
            $this->Cell(0, $this->lineHeight, ($this->isRTL ? "العلاقة: " : "Relation: ") . $this->visit->patient->companyRelation->name, 0, 1, $this->alignStart);
        }
        
        if ($this->visit->doctor) {
            $this->Cell(0, $this->lineHeight, ($this->isRTL ? "الطبيب: " : "Doctor: ") . $this->visit->doctor->name, 0, 1, $this->alignStart);
        }
        
        $this->Cell(0, $this->lineHeight, ($this->isRTL ? "الكاشير: " : "Cashier: ") . $this->cashierName, 0, 1, $this->alignStart);
    }

    protected function generateBarcode(): void
    {
        if ($this->appSettings?->barcode && !empty($this->labRequestsToPrint[0]['id'])) {
            $this->Ln(1);
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
            $this->Ln(1);
        }

        $this->Ln(1);
        $this->Cell(0, 0.1, '', 'T', 1, 'C');
        $this->Ln(0.5);
    }

    protected function generateItemsTable(): void
    {
        $pageUsableWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $nameWidth = $pageUsableWidth * 0.50;
        $qtyWidth = $pageUsableWidth * 0.10;
        $priceWidth = $pageUsableWidth * 0.20;
        $totalWidth = $pageUsableWidth * 0.20;

        // Table headers
        $this->SetFont($this->fontName, 'B', 6.5);
        $this->Cell($nameWidth, $this->lineHeight, ($this->isRTL ? 'البيان' : 'Item'), 'B', 0, $this->alignStart);
        $this->Cell($qtyWidth, $this->lineHeight, ($this->isRTL ? 'كمية' : 'Qty'), 'B', 0, $this->alignCenter);
        $this->Cell($priceWidth, $this->lineHeight, ($this->isRTL ? 'سعر' : 'Price'), 'B', 0, $this->alignCenter);
        $this->Cell($totalWidth, $this->lineHeight, ($this->isRTL ? 'إجمالي' : 'Total'), 'B', 1, $this->alignCenter);
        $this->SetFont($this->fontName, '', 6.5);

        // Table data
        foreach ($this->labRequestsToPrint as $lr) {
            $testName = $lr['main_test']['main_test_name'] ?? 'فحص غير معروف';
            $quantity = (int) ($lr['count'] ?? 1);
            $unitPrice = (float) ($lr['price'] ?? 0);
            $itemGrossTotal = $unitPrice * $quantity;

            $currentYbeforeMultiCell = $this->GetY();
            $this->MultiCell($nameWidth, $this->lineHeight - 0.5, $testName, 0, $this->alignStart, false, 0, '', '', true, (float)0, false, true, (float)0, 'T');
            $yAfterMultiCell = $this->GetY();
            $this->SetXY($this->getMargins()['left'] + $nameWidth, $currentYbeforeMultiCell);

            $this->Cell($qtyWidth, $this->lineHeight - 0.5, $quantity, 0, 0, $this->alignCenter);
            $this->Cell($priceWidth, $this->lineHeight - 0.5, number_format($unitPrice, 2), 0, 0, $this->alignCenter);
            $this->Cell($totalWidth, $this->lineHeight - 0.5, number_format($itemGrossTotal, 2), 0, 1, $this->alignCenter);
            $this->SetY(max($yAfterMultiCell, $currentYbeforeMultiCell + $this->lineHeight - 0.5));
        }

        $this->Ln(0.5);
        $this->Cell(0, 0.1, '', 'T', 1, 'C');
        $this->Ln(0.5);
    }

    protected function generateTotalsSection(): void
    {
        $this->SetFont($this->fontName, '', 7);
        $pageUsableWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];

        // Calculate totals
        $subTotalLab = 0;
        $totalDiscountOnLab = 0;
        $totalEnduranceOnLab = 0;

        foreach ($this->labRequestsToPrint as $lr) {
            $quantity = (int) ($lr['count'] ?? 1);
            $unitPrice = (float) ($lr['price'] ?? 0);
            $itemGrossTotal = $unitPrice * $quantity;
            $subTotalLab += $itemGrossTotal;

            $itemDiscountPercent = (float) ($lr['discount_per'] ?? 0);
            $itemDiscountAmount = ($itemGrossTotal * $itemDiscountPercent) / 100;
            $totalDiscountOnLab += $itemDiscountAmount;

            if ($this->isCompanyPatient) {
                $itemEndurance = (float) ($lr['endurance'] ?? 0) * $quantity;
                $totalEnduranceOnLab += $itemEndurance;
            }
        }

        $netAfterDiscount = $subTotalLab - $totalDiscountOnLab;
        $netPayableByPatient = $netAfterDiscount - ($this->isCompanyPatient ? $totalEnduranceOnLab : 0);
        $totalActuallyPaidForTheseLabs = array_sum(array_column($this->labRequestsToPrint, 'amount_paid'));
        $balanceDueForTheseLabs = $netPayableByPatient - $totalActuallyPaidForTheseLabs;

        // Display totals
        $this->drawThermalTotalRow(($this->isRTL ? 'إجمالي الفحوصات:' : 'Subtotal:'), $subTotalLab, $pageUsableWidth);
        
        if ($totalDiscountOnLab > 0) {
            $this->drawThermalTotalRow(($this->isRTL ? 'إجمالي الخصم:' : 'Discount:'), -$totalDiscountOnLab, $pageUsableWidth, false, 'text-red-500');
        }

        if ($this->isCompanyPatient && $totalEnduranceOnLab > 0) {
            $this->drawThermalTotalRow(($this->isRTL ? 'تحمل الشركة:' : 'Company Share:'), -$totalEnduranceOnLab, $pageUsableWidth, false, 'text-blue-500');
        }

        $this->SetFont($this->fontName, 'B', 7.5);
        $this->drawThermalTotalRow(($this->isRTL ? 'صافي المطلوب من المريض:' : 'Patient Net Payable:'), $netPayableByPatient, $pageUsableWidth, true);
        $this->SetFont($this->fontName, '', 7);

        $this->drawThermalTotalRow(($this->isRTL ? 'المبلغ المدفوع:' : 'Amount Paid:'), $totalActuallyPaidForTheseLabs, $pageUsableWidth);

        $this->SetFont($this->fontName, 'B', 7.5);
        $this->drawThermalTotalRow(($this->isRTL ? 'المبلغ المتبقي:' : 'Balance Due:'), $balanceDueForTheseLabs, $pageUsableWidth, true);

        $this->Ln(2);
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
        $footerMessage = $this->appSettings?->receipt_footer_message ?: ($this->isRTL ? 'شكراً لزيارتكم!' : 'Thank you for your visit!');
        $this->MultiCell(0, $this->lineHeight - 1, $footerMessage, 0, $this->alignCenter, false, 1);
        $this->Ln(3);
    }

    protected function drawThermalTotalRow(string $label, float $amount, float $pageUsableWidth, bool $isBold = false, ?string $colorClass = null): void
    {
        $labelWidth = $pageUsableWidth * 0.60;
        $amountWidth = $pageUsableWidth * 0.40;

        if ($isBold) {
            $this->SetFont($this->fontName, 'B', 7.5);
        }

        $this->Cell($labelWidth, $this->lineHeight, $label, 0, 0, $this->alignStart);
        $this->Cell($amountWidth, $this->lineHeight, number_format($amount, 2), 0, 1, $this->alignEnd);

        if ($isBold) {
            $this->SetFont($this->fontName, '', 7);
        }
    }
}
