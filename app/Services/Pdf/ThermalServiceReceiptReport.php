<?php

namespace App\Services\Pdf;

use App\Models\DoctorVisit;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use TCPDF;

class ThermalServiceReceiptReport extends TCPDF
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
        // Call TCPDF constructor directly
        parent::__construct('P', 'mm', [76, 297], true, 'UTF-8', false, false);

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
        $this->SetMargins(5, 0, 5); // Left, Top, Right margins

        // Performance optimizations for images
        $this->setCompression(true);           // Compress content streams
        $this->setImageScale(1.25);            // Reasonable image DPI scaling
        $this->setJPEGQuality(80);             // Balance quality/performance

        // Set font and alignment properties
        $this->fontName = 'ae_alhor'; // Use the converted Arabic font
        $this->setRTL(true);
        $this->isRTL = true; // Always RTL for Arabic
        $this->alignStart = 'R'; // Right alignment for RTL
        $this->alignEnd = 'L'; // Left alignment for RTL
        $this->alignCenter = 'C';
        $this->lineHeight = 3.5;

        // Set document properties
        $this->SetCreator(config('app.name', 'Lab System'));
        $this->SetAuthor($this->appSettings?->hospital_name ?? $this->appSettings?->lab_name ?? config('app.name'));
        $this->SetTitle('إيصال خدمات - Visit #' . $visit->id);
        $this->SetSubject('Service Receipt');
        $this->setLanguageArray(['a_meta_charset' => 'UTF-8', 'a_meta_dir' => 'rtl', 'a_meta_language' => 'ar', 'w_page' => 'صفحة']);
    }

    protected function setThermalDefaults($pageWidthMM = 76, $fontFamily = 'dejavusanscondensed', $fontSize = 7): void
    {
        $this->setPrintHeader(false); // Often no complex header on thermal
        $this->setPrintFooter(false); // Often no complex footer
        $this->SetMargins(3, 3, 3); // Small margins
        $this->SetAutoPageBreak(TRUE, 3);
        $this->setPageFormat([$pageWidthMM, 297], 'P'); // Custom width, standard height
        $this->SetFont($fontFamily, '', $fontSize);
        $this->setCellPaddings(0.5, 0.5, 0.5, 0.5); // Minimal padding
        $this->setCellHeightRatio(1.1);
    }

    public function generate(): string
    {
        $this->AddPage();

        // Log::info('ThermalServiceReceiptReport generate', ['requestedServicesToPrint' => $this->requestedServicesToPrint, 'company_id' => $this->visit->patient]);

        // Check if single unpaid service - use different format
        // Log each condition result
        $condition1_count = count($this->requestedServicesToPrint);
        $condition1_result = $condition1_count === 1;
        Log::info('Condition 1: Single service check', [
            'count' => $condition1_count,
            'result' => $condition1_result
        ]);

        $firstService = $this->requestedServicesToPrint[0] ?? null;
        $condition2_amountPaid = $firstService ? ($firstService['amount_paid'] ?? 0) : null;
        $condition2_result = $condition2_amountPaid == 0;
        Log::info('Condition 2: Amount paid check', [
            'amount_paid' => $condition2_amountPaid,
            'result' => $condition2_result
        ]);

        $condition3_endurance = $firstService ? ($firstService['endurance'] ?? null) : null;
        $condition3_result = $condition3_endurance != 0;
        Log::info('Condition 3: Endurance check', [
            'endurance' => $condition3_endurance,
            'result' => $condition3_result
        ]);

        $condition4_companyId = $this->visit->patient->company_id ?? null;
        $condition4_result = $condition4_companyId != null;
        Log::info('Condition 4: Company ID check', [
            'company_id' => $condition4_companyId,
            'result' => $condition4_result
        ]);

        $isSingleUnpaidService = $condition1_result
            && ($condition2_result && $condition3_result && $condition4_result);
        if ($this->visit->patient->company_id == null) {
            $isSingleUnpaidService = $condition1_result && $condition2_result;
            Log::info('Company ID is not null', ['isSingleUnpaidService' => $isSingleUnpaidService]);
        } else {
            $isSingleUnpaidService = $condition1_result
                && ($condition2_result && $condition3_result);
        }

        Log::info('Final condition result', [
            'isSingleUnpaidService' => $isSingleUnpaidService,
            'all_conditions' => [
                'condition1_single_service' => $condition1_result,
                'condition2_amount_paid_zero' => $condition2_result,
                'condition3_endurance_not_zero' => $condition3_result,
                'condition4_has_company_id' => $condition4_result,
            ]
        ]);

        if ($isSingleUnpaidService) {
            $this->generateUnpaidReceiptFormat();
        } else {
            $this->generateHeader();
            $this->generateReceiptInfo();
            $this->generateRequiredServices();
            $this->generateTotalsSection();
            $this->generateBarcode();
            $this->generateFooter();
        }

        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $this->visit->patient->name);
        $pdfFileName = 'ServiceReceipt_Visit_' . $this->visit->id . '_' . $patientNameSanitized . '.pdf';

        return $this->Output($pdfFileName, 'S');
    }

    protected function generateUnpaidReceiptFormat(): void
    {
        // "ايصال غير مدفوع" at the top, centered
        $this->SetFont($this->fontName, 'B', 14);
        $this->Cell(0, $this->lineHeight + 2, 'ايصال غير مدفوع', 0, 1, $this->alignCenter);
        $this->Ln(2);

        // Doctor name on separate line, left aligned
        if ($this->visit->doctor) {
            $this->SetFont($this->fontName, '', 12);
            $this->Cell(0, $this->lineHeight, 'الطبيب / ' . $this->visit->doctor->name, 0, 1, $this->alignStart);
        }

        // Patient name on separate line, left aligned
        $this->SetFont($this->fontName, '', 12);
        $this->Cell(0, $this->lineHeight, 'الاسم / ' . $this->visit->patient->name, 0, 1, $this->alignStart);
        $this->Ln(3);

        // Visit ID and Lab Number (visit_number) on same line - separate cells
        $this->SetFont($this->fontName, '', 10);
        $visitIdText = 'SERIAL ' . $this->visit->id;
        $labNumberText = 'LAB NUMBER ' . ($this->visit->patient->visit_number ?? '');

        // Calculate widths for proper spacing
        $pageWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $visitIdWidth = $this->GetStringWidth($visitIdText);
        $labNumberWidth = $this->GetStringWidth($labNumberText);
        $spacing = 5; // Space between cells

        // First cell: SERIAL
        $this->Cell($visitIdWidth, $this->lineHeight, $visitIdText, 0, 0, $this->alignStart);

        // Add spacing
        $this->Cell($spacing, $this->lineHeight, '', 0, 0, $this->alignStart);

        // Second cell: LAB NUMBER
        $this->Cell($labNumberWidth, $this->lineHeight, $labNumberText, 0, 1, $this->alignStart);

        // Horizontal line separator
        $this->Ln(2);
        $this->Cell(0, 0.1, '', 'T', 1, 'C');
        $this->Ln(2);

        // Barcode centered
        // if ($this->appSettings?->barcode) {
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

        // Center the barcode - use write1DBarcode with center alignment
        $pageWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $barcodeWidth = 50; // Barcode width in mm
        // $barcodeX = $this->getMargins()['left'] + ($pageWidth - $barcodeWidth) / 2;
        // $this->SetX($barcodeX);
        $this->write1DBarcode($barcodeValue, 'C128B', 55, $this->GetY(), $barcodeWidth, (float)15, (float)0.3, $style, 'N');

        // Visit ID number below barcode, centered
        // $this->Ln(18); // Space after barcode
        $this->SetFont($this->fontName, '', 10);
        // $this->Cell(0, $this->lineHeight, $barcodeValue, 0, 1, $this->alignCenter);
        // }

        $this->Ln(3);

        // User name
        $this->SetFont($this->fontName, '', 12);
        $this->Cell(0, $this->lineHeight, 'المستخدم / ' . $this->cashierName, 0, 1, $this->alignStart);

        // Required value (amount due)
        $this->Ln(2);
        $this->SetFont($this->fontName, '', 12);
        $this->Cell(0, $this->lineHeight, 'القيمه المطلوبه', 0, 1, $this->alignStart);

        // Calculate and display the amount due
        $rs = $this->requestedServicesToPrint[0];
        $quantity = (int) ($rs['count'] ?? 1);
        $unitPrice = (float) ($rs['price'] ?? 0);
        $itemGrossTotal = $unitPrice * $quantity;

        $itemDiscountPercent = (float) ($rs['discount_per'] ?? 0);
        $itemDiscountFixed = (float) ($rs['discount'] ?? 0);
        $itemDiscountAmount = (($itemGrossTotal * $itemDiscountPercent) / 100) + $itemDiscountFixed;

        $amountDue = $itemGrossTotal - $itemDiscountAmount;

        // Display amount due, centered
        $this->Ln(2);
        $this->SetFont($this->fontName, 'B', 14);
        if ($this->visit->patient->company_id != null) {
            $this->Cell(0, $this->lineHeight + 2, number_format($rs['endurance'], 2), 0, 1, $this->alignCenter);
        } else {
            $this->Cell(0, $this->lineHeight + 2, number_format($amountDue, 2), 0, 1, $this->alignCenter);
        }
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

        // $this->Ln(1);
        // $this->Cell(0, 0.1, '', 'T', 1, 'C');
        // $this->Ln(1);
    }

    protected function generateReceiptInfo(): void
    {
        $this->SetFont($this->fontName, 'B', 12);

        // Patient name (first line, left aligned)
        $this->Cell(0, $this->lineHeight + 1, 'الاسم / ' . $this->visit->patient->name, 0, 1, $this->alignStart);

        // Doctor name (second line, left aligned)
        if ($this->visit->doctor) {
            $this->SetFont($this->fontName, '', 12);
            $this->Cell(0, $this->lineHeight, 'اسم الطبيب/ ' . $this->visit->doctor->name, 0, 1, $this->alignStart);
        }

        // Cashier/User name (third line, left aligned)
        $this->SetFont($this->fontName, '', 12);
        // $this->Cell(0, $this->lineHeight, 'الكاشير/ '.$this->cashierName, 0, 1, $this->alignStart);

        // $this->Ln(5);

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
        $this->Cell(0, 5, 'LAB NUMBER ' . ($this->visit->patient->visit_number ?? ''), 0, 1, 'L');

        $this->Ln(5);

        // Company details (if present) - shown after date
        if ($this->isCompanyPatient && $this->visit->patient) {
            $this->SetFont($this->fontName, 'B', 10);
            $this->MultiCell(0, $this->lineHeight, 'بيانات التامين', 0, $this->alignStart, false, 1);
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
        // if ($this->appSettings?->barcode && !empty($this->requestedServicesToPrint[0]['id'])) {
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
        // }

        // $this->Ln(5);
        // $this->Cell(0, 0.1, '', 'T', 1, 'C');
        $this->Ln(0.5);
    }

    protected function generateRequiredServices(): void
    {
        // Title: "الخدمات المطلوبة"
        $this->SetFont($this->fontName, 'B', 15);
        $this->Cell(0, $this->lineHeight + 1, 'الخدمات المطلوبة', 0, 1, 'R');
        // $this->Ln(1);

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

        $this->SetFont($this->fontName, '', 10);
        $pageUsableWidth = $this->getPageWidth() - $this->getMargins()['right'];
        $this->MultiCell(0, $this->lineHeight, $allServicesText, 0, 'L', false, 1);

        // $this->Ln(2);
        $this->Cell(0, 0.1, '', 'T', 1, 'C');
        // $this->Ln(5);
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
        if ($this->visit->patient->company_id == null) {

            $this->drawThermalTotalRow('الإجمالي:', $subTotalServices, $pageUsableWidth);
        }

        // Display totals - simplified version

        // Only show discount if there's actually a discount
        if ($hasDiscount && $totalDiscountOnServices > 0) {
            $this->drawThermalTotalRow('الخصم:', -$totalDiscountOnServices, $pageUsableWidth);
        }

        // Show company endurance if applicable
        // if ($this->isCompanyPatient && $totalEnduranceOnServices > 0) {
        //     $this->drawThermalTotalRow('تحمل الشركة:', -$totalEnduranceOnServices, $pageUsableWidth);
        // }

        $this->drawThermalTotalRow('المدفوع:', $totalActuallyPaidForTheseServices, $pageUsableWidth);

        // $this->Ln(5);
    }

    protected function generateFooter(): void
    {
        $this->Ln(3);
        $condition1_count = count($this->requestedServicesToPrint);
        $condition1_result = $condition1_count === 1;
        if ($condition1_result) {
            $user_requested = $this->requestedServicesToPrint[0]['user_id'];
            $user_deposited = $this->requestedServicesToPrint[0]['user_deposited'];
            $user = User::find($user_deposited);
            $user_requested = User::find($user_requested);
            $this->SetFont($this->fontName, 'I', 10);
            $footerMessage = ' تحصل بواسطة: ' . $user?->name;
            $footerRequestedMessage = ' تم الطلب بواسطة: ' . $user_requested->name;
            $this->MultiCell(0, $this->lineHeight - 1, $footerRequestedMessage, 0, 'L', false, 1);
            $this->MultiCell(0, $this->lineHeight - 1, $footerMessage, 0, 'L', false, 1);
            $this->Ln(3);
        }
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
