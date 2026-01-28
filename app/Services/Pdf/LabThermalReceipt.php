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

        // Set custom margins
        $this->SetMargins(5, 20, 5); // Left, Top, Right margins

        // Performance optimizations for images
        $this->setCompression(true);           // Compress content streams
        $this->setImageScale(1.25);            // Reasonable image DPI scaling
        $this->setJPEGQuality(80);             // Balance quality/performance
        // $this->setPngCompression(9);
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
        $this->generateTotalsSection();
        $this->generateBarcode();
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
        if ($this->appSettings?->header_base64) {
            try {
                $originalImageData = null;

                // Check if it's a file path
                if (file_exists($this->appSettings->header_base64)) {
                    $originalImageData = file_get_contents($this->appSettings->header_base64);
                }
                // Check if it's base64 data with data URI scheme
                elseif (str_starts_with($this->appSettings->header_base64, 'data:image')) {
                    $originalImageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $this->appSettings->header_base64));
                }
                // Check if it's raw base64 encoded string
                elseif (base64_decode($this->appSettings->header_base64, true) !== false) {
                    $originalImageData = base64_decode($this->appSettings->header_base64);
                }

                // Optimize the image for better performance
                if ($originalImageData) {
                    $logoData = $this->optimizeImage($originalImageData);
                }
            } catch (\Exception $e) {
                // Logo data invalid, continue without logo
            }
        }

        if ($logoData) {
            $this->Image('@' . $logoData, '', 5, 30, 0, '', '', 'T', false, 300, $this->alignCenter, false, false, (float)0, false, false, false);
            $this->Ln($logoData ? 10 : 1);
            $this->Ln(15);
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
        $this->Cell(0, $this->lineHeight + 1, 'الاسم / ' . $this->visit->patient->name, 0, 1, $this->alignStart);

        // Doctor name (second line, left aligned)
        // if ($this->visit->doctor) {
        //     $this->SetFont($this->fontName, '', 12);
        //     $this->Cell(0, $this->lineHeight, 'اسم الطبيب/ '.$this->visit->doctor->name, 0, 1, $this->alignStart);
        // }
        $this->Ln(5);

        // Visit number in the middle and date on the right
        $this->SetFont($this->fontName, 'B', 13);
        $visitNumber = "ID " . $this->visit->patient->visit_number;
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
        // $this->SetX($this->getMargins()['left'] + $pageWidth - $dateWidth);
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
        // Always generate barcode using visit ID
        $barcodeValue = (string) $this->visit->id;

        // Add some space before barcode
        $this->Ln(3);

        // Calculate center position for barcode
        $pageWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $barcodeWidth = 50; // Width of the barcode
        $centerX = ($pageWidth - $barcodeWidth) / 2;

        // Set position to center the barcode
        $this->SetX($this->getMargins()['left'] + $centerX);

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
            'fontsize' => 8,
            'stretchtext' => 4
        ];

        // Generate the barcode
        $this->write1DBarcode($barcodeValue, 'C128B', $barcodeWidth, '', '', (float)15, (float)0.3, $style, 'N');

        // Add visit ID text below barcode
        $this->Ln(2);
        $this->SetFont($this->fontName, '', 8);
        $visitIdText = "رقم الزيارة: " . $barcodeValue;
        $textWidth = $this->GetStringWidth($visitIdText);
        $textCenterX = ($pageWidth - $textWidth) / 2;
        $this->SetX($this->getMargins()['left'] + $textCenterX);
        $this->Cell($textWidth, $this->lineHeight, $visitIdText, 0, 1, 'C');

        $this->Ln(3);
        $this->Cell(0, 0.1, '', 'T', 1, 'C');
        $this->Ln(0.5);
    }

    protected function generateRequiredTests(): void
    {
        // Title: "الفحوصات المطلوبة"
        $this->SetFont($this->fontName, 'B', 15);
        $this->Cell(0, $this->lineHeight + 1, 'الفحوصات المطلوبة', 0, 1, 'R');
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
        $pageUsableWidth = $this->getPageWidth() - $this->getMargins()['right'];
        $this->MultiCell(0, $this->lineHeight, $allTestsText, 0, 'L', false, 1);

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

    /**
     * Optimize image for PDF embedding using GD library - resize and convert to JPEG
     * 
     * @param string $imageData Raw image data
     * @return string|null Optimized image data or null on failure
     */
    protected function optimizeImage(string $imageData): ?string
    {
        // Check if GD extension is available
        if (!extension_loaded('gd')) {
            return $imageData; // Return original if GD is not available
        }

        try {
            // Create image from string using GD
            $image = @imagecreatefromstring($imageData);
            if (!$image) {
                return $imageData; // Return original if we can't process it
            }

            // Get original dimensions
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);

            // Target max width for thermal receipt (300px is more than enough for 15mm display)
            $maxWidth = 300;

            // Only resize if image is larger than max width
            if ($originalWidth > $maxWidth) {
                $ratio = $maxWidth / $originalWidth;
                $newWidth = $maxWidth;
                $newHeight = (int)($originalHeight * $ratio);

                // Create new resized image with GD
                $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

                if (!$resizedImage) {
                    imagedestroy($image);
                    return $imageData;
                }

                // Fill with white background (better for JPEG conversion)
                $white = imagecolorallocate($resizedImage, 255, 255, 255);
                imagefill($resizedImage, 0, 0, $white);

                // Enable alpha blending for better quality
                imagealphablending($resizedImage, true);

                // High-quality resampling using GD
                imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

                // Free original image memory
                imagedestroy($image);
                $image = $resizedImage;
            }

            // Enable interlaced JPEG for progressive loading
            imageinterlace($image, 1);

            // Convert to JPEG with compression using GD
            ob_start();
            imagejpeg($image, null, 80); // 80% quality - good balance
            $optimizedData = ob_get_clean();

            // Free image memory
            imagedestroy($image);

            return $optimizedData ?: $imageData;
        } catch (\Exception $e) {
            // If optimization fails, return original image data
            return $imageData;
        }
    }
}
