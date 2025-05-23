<?php // app/Services/Pdf/MyCustomTCPDF.php

namespace App\Services\Pdf;

use TCPDF;
use App\Models\Setting; // Example: If you fetch settings from your Setting model

// It's generally better if TCPDF's own config handles these.
// Only define them if you are consistently getting "constant not defined" errors
// and TCPDF isn't picking up its internal configuration correctly in your environment.
// Make sure the paths are correct for your Laravel setup if you uncomment them.
// if (!defined('K_PATH_MAIN')) { define('K_PATH_MAIN', rtrim(base_path(), '/').'/'); }
// if (!defined('K_PATH_URL')) { define('K_PATH_URL', rtrim(url('/'), '/').'/'); }
// if (!defined('K_PATH_FONTS')) { define('K_PATH_FONTS', base_path('vendor/tecnickcom/tcpdf/fonts/')); }
// if (!defined('K_PATH_IMAGES')) { define('K_PATH_IMAGES', public_path('images/pdf_assets/')); }


class MyCustomTCPDF extends TCPDF
{
    protected string $reportTitle;
    protected string $filterCriteria;
    
    protected string $companyName;
    protected string $companyAddress;
    protected ?string $companyLogoPath; // Path to your company logo
    protected ?string $companyPhone;
    protected ?string $companyEmail;
    protected ?string $companyVatin;
    protected ?string $companyCr;


    // Use a font known for good Arabic support in TCPDF
    protected string $defaultFontFamily = 'arial'; // Good general-purpose sans-serif for Arabic
    protected int $defaultFontSize = 9;
    protected string $defaultFontBold = 'arial'; // Bold variant of dejavusans
    protected string $defaultFontItalic = 'arial'; // Italic variant
    protected string $defaultMonospacedFont = 'arial';

    public function __construct(
        string $reportTitle = 'تقرير', 
        string $filterCriteria = '',
        string $orientation = 'P', 
        string $unit = 'mm', 
        mixed $format = 'A4', 
        bool $unicode = true, 
        string $encoding = 'UTF-8', 
        bool $diskcache = false, 
        bool $pdfa = false
    ) {
        // 1. Call parent constructor FIRST
        parent::__construct($orientation, $unit, $format, $unicode, $encoding, $diskcache, $pdfa);

        // 2. Set your custom class properties
        $this->reportTitle = $reportTitle;
        $this->filterCriteria = $filterCriteria;

        // 3. Load company/application specific settings (ideally from a config or settings service)
        // For example, if you have a Setting model and a helper to get values:
        // $appSettings = Setting::instance();
        // $this->companyName = $appSettings?->hospital_name ?: (config('app.name', 'اسم نظامك الطبي'));
        // $this->companyAddress = $appSettings?->address ?: 'عنوان الشركة هنا';
        // $this->companyPhone = $appSettings?->phone ?: '';
        // $this->companyEmail = $appSettings?->email ?: '';
        // $this->companyVatin = $appSettings?->vatin ?: '';
        // $this->companyCr = $appSettings?->cr ?: '';
        // $logoPath = $appSettings?->logo_base64; // If storing base64 in settings
        // if ($logoPath && str_starts_with($logoPath, 'data:image')) {
        //     $this->companyLogoPath = $logoPath; // TCPDF can handle base64 image strings
        // } else if ($logoPath) {
        //     $this->companyLogoPath = storage_path('app/public/' . $logoPath); // If storing path relative to storage/app/public
        // } else {
        //     $this->companyLogoPath = null;
        // }

        // Using placeholders for now:
        $this->companyName = config('app.name', 'المركز الطبي المتميز');
        $this->companyAddress = 'الخرطوم، شارع الجمهورية، مقابل برج الاتصالات';
        $this->companyPhone = '0123456789';
        $this->companyEmail = 'info@medicalcenter.sd';
        $this->companyVatin = 'VAT: 123456789';
        $this->companyCr = 'CR: 98765';
        $this->companyLogoPath = null; // Example: public_path('images/report_logo.png');

        // 4. Set PDF metadata and default properties
        $this->SetCreator(config('app.name'));
        $this->SetAuthor($this->companyName);
        $this->SetTitle($this->reportTitle); // Set the main document title
        $this->SetSubject($this->reportTitle); // Subject can also be the report title
        $this->SetKeywords('تقرير, طبي, ' . $this->reportTitle);

        $this->setHeaderFont([$this->defaultFontBold, '', $this->defaultFontSize + 1]); // Slightly larger for header text
        $this->setFooterFont([$this->defaultFontFamily, 'I', $this->defaultFontSize - 2]); // Italic, smaller for footer

        $this->SetDefaultMonospacedFont($this->defaultMonospacedFont);

        // Margins: Left, Top, Right. Top margin increased for custom header.
        $this->SetMargins(10, 40, 10); // Increased top margin for more header space
        $this->SetHeaderMargin(5);    // Distance from top of page to start of header content block
        $this->SetFooterMargin(10);   // Distance from bottom of page to start of footer content block

        $this->SetAutoPageBreak(TRUE, 20); // Enable auto page break, 20mm from bottom
        
        $this->setImageScale(PDF_IMAGE_SCALE_RATIO); // Default TCPDF constant
        $this->setFontSubsetting(true); // Recommended for better font handling

        // Set default document font
        $this->SetFont($this->defaultFontFamily, '', $this->defaultFontSize);

        // Set language array for better RTL support and metadata
        $currentLang = app()->getLocale(); // Get current app locale (e.g., 'ar', 'en')
        $this->setLanguageArray([
            'a_meta_charset' => 'UTF-8',
            'a_meta_dir' => ($currentLang === 'ar' ? 'rtl' : 'ltr'), // Dynamic direction
            'a_meta_language' => $currentLang,
            'w_page' => ($currentLang === 'ar' ? 'صفحة' : 'Page'),
        ]);

        // Set default RTL mode based on language. Can be overridden per cell/multicell.
        $this->setRTL($currentLang === 'ar');
    }

