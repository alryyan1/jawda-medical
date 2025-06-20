<?php

namespace App\Services\Pdf; // Make sure this namespace is correct for your application

use App\Models\DoctorVisit;
use App\Models\Setting;   // Assuming Setting model is in App\Models
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use TCPDF;

class MyCustomTCPDF extends TCPDF
{
    protected string $reportTitle = 'Report';
    protected string $filterCriteria = '';
    protected ?Setting $currentSettings = null; // Type hint Setting model
    protected string $pageOrientation = 'P';
    protected string $defaultFont = 'arial'; // Default to arial as per your code
    public $head ;
    public $foot ;
    // Table header properties to be accessible for re-drawing
    protected array $tableHeaders = [];
    protected array $tableWidths = [];
    protected array $tableAlignments = [];
    protected int $defaultCellHeight = 7; // Default height for a single line cell
    public  $currentVisit = null; // Made public for easier access if needed

    // Store concatenated test names for header
    protected string $concatenatedTestNamesForHeader = '';
    public function __construct(
        string $reportTitle = 'Report',
         $visitContext = null,
        string $orientation = 'P',
        string $unit = 'mm',
        $format = 'A4',
        bool $unicode = true,
        string $encoding = 'UTF-8',
        bool $diskcache = false,
        bool $pdfa = false
    ) {
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);

        $this->reportTitle = $reportTitle;
        $this->currentVisit = $visitContext;
        $this->currentSettings = Setting::instance(); // Make sure Setting::instance() is reliable

        // Font setup should ideally be global or in a base PDF class constructor
        // For this example, assuming defaultFont is set and available
        // Example: $this->defaultFont = TCPDF_FONTS::addTTFfont(public_path('fonts/aealarabiya.ttf'), 'TrueTypeUnicode', '', 32);

        $this->SetCreator(config('app.name', 'Lab System'));
        $this->SetAuthor($this->currentSettings?->hospital_name ?? $this->currentSettings?->lab_name ?? config('app.name'));
        $this->SetTitle($this->reportTitle . ($this->currentVisit ? " - Visit #{$this->currentVisit->id}" : ""));
        $this->SetSubject('Lab Result Report');
        $this->setLanguageArray(['a_meta_charset' => 'UTF-8', 'a_meta_dir' => 'rtl', 'a_meta_language' => 'ar', 'w_page' => 'صفحة']);
        $this->setRTL(true);

        $this->SetMargins(10, 45, 10); // L, T, R - Top margin increased for letterhead
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(20); // Increased for more robust footer
        $this->SetAutoPageBreak(true, 25); // Bottom margin for auto page break

