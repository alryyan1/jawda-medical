<?php

namespace App\Services\Pdf;

use App\Models\Setting; // If fetching settings directly here
use Carbon\Carbon;
use TCPDF;

// Assuming your MyCustomTCPDF extends TCPDF
class MyCustomTCPDF extends TCPDF
{
    protected $reportTitle = 'Report';
    protected $filterCriteria = '';
    protected $currentSettings = null;
    protected $pageOrientation = 'P'; // Default to Portrait

    public function __construct($reportTitle = 'Report', $filterCriteria = '', $orientation = 'P', $unit = 'mm', $format = 'A4', $unicode = true, $encoding = 'UTF-8', $diskcache = false, $pdfa = false)
    {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);
        
        $this->reportTitle = $reportTitle;
        $this->filterCriteria = $filterCriteria;
        $this->currentSettings = Setting::instance(); // Get settings instance
        $this->pageOrientation = $orientation;

        $this->SetCreator(PDF_CREATOR);
        $this->SetAuthor($this->currentSettings?->hospital_name ?? config('app.name', 'Clinic'));
        $this->SetTitle($this->reportTitle);
        $this->SetSubject($this->filterCriteria);
        $this->setPrintHeader(true); // Enable custom header
        $this->setPrintFooter(true); // Enable custom footer
        $this->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $this->SetFont($this->getDefaultFontFamily(), '', 10); // Set default font for the document
        $this->setLanguageArray($this->getLanguageArray()); // For RTL text
        $this->setRTL(app()->getLocale() === 'ar'); // Set RTL based on app locale
    }

    public function getDefaultFontFamily()
    {
        return 'arial'; // Or your preferred default font
    }

    public function getLanguageArray() {
        $currentLang = app()->getLocale();
        $l = [];
        $l['a_meta_charset'] = 'UTF-8';
        $l['a_meta_dir'] = ($currentLang === 'ar') ? 'rtl' : 'ltr';
        $l['a_meta_language'] = $currentLang; // Or map to 'ar', 'en'
        $l['w_page'] = 'page';
        return $l;
    }


    public function Header()
    {
        $isRTL = $this->getRTL();
        $logoPath = null;
        $logoData = null;
        

        if ($this->currentSettings?->logo_base64 && str_starts_with($this->currentSettings->logo_base64, 'data:image')) {
            try {
                // Extract base64 data from data URL
                $logoData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $this->currentSettings->logo_base64));
            } catch (\Exception $e) {
                $logoData = null; // Failed to decode
            }
        } elseif ($this->currentSettings?->logo_path) { // Assuming you might store a path
            $logoPath = storage_path('app/public/' . $this->currentSettings->logo_path);
            if (!file_exists($logoPath)) $logoPath = null;
        }
        
        $headerData = $this->currentSettings?->report_header_company_name 
                        ? [
                            'company_name' => $this->currentSettings->report_header_company_name,
                            'address_line1' => $this->currentSettings->report_header_address_line1,
                            'address_line2' => $this->currentSettings->report_header_address_line2,
                            'phone' => $this->currentSettings->report_header_phone,
                            'email' => $this->currentSettings->report_header_email,
                            'vatin' => $this->currentSettings->report_header_vatin,
                            'cr' => $this->currentSettings->report_header_cr,
                            'logo_base64' => $this->currentSettings->report_header_logo_base64, // Use specific report header logo
                          ]
                        : [ // Fallback to general settings
                            'company_name' => $this->currentSettings?->hospital_name ?: ($this->currentSettings?->lab_name ?: config('app.name')),
                            'address_line1' => $this->currentSettings?->address,
                            'phone' => $this->currentSettings?->phone,
                            'email' => $this->currentSettings?->email,
                            'vatin' => $this->currentSettings?->vatin,
                            'cr' => $this->currentSettings?->cr,
                            'logo_base64' => $this->currentSettings?->logo_base64, // General logo
                          ];


        $currentY = $this->GetY(); // Start Y for header block
        $imageX = $isRTL ? $this->getPageWidth() - $this->original_rMargin - 25 : $this->original_lMargin;
        $textX = $isRTL ? $this->original_rMargin : $this->original_lMargin + ( ($logoData || $logoPath) ? 28 : 0) ; // Adjust text start based on logo
        $imageWidth = 25; // Max width for logo
        $imageHeight = 15; // Max height for logo
        
        // Attempt to render logo
        if ($logoData) {
             $this->Image('@'.$logoData, $imageX, $currentY, $imageWidth, $imageHeight, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        } elseif ($logoPath) {
             $this->Image($logoPath, $imageX, $currentY, $imageWidth, $imageHeight, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }


        $this->SetFont($this->getDefaultFontFamily(), 'B', 12);
        $this->SetXY($textX, $currentY);
        $this->MultiCell(0, 5, $headerData['company_name'], 0, $isRTL ? 'R' : 'L', false, 1, '', '', true, 0, false, true, 0, 'T');

        $this->SetFont($this->getDefaultFontFamily(), '', 8);
        if($headerData['address_line1']) {
            $this->SetX($textX);
            $this->MultiCell(0, 4, $headerData['address_line1'], 0, $isRTL ? 'R' : 'L', false, 1, '', '', true, 0, false, true, 0, 'T');
        }
        // if($headerData['address_line2']) {
        //     $this->SetX($textX);
        //     $this->MultiCell(0, 4, $headerData['address_line2'], 0, $isRTL ? 'R' : 'L', false, 1, '', '', true, 0, false, true, 0, 'T');
        // }
        if($headerData['phone']) {
            $this->SetX($textX);
            $this->MultiCell(0, 4, 'الهاتف: ' . $headerData['phone'], 0, $isRTL ? 'R' : 'L', false, 1, '', '', true, 0, false, true, 0, 'T');
        }
        // Add email, vatin, cr similarly if they exist

        // Margins: Left, Top, Right. Top margin increased for custom header.
        $this->SetMargins(10, 40, 10); // Increased top margin for more header space
        $this->SetHeaderMargin(5);    // Distance from top of page to start of header content block
        $this->SetFooterMargin(10);   // Distance from bottom of page to start of footer content block
        // Ensure Y cursor is below the tallest element (logo or text block)
        $yAfterText = $this->GetY();
        $yAfterImage = $currentY + $imageHeight + 2; // Add some padding
        $this->SetY(max($yAfterText, $yAfterImage));


        // Report Title and Filters
        $this->SetFont($this->getDefaultFontFamily(), 'B', $this->pageOrientation === 'L' ? 11 : 12);
        $this->Cell(0, 6, $this->reportTitle, 0, 1, 'C', 0, '', 0, false, 'T', 'M');
        if ($this->filterCriteria) {
            $this->SetFont($this->getDefaultFontFamily(), '', $this->pageOrientation === 'L' ? 7 : 8);
            $this->Cell(0, 4, $this->filterCriteria, 0, 1, 'C', 0, '', 0, false, 'T', 'M');
        }
        $this->Ln(5);
        // Header line
        $this->Line($this->getMargins()['left'], $this->GetY(), $this->getPageWidth() - $this->getMargins()['right'], $this->GetY());
        $this->Ln(5);
    }

    public function Footer()
    {
        $isRTL = $this->getRTL();
        $this->SetY(-15); // Position at 1.5 cm from bottom
        $this->SetFont($this->getDefaultFontFamily(), 'I', 7);
        // Page number
        $pageNumberText = 'صفحة ' . $this->getAliasNumPage() . ' من ' . $this->getAliasNbPages();
        $this->Cell(0, 10, $pageNumberText, 0, false, 'C', 0, '', 0, false, 'T', 'M');
        
        // Print date
        $printDate = 'تاريخ الطباعة: ' . Carbon::now()->format('Y-m-d h:i A');
        $this->Cell(0, 10, $printDate, 0, false, $isRTL ? 'L' : 'R', 0, '', 0, false, 'T', 'M'); // Align to opposite side
    }

    // Helper to draw table headers
    public function DrawTableHeader($headers, $widths, $alignments = [], $lineHeight = 7, $fillColor = [230,230,230])
    {
        $this->SetFont($this->getDefaultFontFamily(), 'B', 8);
        $this->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
        $this->SetTextColor(0);
        $this->SetDrawColor(128, 128, 128); // Grey border
        $this->SetLineWidth(0.15);

        foreach ($headers as $i => $header) {
            $align = $alignments[$i] ?? ($this->getRTL() ? 'R' : 'L');
            $this->Cell($widths[$i], $lineHeight, $header, 1, ($i == count($headers) - 1 ? 1 : 0), $align, true);
        }
    }

    // Helper to draw table rows
    public function DrawTableRow($rowData, $widths, $alignments = [], $fill = false, $lineHeight = 6, $maxLines = 1)
    {
        $this->SetFont($this->getDefaultFontFamily(), '', 7.5);
        $this->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        $this->SetTextColor(0);

        $rowCalculatedHeight = $lineHeight; // Start with default line height
        if ($maxLines > 1) { // If allowing multiline, calculate max height for this row
            $currentCellPadding = $this->getCellPaddings();
            $actualLineHeightForFont = ($this->getFontSize() * $this->getCellHeightRatio() * K_PATH_MAIN); // Approximate line height
            if ($actualLineHeightForFont < 3) $actualLineHeightForFont = 3.5; // Min reasonable
            
            $calculatedHeightFromLines = ($maxLines * $actualLineHeightForFont) + $currentCellPadding['T'] + $currentCellPadding['B'] + 0.5;
            if ($calculatedHeightFromLines > $rowCalculatedHeight) $rowCalculatedHeight = $calculatedHeightFromLines;
        }


        $xPos = $this->GetX();
        $yPos = $this->GetY();

        foreach ($rowData as $i => $cellData) {
            $align = $alignments[$i] ?? ($this->getRTL() ? 'R' : 'L');
            if (is_numeric($cellData) && !is_string($cellData)) $align = 'C'; // Center numbers by default

            $this->MultiCell($widths[$i], $rowCalculatedHeight, (string)$cellData, 1, $align, $fill, 0, $xPos, $yPos, true, 0, false, true, $rowCalculatedHeight, 'M');
            $xPos += $widths[$i];
        }
        $this->Ln($rowCalculatedHeight);
    }


    // Specific method for thermal receipt, if it has very different header/footer needs
    public function setThermalDefaults($pageWidthMM = 76, $fontFamily = 'dejavusanscondensed', $fontSize = 7) {
        $this->setPrintHeader(false); // Often no complex header on thermal
        $this->setPrintFooter(false); // Often no complex footer
        $this->SetMargins(3, 3, 3); // Small margins
        $this->SetAutoPageBreak(TRUE, 3);
        $this->setPageFormat([$pageWidthMM, 297], $this->pageOrientation); // Custom width, standard height (will break)
        $this->SetFont($fontFamily, '', $fontSize);
        $this->setCellPaddings(0.5, 0.5, 0.5, 0.5); // Minimal padding
        $this->setCellHeightRatio(1.1);
    }
}