    // Override Header() method
    public function Header()
    {
        $isRTL = $this->getRTL();
        $currentY = 8; // Initial Y position for header elements

        // Logo
        if ($this->companyLogoPath && (file_exists($this->companyLogoPath) || str_starts_with($this->companyLogoPath, 'data:image'))) {
            $logoX = $isRTL ? ($this->GetPageWidth() - $this->original_rMargin - 20) : $this->original_lMargin;
            // '@' before $this->companyLogoPath tells TCPDF it might be base64 or a URL that needs fetching
            $this->Image(str_starts_with($this->companyLogoPath, 'data:image') ? '@'.base64_decode(substr($this->companyLogoPath, strpos($this->companyLogoPath, ',')+1)) : $this->companyLogoPath, 
                         $logoX, $currentY, 20, 0, '', '', 'T', false, 300, '', false, false, 0);
            $logoHeight = $this->getImageRBY() - $currentY > 15 ? $this->getImageRBY() - $currentY : 15; // Estimate logo height or set fixed
        } else {
            $logoHeight = 0; // No logo, no extra height needed for it
        }
        
        // Text content area
        $textStartX = $isRTL ? $this->original_lMargin : ($this->original_lMargin + ($this->companyLogoPath ? 22 : 0));
        $textWidth = $this->GetPageWidth() - $this->original_lMargin - $this->original_rMargin - ($this->companyLogoPath ? 25 : 0);
        $initialTextY = $currentY; // Y position for text block

        $this->SetFont($this->defaultFontBold, 'B', 12);
        $this->MultiCell($textWidth, 6, $this->companyName, 0, $isRTL ? 'R':'L', false, 1, $textStartX, $initialTextY, true, 0, false, true, 0, 'T', false);
        
        $currentTextY = $this->GetY();
        $this->SetFont($this->defaultFontFamily, '', 7);
        $addressLine = $this->companyAddress;
        if($this->companyPhone) $addressLine .= ($isRTL ? " \n" : " | ") . ($isRTL ? "" : "Tel: ") . $this->companyPhone;
        if($this->companyEmail) $addressLine .= ($isRTL ? " \n" : " | ") . ($isRTL ? "" : "Email: ") . $this->companyEmail;
        $this->MultiCell($textWidth, 3.5, $addressLine, 0, $isRTL ? 'R':'L', false, 1, $textStartX, $currentTextY, true, 0, false, true, 0, 'T', false);
        
        $currentTextY = $this->GetY();
        $vatCrLine = "";
        if($this->companyVatin) $vatCrLine .= $this->companyVatin;
        if($this->companyCr) $vatCrLine .= ($vatCrLine ? ($isRTL ? " | " : " | ") : "") . $this->companyCr;
        if($vatCrLine) $this->MultiCell($textWidth, 3.5, $vatCrLine, 0, $isRTL ? 'R':'L', false, 1, $textStartX, $currentTextY, true, 0, false, true, 0, 'T', false);

        // Determine Y position after logo and text block for Report Title
        $currentY = max($this->GetY(), $initialTextY + $logoHeight) + 1;
        if ($currentY < 25) $currentY = 25; // Ensure minimum space for title after header info

        $this->SetY($currentY);
        $this->SetFont($this->defaultFontBold, 'BU', 11); // Report Title - Bold, Underlined
        $this->Cell(0, 6, $this->reportTitle, 0, 1, 'C', false, '', 0, false, 'T', 'M');

        if (!empty($this->filterCriteria)) {
            $this->SetFont($this->defaultFontFamily, '', 8);
            $this->Cell(0, 4, $this->filterCriteria, 0, 1, 'C', false, '', 0, false, 'T', 'M');
        }
        
        $this->Ln(1); // Space before the line
        // Header bottom line
        $this->Line($this->original_lMargin, $this->GetY(), $this->GetPageWidth() - $this->original_rMargin, $this->GetY());
        // $this->SetY($this->GetY() + 0.5); // Set Y below the line for content start - this is now done by SetTopMargin
    }