        if ($this->currentVisit) {
            $this->concatenatedTestNamesForHeader = $this->currentVisit->patientLabRequests
                ->where('hidden', false)->where('valid', true)
                ->map(fn($lr) => $lr->mainTest?->main_test_name)
                ->filter()->unique()->implode(' | '); // Use a clear separator
        }
    }


    public function drawTextWatermark(string $text, string $font = '', int $fontSize = 45, int $angle = 45, array $color = [230, 230, 230])
    {
        // Save current graphic states
        $this->StartTransform();

        // Set transparency
        $this->SetAlpha(0.2);

        // Set font
        $this->SetFont($font ?: $this->defaultFont, 'B', $fontSize);
        $this->SetTextColor($color[0], $color[1], $color[2]);

        // Calculate position for centering (this is where checkPageRegions was used)
        // We can approximate center or use page dimensions
        $pageWidth = $this->getPageWidth();
        $pageHeight = $this->getPageHeight();
        $textWidth = $this->GetStringWidth($text);

        // Position the rotation point (approximately center of where text will be)
        // This might need some trial and error to get perfect centering after rotation
        $rotateX = $pageWidth / 2;
        $rotateY = $pageHeight / 2; 
        
        $this->Rotate($angle, $rotateX, $rotateY);

        // Calculate text position to be centered around the rotation point
        // The exact calculation for centering rotated text is complex.
        // A simpler approach is to position it before rotation and let TCPDF handle it.
        // Or adjust based on StringWidth and angle.
        // For a 45-degree angle, placing it somewhat offset from the rotation point often works.
        $textX = $rotateX - ($textWidth / 2) ; // Start by centering before rotation effects
        $textY = $rotateY - ($fontSize / 2);   // Adjust based on font size

        // Adjustments based on empirical testing for 45 deg rotation might be needed.
        // The original TCPDF example for watermark often just places it and rotates.
        // Let's try a simpler positioning:
        $this->Text($pageWidth * 0.2, $pageHeight * 0.4, $text);


        // Restore transformations and alpha
        $this->StopTransform();
        $this->SetAlpha(1);
        $this->SetTextColor(0); // Reset to black
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
        if (!$this->currentSettings) return; // Should not happen if Setting::instance() is robust

        $settings = $this->currentSettings;
        $isRTL = $this->getRTL();
        $pageWidth = $this->getPageWidth();
        $lMargin = $this->getMargins()['left'];
        $rMargin = $this->getMargins()['right'];
        $contentWidth = $pageWidth - $lMargin - $rMargin;

        // --- Letterhead Section ---
        $logoBase64 = $settings->report_header_logo_base64 ?? $settings->logo_base64;
        $headerTextLine1 = $settings->report_header_company_name ?? $settings->hospital_name ?? $settings->lab_name ?? config('app.name');
        $headerTextLine2 = $settings->report_header_address_line1 ?? $settings->address;
        $headerTextLine3 = ($settings->report_header_phone ? ($isRTL ? "هاتف: " : "Phone: ") . $settings->report_header_phone : '') .
                           ($settings->report_header_email ? ($isRTL ? " | إيميل: " : " | Email: ") . $settings->report_header_email : '');
        $headerTextLine4 = ($settings->report_header_cr ? ($isRTL ? "س.ت: " : "CR: ") . $settings->report_header_cr : '') .
                           ($settings->report_header_vatin ? ($isRTL ? " | ر.ض: " : " | VATIN: ") . $settings->report_header_vatin : '');


        $logoHeight = 20; // Max logo height
        $logoWidth = 20;  // Max logo width
        $logoX = $isRTL ? $pageWidth - $rMargin - $logoWidth : $lMargin;
        $textX = $isRTL ? $lMargin : $lMargin + $logoWidth + 5;
        $textWidth = $contentWidth - ($logoWidth + 5); // Adjust text width if logo is present
        $logoRendered = false;

        if ($logoBase64 && str_starts_with($logoBase64, 'data:image')) {
            try {
                $imgData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $logoBase64));
                if ($imgData) {
                    $this->Image('@'.$imgData, $logoX, $this->getHeaderMargin() + 2, $logoWidth, $logoHeight, '', '', 'T', false, 300, '', false, false, 0);
                    $logoRendered = true;
                }
            } catch (\Exception $e) { Log::error("PDF Header Logo Error: ".$e->getMessage()); }
        }
        if (!$logoRendered) { // If no logo, text takes full width
            $textX = $lMargin;
            $textWidth = $contentWidth;
        }
        
        $this->SetFont($this->defaultFont, 'B', 11);
        $this->SetXY($textX, $this->getHeaderMargin() + 2);
        $this->MultiCell($textWidth, 5, $headerTextLine1, 0, $isRTL ? 'R' : 'L', false, 1);
        
        $this->SetFont($this->defaultFont, '', 7.5);
        if ($headerTextLine2) {
            $this->SetX($textX); $this->MultiCell($textWidth, 3.5, $headerTextLine2, 0, $isRTL ? 'R' : 'L', false, 1);
        }
        if ($headerTextLine3) {
            $this->SetX($textX); $this->MultiCell($textWidth, 3.5, $headerTextLine3, 0, $isRTL ? 'R' : 'L', false, 1);
        }
        if ($headerTextLine4) {
            $this->SetX($textX); $this->MultiCell($textWidth, 3.5, $headerTextLine4, 0, $isRTL ? 'R' : 'L', false, 1);
        }

        // Determine Y position after letterhead details, considering logo height
        $currentY = $this->GetY();
        $logoEndY = $logoRendered ? ($this->getHeaderMargin() + 2 + $logoHeight) : $this->getHeaderMargin() + 2;
        $this->SetY(max($currentY, $logoEndY) + 1); // Ensure Y is below tallest element

        // --- Report Title ---
        $this->SetFont($this->defaultFont, 'B', 14); // Main report title font
        $this->Cell(0, 7, $this->reportTitle, 0, 1, 'C'); // Centered report title
        $this->Ln(1);

        // --- Patient Info Block ---
        if ($this->currentVisit) {
            // Call the dedicated method to draw patient info. This method is now in ReportController.
            // For this to work directly, ReportController needs to be passed or its method made static
            // Or, move this logic *into* MyCustomTCPDF if it's always the same header structure.
            // For now, let's assume ReportController will call a method to draw this *after* AddPage()
            // and before drawSingleMainTestBlock.
            // The alternative is to pass ReportController instance or make drawPatientDoctorInfo public static.
            // The original request implied a custom header *within TCPDF*.
            $this->drawPatientDoctorInfoForHeader(); // New helper method within MyCustomTCPDF
        }
        
        // Final line before main content area begins
        $this->Line($lMargin, $this->GetY(), $pageWidth - $rMargin, $this->GetY());
        // SetY below this line is important for where the content starts.
        // The Top Margin in $this->SetMargins(10, 45, 10) is what dictates the start of content after header.
        // Header() method draws *within* this top margin area.
    }

    // New helper within MyCustomTCPDF for the patient info block
    protected function drawPatientDoctorInfoForHeader()
    {
        if (!$this->currentVisit) return;
        $patient = $this->currentVisit->patient;
        $visit = $this->currentVisit;
        $isRTL = $this->getRTL();
        $contentWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];
        $lineHeight = 4.5;
        $labelWidth = 28;
        $valueIndent = 2;

        $this->SetFont($this->defaultFont, '', 8.5);
        $yStartBlock = $this->GetY();

        // Column 1 (Right in RTL, Left in LTR)
        $col1X = $isRTL ? ($this->getPageWidth() - $this->getMargins()['right'] - ($contentWidth / 2)) : $this->getMargins()['left'];
        $colWidth = $contentWidth / 2 - 2; // -2 for a small gap between columns

        $this->SetXY($col1X, $yStartBlock);
        $this->SetFont($this->defaultFont, 'B', 8.5); $this->Cell($labelWidth, $lineHeight, ($isRTL ? 'اسم المريض:' : 'Patient Name:'), 0, 0, $isRTL ? 'R' : 'L');
        $this->SetFont($this->defaultFont, '', 8.5); $this->MultiCell($colWidth - $labelWidth - $valueIndent, $lineHeight, $patient->name, 0, $isRTL ? 'R' : 'L', false, 1, $col1X + $labelWidth + $valueIndent);
        
        $this->SetX($col1X);
        $this->SetFont($this->defaultFont, 'B', 8.5); $this->Cell($labelWidth, $lineHeight, ($isRTL ? 'رقم المريض:' : 'Patient ID:'), 0, 0, $isRTL ? 'R' : 'L');
        $this->SetFont($this->defaultFont, '', 8.5); $this->Cell($colWidth - $labelWidth - $valueIndent, $lineHeight, $patient->id, 0, 1, $isRTL ? 'R' : 'L');

        $ageGender = ($patient->age_year ?? 'N/A') . ($isRTL ? 'س' : 'Y') . " / " . ($patient->gender ? substr(strtoupper($patient->gender),0,1) : 'U');
        $this->SetX($col1X);
        $this->SetFont($this->defaultFont, 'B', 8.5); $this->Cell($labelWidth, $lineHeight, ($isRTL ? 'العمر/الجنس:' : 'Age/Sex:'), 0, 0, $isRTL ? 'R' : 'L');
        $this->SetFont($this->defaultFont, '', 8.5); $this->Cell($colWidth - $labelWidth - $valueIndent, $lineHeight, $ageGender, 0, 1, $isRTL ? 'R' : 'L');

        $this->SetX($col1X);
        $this->SetFont($this->defaultFont, 'B', 8.5); $this->Cell($labelWidth, $lineHeight, ($isRTL ? 'طبيب محول:' : 'Ref. Doctor:'), 0, 0, $isRTL ? 'R' : 'L');
        $this->SetFont($this->defaultFont, '', 8.5); $this->Cell($colWidth - $labelWidth - $valueIndent, $lineHeight, $visit->doctor?->name ?? ($isRTL ? 'غير محدد' : 'N/A'), 0, 1, $isRTL ? 'R' : 'L');


        // Column 2 (Left in RTL, Right in LTR)
        $yCol1End = $this->GetY(); // Y after drawing Col1
        $this->SetY($yStartBlock);   // Reset Y to start of block for Col2
        $col2X = $isRTL ? $this->getMargins()['left'] : $col1X + $colWidth + 4; // +4 for gap

        $this->SetXY($col2X, $this->GetY());
        $this->SetFont($this->defaultFont, 'B', 8.5); $this->Cell($labelWidth, $lineHeight, ($isRTL ? 'رقم الزيارة:' : 'Visit ID:'), 0, 0, $isRTL ? 'R' : 'L');
        $this->SetFont($this->defaultFont, '', 8.5); $this->Cell($colWidth - $labelWidth - $valueIndent, $lineHeight, $visit->id, 0, 1, $isRTL ? 'R' : 'L');
        
        $sampleIds = $visit->patientLabRequests->pluck('sample_id')->filter()->unique()->implode(', ') ?: $visit->patientLabRequests->pluck('id')->filter()->unique()->implode('REQ-');
        $this->SetX($col2X);
        $this->SetFont($this->defaultFont, 'B', 8.5); $this->Cell($labelWidth, $lineHeight, ($isRTL ? 'رقم العينة:' : 'Sample ID:'), 0, 0, $isRTL ? 'R' : 'L');
        $this->SetFont($this->defaultFont, '', 8.5); $this->MultiCell($colWidth - $labelWidth - $valueIndent, $lineHeight, $sampleIds ?: ($isRTL ? 'غير محدد' : 'N/A'), 0, $isRTL ? 'R' : 'L', false, 1, $col2X + $labelWidth + $valueIndent);
        
        $this->SetX($col2X);
        $this->SetFont($this->defaultFont, 'B', 8.5); $this->Cell($labelWidth, $lineHeight, ($isRTL ? 'تاريخ الطلب:' : 'Request Date:'), 0, 0, $isRTL ? 'R' : 'L');
        $this->SetFont($this->defaultFont, '', 8.5); $this->Cell($colWidth - $labelWidth - $valueIndent, $lineHeight, $visit->created_at->format('Y-m-d H:i'), 0, 1, $isRTL ? 'R' : 'L');

        $this->SetX($col2X);
        $this->SetFont($this->defaultFont, 'B', 8.5); $this->Cell($labelWidth, $lineHeight, ($isRTL ? 'تاريخ التقرير:' : 'Report Date:'), 0, 0, $isRTL ? 'R' : 'L');
        $this->SetFont($this->defaultFont, '', 8.5); $this->Cell($colWidth - $labelWidth - $valueIndent, $lineHeight, Carbon::now()->format('Y-m-d H:i'), 0, 1, $isRTL ? 'R' : 'L');
        
        // Set Y to be below the tallest of the two columns
        $this->SetY(max($yCol1End, $this->GetY()));
        $this->Ln(2); // Space after patient info block

        // Display concatenated test names if they exist
        if ($this->currentVisit->patient->tests_concatinated()) {
            $this->SetFont($this->defaultFont, 'B', 7.5);
            $this->SetFillColor(245,245,245);
            $this->MultiCell($contentWidth, $lineHeight - 1, ($isRTL ? 'الفحوصات المطلوبة: ' : 'Requested Tests: ') . $this->concatenatedTestNamesForHeader, 'LTRB', $isRTL ? 'R' : 'L', true, 1);
            $this->Ln(1);
        }
    }
    // public function Footer()
    // {
    //     $isRTL = $this->getRTL();
    //     $this->SetY(-15); // Position at 1.5 cm from bottom
    //     $this->SetFont($this->defaultFont, 'I', 7);
    //     $this->SetTextColor(100, 100, 100);

    //     $pageNumberText = ($isRTL ? 'صفحة ' : 'Page ') . $this->getAliasNumPage() . ($isRTL ? ' من ' : ' of ') . $this->getAliasNbPages();
    //     $this->Cell(0, 10, $pageNumberText, 0, false, 'C', 0, '', 0, false, 'T', 'M');

    //     $printDate = ($isRTL ? 'تاريخ الطباعة: ' : 'Printed: ') . Carbon::now()->setTimezone(config('app.timezone', 'UTC'))->format('Y-m-d h:i A');
    //     $this->Cell(0, 10, $printDate, 0, false, $isRTL ? 'L' : 'R', 0, '', 0, false, 'T', 'M');
    // }
    public function Footer()
    {
        // ... (Your detailed signature and page number footer from previous version) ...
        // ... Or a simplified version:
        $isRTL = $this->getRTL();
        $this->SetY(-23); // Adjusted for potentially more signature lines
        $this->SetFont($this->defaultFont, 'I', 7);
        $this->SetTextColor(100);
        $this->Cell(0, 5, ($isRTL ? 'صفحة ' : 'Page ') . $this->getAliasNumPage() . '/' . $this->getAliasNbPages(), 'T', 0, 'C');
        $this->Cell(0, 5, ($isRTL ? 'تاريخ الطباعة: ' : 'Printed: ') . Carbon::now()->format('Y-m-d H:i'), 'T', 0, $isRTL ? 'L' : 'R');

        // Example simplified signature lines
        $this->Ln(6);
        $sigBlockWidth = ($this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right']) / 2 - 5;
        $this->Cell($sigBlockWidth, 5, ($isRTL ? 'فني المختبر:' : 'Lab Technician:'), 'T', 0, 'C');
        $this->Cell(10,5,'',0,0); // Spacer
        $this->Cell($sigBlockWidth, 5, ($isRTL ? 'مدير المختبر/المراجع:' : 'Lab Director/Auditor:'), 'T', 1, 'C');

        if ($this->currentSettings?->footer_content) {
            $this->SetY(-10); // Closer to bottom for fixed footer text
            $this->SetFont($this->defaultFont, '', 7);
            $this->MultiCell(0, 4, $this->currentSettings->footer_content, 0, 'C', false, 1);
        }
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