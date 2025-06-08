<?php

namespace App\Services\Pdf; // Make sure this namespace is correct for your application

use App\Models\Setting;   // Assuming Setting model is in App\Models
use Carbon\Carbon;
use TCPDF;

class MyCustomTCPDF extends TCPDF
{
    protected string $reportTitle = 'Report';
    protected string $filterCriteria = '';
    protected ?Setting $currentSettings = null; // Type hint Setting model
    protected string $pageOrientation = 'P';
    protected string $defaultFont = 'arial'; // Default to arial as per your code

    // Table header properties to be accessible for re-drawing
    protected array $tableHeaders = [];
    protected array $tableWidths = [];
    protected array $tableAlignments = [];
    protected int $defaultCellHeight = 7; // Default height for a single line cell

    public function __construct(
        string $reportTitle = 'Report',
        string $filterCriteria = '',
        string $orientation = 'P',
        string $unit = 'mm',
        string $format = 'A4',
        bool $unicode = true,
        string $encoding = 'UTF-8',
        bool $diskcache = false,
        bool $pdfa = false
    ) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);

        $this->reportTitle = $reportTitle;
        $this->filterCriteria = $filterCriteria;
        $this->currentSettings = Setting::first(); // Fetch settings once
        $this->pageOrientation = $orientation;

        // Set Meta Information
        $this->SetCreator(PDF_CREATOR);
        $this->SetAuthor($this->currentSettings?->hospital_name ?? config('app.name', 'System'));
        $this->SetTitle($this->reportTitle);
        $this->SetSubject('Report Details');
        $this->SetKeywords('Report, PDF, Data');

        // Set default font
       
            $this->defaultFont = 'arial'; // Fallback
            // Log::warning("Arial font not found at public/arial.ttf. Falling back to Helvetica.");
        
        $this->SetFont($this->defaultFont, '', 10);

        // Set RTL and Language
        $this->setLanguageArray($this->getCustomLanguageArray());
        $this->setRTL(app()->getLocale() === 'ar'); // Assuming 'ar' for Arabic

        // Page settings (Margins crucial for AutoPageBreak)
        // These values are examples, adjust as needed.
        $topMargin = 38; // Increased to accommodate a more detailed header
        if ($this->pageOrientation === 'L') { // Landscape might need different margins
            $topMargin = 35;
        }
        $this->SetMargins(10, $topMargin, 10); // Left, Top, Right
        $this->SetHeaderMargin(5);      // From top of page to start of Header() content
        $this->SetFooterMargin(10);     // From bottom of page to start of Footer() content
        $this->SetAutoPageBreak(true, 20); // Enable auto page break, 20mm from bottom

        $this->setPrintHeader(true);
        $this->setPrintFooter(true);
    }

    public function getDefaultFontFamily(): string
    {
        return $this->defaultFont;
    }

    protected function getCustomLanguageArray(): array
    {
        $currentLang = app()->getLocale(); // Assuming Laravel's app locale
        return [
            'a_meta_charset' => 'UTF-8',
            'a_meta_dir' => ($currentLang === 'ar') ? 'rtl' : 'ltr',
            'a_meta_language' => $currentLang,
            'w_page' => ($currentLang === 'ar') ? 'صفحة' : 'Page',
        ];
    }

    public function Header()
    {
        $isRTL = $this->getRTL();
        $settings = $this->currentSettings; // Use the fetched settings

        $logoPath = $settings?->report_header_logo_base64 ?? $settings?->logo_base64; // Prioritize report specific logo
        $companyName = $settings?->report_header_company_name ?? $settings?->hospital_name ?? config('app.name');
        $address1 = $settings?->report_header_address_line1 ?? $settings?->address;
        $phone = $settings?->report_header_phone ?? $settings?->phone;
        // Add more header fields (email, VATIN, CR) similarly if needed

        $imageX = $isRTL ? $this->getPageWidth() - $this->original_rMargin - 25 : $this->original_lMargin;
        $textBlockX = $isRTL ? $this->original_rMargin : $this->original_lMargin;
        $textBlockWidth = $this->getPageWidth() - $this->original_lMargin - $this->original_rMargin;
        $logoRendered = false;

        if ($logoPath) {
            // Check if it's a base64 string
            if (str_starts_with($logoPath, 'data:image')) {
                try {
                    $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $logoPath));
                    if ($imgData) {
                        $this->Image('@'.$imgData, $imageX, $this->GetY(), 20, 12, '', '', 'T', false, 300, '', false, false, 0);
                        $logoRendered = true;
                    }
                } catch (\Exception $e) {/* Log error if needed */}
            } elseif (file_exists(storage_path('app/public/' . $logoPath))) { // Assuming path relative to storage/app/public
                $this->Image(storage_path('app/public/' . $logoPath), $imageX, $this->GetY(), 20, 12, '', '', 'T', false, 300, '', false, false, 0);
                $logoRendered = true;
            }
        }
        
        // If logo is rendered on one side, text block width might need adjustment if side-by-side
        if ($logoRendered) {
            $textBlockWidth -= 25; // Reduce width for text block if logo takes space
            if (!$isRTL) { // If LTR and logo is on left, text starts after logo
                $textBlockX += 25;
            }
        }

        $this->SetFont($this->defaultFont, 'B', 11);
        $this->SetXY($textBlockX, $this->getHeaderMargin() + 2); // Start text near top margin
        $this->MultiCell($textBlockWidth, 5, $companyName, 0, $isRTL ? 'R' : 'L', false, 1, '', '', true, 0, false, true, 0, 'T');

        $this->SetFont($this->defaultFont, '', 7);
        if ($address1) {
            $this->SetX($textBlockX);
            $this->MultiCell($textBlockWidth, 3.5, $address1, 0, $isRTL ? 'R' : 'L', false, 1, '', '', true, 0, false, true, 0, 'T');
        }
        if ($phone) {
            $this->SetX($textBlockX);
            $this->MultiCell($textBlockWidth, 3.5, ($isRTL ? 'الهاتف: ' : 'Phone: ') . $phone, 0, $isRTL ? 'R' : 'L', false, 1, '', '', true, 0, false, true, 0, 'T');
        }
        // ... Add other details like email, VATIN, CR ...

        // Y position after header details block (consider logo height if taller)
        $yAfterHeaderBlock = $this->GetY();
        if ($logoRendered) {
            $yAfterHeaderBlock = max($yAfterHeaderBlock, $this->getHeaderMargin() + 2 + 12 + 2); // currentY + imageHeight + padding
        }
        $this->SetY($yAfterHeaderBlock);
        $this->Ln(1); // Small space

        // Report Title
        $this->SetFont($this->defaultFont, 'B', $this->pageOrientation === 'L' ? 10 : 12);
        $this->Cell(0, 5, $this->reportTitle, 0, 1, 'C');

        // Filter Criteria
        if ($this->filterCriteria) {
            $this->SetFont($this->defaultFont, '', $this->pageOrientation === 'L' ? 7 : 8);
            $this->MultiCell(0, 3.5, $this->filterCriteria, 0, 'C', false, 1, '', '', true, 0, false, true, 0, 'T');
        }
        $this->Ln(2);

        // Header Line
        $this->Line($this->getMargins()['left'], $this->GetY(), $this->getPageWidth() - $this->getMargins()['right'], $this->GetY());
        // $this->Ln(1); // Space for table header to start after this line, SetY will be PDF_MARGIN_TOP on new page

        // After drawing all header elements, TCPDF will place content starting at PDF_MARGIN_TOP.
        // Ensure the sum of your header elements + Ln() calls doesn't exceed PDF_MARGIN_TOP.
        // The actual content drawing (like table headers) will start based on the Y cursor
        // which should be at PDF_MARGIN_TOP after AddPage() or where this Header() method leaves it.
    }

    public function Footer()
    {
        $isRTL = $this->getRTL();
        $this->SetY(-15); // Position at 1.5 cm from bottom
        $this->SetFont($this->defaultFont, 'I', 7);
        $this->SetTextColor(100, 100, 100);

        $pageNumberText = ($isRTL ? 'صفحة ' : 'Page ') . $this->getAliasNumPage() . ($isRTL ? ' من ' : ' of ') . $this->getAliasNbPages();
        $this->Cell(0, 10, $pageNumberText, 0, false, 'C', 0, '', 0, false, 'T', 'M');

        $printDate = ($isRTL ? 'تاريخ الطباعة: ' : 'Printed: ') . Carbon::now()->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d h:i A');
        $this->Cell(0, 10, $printDate, 0, false, $isRTL ? 'L' : 'R', 0, '', 0, false, 'T', 'M');
    }

    // Call this once before starting to draw the table rows for the first time
    public function SetTableDefinition(array $headers, array $widths, array $alignments)
    {
        $this->tableHeaders = $headers;
        $this->tableWidths = $widths;
        $this->tableAlignments = $alignments;
    }

    public function DrawTableHeader(array $headers = null, array $widths = null, array $alignments = null, int $lineHeight = 7, array $fillColor = [230, 230, 230])
    {
        $_headers = $headers ?? $this->tableHeaders;
        $_widths = $widths ?? $this->tableWidths;
        $_alignments = $alignments ?? $this->tableAlignments;

        if (empty($_headers) || empty($_widths)) {
            return; // Not enough info to draw header
        }

        $this->SetFont($this->defaultFont, 'B', 7.5); // Slightly smaller for more content
        $this->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
        $this->SetTextColor(0);
        $this->SetDrawColor(128, 128, 128);
        $this->SetLineWidth(0.15);

        $num_headers = count($_headers);
        for ($i = 0; $i < $num_headers; ++$i) {
            $align = $_alignments[$i] ?? ($this->getRTL() ? 'R' : 'L');
            $this->Cell($_widths[$i], $lineHeight, $_headers[$i], 1, 0, $align, 1);
        }
        $this->Ln($lineHeight);
    }

    public function DrawTableRow(array $rowData, array $widths = null, array $alignments = null, bool $fill = false, int $baseLineHeight = 6)
    {
        $_widths = $widths ?? $this->tableWidths;
        $_alignments = $alignments ?? $this->tableAlignments;

        if (empty($rowData) || empty($_widths)) {
            return;
        }
        
        $this->SetFont($this->defaultFont, '', 7); // Regular font for data
        $this->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255);
        $this->SetTextColor(0);
        $this->SetDrawColor(150,150,150); // Lighter border for data rows
        $this->SetLineWidth(0.1);

        // Calculate max number of lines needed for this row to determine dynamic row height
        $maxLines = 1;
        foreach ($rowData as $key => $data) {
            if (isset($_widths[$key])) { // Ensure width is defined
                $numLines = $this->getNumLines((string)$data, $_widths[$key]);
                if ($numLines > $maxLines) {
                    $maxLines = $numLines;
                }
            }
        }
        $rowHeight = $baseLineHeight * $maxLines;
        // Add a little padding if more than one line
        if ($maxLines > 1) {
            $rowHeight += ($maxLines * 0.5); // Small vertical padding per extra line
        }


        // Check for page break: if current Y + rowHeight > page break margin
        $pageBreakTrigger = $this->getPageHeight() - $this->getBreakMargin();
        if ($this->GetY() + $rowHeight > $pageBreakTrigger) {
            $this->AddPage($this->CurOrientation); // This will call Header()
            $this->DrawTableHeader(); // Re-draw table header using stored definitions
            // Font for data rows might need to be reset if Header changes it significantly
            $this->SetFont($this->defaultFont, '', 7);
            $this->SetFillColor($fill ? 240 : 255, $fill ? 240 : 255, $fill ? 240 : 255); // Re-apply fill for this row
            $this->SetTextColor(0);
            $this->SetDrawColor(150,150,150);
            $this->SetLineWidth(0.1);
        }

        $currentX = $this->GetX(); // GetX() gives current X based on L/R margin
        $currentY = $this->GetY();

        foreach ($rowData as $key => $data) {
            if (isset($_widths[$key])) { // Ensure width is defined for this cell
                $align = $_alignments[$key] ?? ($this->getRTL() ? 'R' : 'L');
                // If data is purely numeric, consider centering or right-aligning for LTR
                if (is_numeric($data) && !is_string($data) && !$this->getRTL()) $align = 'R';
                else if (is_numeric($data) && !is_string($data) && $this->getRTL()) $align = 'L';


                $this->MultiCell($_widths[$key], $rowHeight, (string)$data, 1, $align, $fill, 0, $currentX, $currentY, true, 0, false, true, $rowHeight, 'M');
                $currentX += $_widths[$key];
            }
        }
        $this->Ln($rowHeight);
    }

    // Method for drawing a summary row, similar to DrawTableRow but can have different styling
    public function DrawSummaryRow(array $rowData, array $widths = null, array $alignments = null, int $baseLineHeight = 7, array $fillColor = [220,220,220], string $fontStyle = 'B', float $fontSize = 7.5)
    {
        $_widths = $widths ?? $this->tableWidths;
        $_alignments = $alignments ?? $this->tableAlignments;

        if (empty($rowData) || empty($_widths)) {
            return;
        }
        
        $this->SetFont($this->defaultFont, $fontStyle, $fontSize);
        $this->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
        $this->SetTextColor(0);
        $this->SetDrawColor(128, 128, 128);
        $this->SetLineWidth(0.15);

        $maxLines = 1;
        foreach ($rowData as $key => $data) {
             if (isset($_widths[$key])) {
                $numLines = $this->getNumLines((string)$data, $_widths[$key]);
                if ($numLines > $maxLines) $maxLines = $numLines;
            }
        }
        $rowHeight = $baseLineHeight * $maxLines;
        if ($maxLines > 1) $rowHeight += ($maxLines * 0.5);

        $pageBreakTrigger = $this->getPageHeight() - $this->getBreakMargin();
        if ($this->GetY() + $rowHeight > $pageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
            $this->DrawTableHeader(); // Re-draw table header
            $this->SetFont($this->defaultFont, $fontStyle, $fontSize); // Reset font for summary
            $this->SetFillColor($fillColor[0], $fillColor[1], $fillColor[2]);
        }

        $currentX = $this->GetX();
        $currentY = $this->GetY();

        // For summary, the first cell might span multiple conceptual columns
        if (isset($rowData[0]) && isset($_alignments[0]) && $_alignments[0] === 'SPAN') {
            $spanWidth = 0;
            $spanCount = (int)($rowData[1] ?? 1); // Expecting second element to be span count if first is SPAN
            for($i=0; $i < $spanCount; $i++) {
                if(isset($_widths[$i])) $spanWidth += $_widths[$i];
            }
            $this->MultiCell($spanWidth, $rowHeight, (string)$rowData[0], 1, ($this->getRTL() ? 'R' : 'L'), true, 0, $currentX, $currentY, true, 0, false, true, $rowHeight, 'M');
            $currentX += $spanWidth;
            $startIndex = $spanCount;
        } else {
            $startIndex = 0;
        }


        for ($key = $startIndex; $key < count($rowData); ++$key) {
             if (isset($_widths[$key]) && isset($rowData[$key])) { // Check if data for this conceptual col exists
                $align = $_alignments[$key] ?? ($this->getRTL() ? 'R' : 'L');
                if (is_numeric($rowData[$key]) && !is_string($rowData[$key])) $align = 'C'; // Center numbers

                $this->MultiCell($_widths[$key], $rowHeight, (string)$rowData[$key], 1, $align, true, 0, $currentX, $currentY, true, 0, false, true, $rowHeight, 'M');
                $currentX += $_widths[$key];
            } elseif (isset($_widths[$key])) { // Draw empty cell if no data but width defined
                 $this->MultiCell($_widths[$key], $rowHeight, '', 1, 'L', true, 0, $currentX, $currentY, true, 0, false, true, $rowHeight, 'M');
                 $currentX += $_widths[$key];
            }
        }
        $this->Ln($rowHeight);
    }
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