    // Override Footer() method
    public function Footer()
    {
        $isRTL = $this->getRTL();
        $this->SetY(-15); // Position at 15 mm from bottom
        $this->SetFont($this->defaultFontFamily, 'I', 7); // Italic, smaller font

        // Page number
        $pageNumberText = $this->l['w_page'].' '.$this->getAliasNumPage().' / '.$this->getAliasNbPages();
        $this->Cell(0, 10, $pageNumberText, 0, false, 'C', 0, '', 0, false, 'T', 'M');

        // Print date on one side of the footer
        $this->SetFont($this->defaultFontFamily, '', 7); // Non-italic for date
        $dateText = date('Y-m-d H:i:s');
        if ($isRTL) {
            $this->SetX($this->original_lMargin);
            $this->Cell(0, 10, $dateText, 0, false, 'L', 0, '', 0, false, 'T', 'M');
        } else {
            $this->SetX($this->original_rMargin); // This positions from the right, Cell needs negative width or use GetPageWidth()
             $this->SetX($this->GetPageWidth() - $this->original_rMargin - $this->GetStringWidth($dateText));
            $this->Cell($this->GetStringWidth($dateText), 10, $dateText, 0, false, 'R', 0, '', 0, false, 'T', 'M');
        }
    }

    /**
     * Helper function to draw a table header row.
     * @param array $headers Array of header texts
     * @param array $widths Array of column widths
     * @param array $alignments Array of column alignments ('L', 'C', 'R')
     */
    public function DrawTableHeader(array $headers, array $widths, array $alignments = [])
    {
        $this->SetFont($this->defaultFontBold, 'B', $this->defaultFontSize -1);
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0);
        $this->SetDrawColor(150, 150, 150); // Border color for header
        $this->SetLineWidth(0.1);
        $border = 'LTRB';
        $lineHeight = 6; // Header row height

        foreach ($headers as $i => $header) {
            $align = $alignments[$i] ?? ($this->getRTL() ? 'R' : 'L'); // Default align based on RTL
            if ($header === 'م' || $header === 'ID') $align = 'C'; // Center ID columns
            
            $this->Cell($widths[$i], $lineHeight, $header, $border, 0, $align, true);
        }
        $this->Ln($lineHeight);
    }

    /**
     * Helper function to draw a table data row using MultiCell for auto height adjustment.
     * @param array $data Array of cell data for the row
     * @param array $widths Array of column widths
     * @param array $alignments Array of column alignments
     * @param bool $fill Fill color for the row (for zebra striping)
     * @param int $minRowHeight Minimum height for the row
     */
    public function DrawTableRow(array $data, array $widths, array $alignments = [], bool $fill = false, int $minRowHeight = 5)
    {
        $this->SetFont($this->defaultFontFamily, '', $this->defaultFontSize - 1);
        $this->SetFillColor($fill ? 245 : 255, $fill ? 245 : 255, $fill ? 245 : 255);
        $this->SetTextColor(0);
        $this->SetDrawColor(200,200,200); // Lighter border for data rows
        $this->SetLineWidth(0.1);
        $border = 'LR'; // Left and Right borders for cells, top/bottom drawn by Ln or other cells

        // Calculate max number of lines for the current row to set a consistent height
        $maxLines = 0;
        for ($i = 0; $i < count($data); $i++) {
            $numLines = $this->getNumLines((string)$data[$i], $widths[$i]);
            if ($numLines > $maxLines) {
                $maxLines = $numLines;
            }
        }
        if ($maxLines == 0) $maxLines = 1; // Ensure at least one line

        $cellPadding = $this->getCellPaddings();
        // Estimate line height based on current font size. This is an approximation.
        // TCPDF's getLineHeight is usually FontHeight * CellHeightRatio. FontHeight is FontSize * some_factor.
        $lineHeight = $this->FontSize * $this->getCellHeightRatio() * 0.352777778; // Convert Pt to mm approx
        if($lineHeight < 3) $lineHeight = 3.5; // Min line height

        $rowHeight = ($maxLines * $lineHeight) + $cellPadding['T'] + $cellPadding['B'] + 0.5;
        if ($rowHeight < $minRowHeight) $rowHeight = $minRowHeight;
        
        // If it's the first cell of a new line, add top border
        if ($this->GetY() + $rowHeight > $this->getPageHeight() - $this->getBreakMargin()) {
            $this->AddPage($this->CurOrientation); // Add new page if not enough space
            // Redraw header on new page
            // This needs to be handled by TCPDF's auto page break and Header() method
            // For this helper, we just ensure there's space.
        }

        $currentX = $this->GetX();
        $currentY = $this->GetY();

        // Draw top border for the row
        $this->Line($currentX, $currentY, $this->GetPageWidth() - $this->original_rMargin, $currentY);


        for ($i = 0; $i < count($data); $i++) {
            $align = $alignments[$i] ?? ($this->getRTL() ? 'R' : 'L');
            if ($data[$i] === 'م' || $data[$i] === 'ID' || is_numeric($data[$i])) $align = 'C'; // Center ID/numeric

            $this->MultiCell($widths[$i], $rowHeight, (string)$data[$i], $border, $align, $fill, 0, $currentX, $currentY, true, 0, false, true, $rowHeight, 'M');
            $currentX += $widths[$i];
        }
        $this->Ln($rowHeight); // Move to next line based on calculated row height

        // Draw bottom border for the row - last cell MultiCell with ln=1 handles this
        // If not, draw explicitly:
        // $this->Line($this->original_lMargin, $this->GetY(), $this->GetPageWidth() - $this->original_rMargin, $this->GetY());
    }

    // Your setThermalDefaults method
    public function setThermalDefaults(float $width = 80, float $height = 200): void
    {
        $pageLayout = [$width, $height];
        $this->AddPage($this->CurOrientation, $pageLayout);
        $this->setPrintHeader(false);
        $this->setPrintFooter(false);
        $this->SetMargins(3, 3, 3);
        $this->SetAutoPageBreak(TRUE, 5);
        $this->SetFont($this->defaultMonospacedFont, '', 8);
        $currentLang = app()->getLocale();
        $this->setRTL($currentLang === 'ar');
    }

    public function getDefaultFontFamily()
    {
        return $this->defaultFontFamily;
    }
}