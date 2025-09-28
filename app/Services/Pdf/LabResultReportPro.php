<?php

namespace App\Services\Pdf;

use App\Models\DoctorVisit;
use App\Models\Package;
use App\Models\Setting;
use App\Mypdf\Pdf;
use Illuminate\Support\Facades\Log;

/**
 * LabResultReportPro
 *
 * A modern, professionally-styled lab result report generator.
 *
 * Design goals:
 * - Clear visual hierarchy with strong header and patient info card
 * - Consistent spacing and typography scale
 * - Striped tables with clear borders and header background
 * - Abnormal results highlighted with badges and color
 * - Robust pagination with pre-measurement to avoid row splits
 */
class LabResultReportPro
{
    // Layout constants
    private float $baseLineHeight = 6.0;     // Default line height
    private float $sectionSpacing = 10.0;     // Spacing between sections
    private float $headerSpacing = 8.0;       // Spacing after headers
    private float $smallSpacing = 4.0;        // Minor spacing

    // Theme colors
    private array $colorHeaderFill = [242, 245, 250]; // light blue-ish
    private array $colorBorder = [205, 210, 220];
    private array $colorStripe = [249, 251, 253];
    private array $colorAccent = [33, 94, 180];
    private array $colorDanger = [200, 50, 50];
    private array $colorMuted = [120, 120, 120];

    public function generate(DoctorVisit $doctorVisit, bool $base64 = false): string
    {
        ob_start();

        $patient = $doctorVisit->patient;
        if ($patient?->result_print_date === null) {
            $patient->update(['result_print_date' => now()]);
        }

        /** @var Setting|null $settings */
        $settings = Setting::query()->first();
        $logoName = $settings?->header_base64;
        $footerName = $settings?->footer_base64;
        $publicPath = public_path();

        $pdf = new Pdf('portrait', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->setCreator(PDF_CREATOR);
        $pdf->setAuthor('Jawda Medical');
        $pdf->setTitle('Laboratory Result Report');
        $pdf->setSubject('Patient laboratory results');

        $pdf->setMargins(PDF_MARGIN_LEFT, 70, PDF_MARGIN_RIGHT);
        $pdf->setHeaderMargin(0);
        $pdf->setFooterMargin(0);
        $pdf->setAutoPageBreak(true, 40);

        $pageWidth = $pdf->getPageWidth() - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT;

        // Header renderer
        $pdf->head = function (Pdf $pdf) use ($settings, $logoName, $publicPath, $doctorVisit, $pageWidth) {
            $this->renderHeader($pdf, $settings, $logoName, $publicPath, $doctorVisit, $pageWidth);
        };

        // Footer renderer
        $pdf->foot = function (Pdf $pdf) use ($settings, $footerName, $publicPath, $pageWidth, $patient) {
            $this->renderFooter($pdf, $settings, $footerName, $publicPath, $pageWidth, $patient?->visit_number);
        };

        $pdf->AddPage();

        // Optional watermark
        if ($settings?->show_water_mark && $logoName) {
            $pdf->SetAlpha(0.08);
            $pdf->Image($publicPath . '/' . $logoName, 25, 90, 160, 160, '', '', '', false, 300);
            $pdf->SetAlpha(1);
        }

        // Header band / hero area
        $this->renderHeaderBand($pdf, $settings, $logoName, $publicPath, $pageWidth, $base64);

        // Patient info card
        $this->renderPatientInfoCard($pdf, $doctorVisit, $pageWidth);

        // Content: Lab results
        $this->renderAllPackages($pdf, $patient, $pageWidth);

        $content = $pdf->Output('lab-result.pdf', 'S');
        ob_end_clean();
        return $content;
    }

    // ----- Header / Footer -----

    private function renderHeader(Pdf $pdf, ?Setting $settings, ?string $logoName, string $publicPath, DoctorVisit $doctorVisit, float $pageWidth): void
    {
        $pdf->SetFont('arial', 'b', 16, '', true);
        $pdf->SetTextColor(0, 0, 0);

        $labName = $settings?->lab_name ?: 'Laboratory';
        $pdf->Ln(8);
        $pdf->Cell($pageWidth, 8, $labName, 0, 1, 'C');

        $pdf->SetFont('arial', '', 10, '', true);
        $pdf->SetTextColor(...$this->colorMuted);
        $pdf->Cell($pageWidth, 5, $settings?->lab_subtitle ?: '', 0, 1, 'C');
        $pdf->Ln(2);

        // top hairline
        $pdf->SetDrawColor(...$this->colorBorder);
        $pdf->Line(PDF_MARGIN_LEFT, 24, $pdf->getPageWidth() - PDF_MARGIN_RIGHT, 24);
    }

    private function renderFooter(Pdf $pdf, ?Setting $settings, ?string $footerName, string $publicPath, float $pageWidth, ?string $visitNumber): void
    {
        $pdf->SetFont('arial', '', 9, '', true);
        $pdf->SetTextColor(...$this->colorMuted);

        $pdf->SetY($pdf->getPageHeight() - 30);
        $pdf->Cell($pageWidth / 2, 5, 'Generated: ' . now()->format('Y-m-d H:i'), 0, 0, 'L');
        $pdf->Cell($pageWidth / 2, 5, 'Visit No: ' . ($visitNumber ?: '-'), 0, 1, 'R');

        if ($settings?->footer_content) {
            $pdf->MultiCell($pageWidth, 5, (string)$settings->footer_content, 0, 'C', 0, 1, null, null, true);
        }

        if ($settings?->is_footer && $footerName) {
            $pdf->Image($publicPath . '/' . $footerName, 10, $pdf->GetY() + 4, $pageWidth + 10, 12);
        }
    }

    private function renderHeaderBand(Pdf $pdf, ?Setting $settings, ?string $logoName, string $publicPath, float $pageWidth, bool $base64): void
    {
        // Background band
        $pdf->SetFillColor(...$this->colorHeaderFill);
        $pdf->Rect(PDF_MARGIN_LEFT, 28, $pageWidth, 30, 'F');

        // Logo
        if ($settings?->is_logo && $logoName) {
            $pdf->Image($publicPath . '/' . $logoName, PDF_MARGIN_LEFT + 2, 30, 28, 28);
        } elseif (!$base64 && $settings?->is_header && $logoName) {
            $pdf->Image($publicPath . '/' . $logoName, PDF_MARGIN_LEFT, 30, $pageWidth, 28);
        }

        // Lab contact info
        $pdf->SetXY(PDF_MARGIN_LEFT + 34, 32);
        $pdf->SetFont('arial', 'b', 12, '', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Cell($pageWidth - 36, 7, $settings?->lab_name ?: 'Laboratory', 0, 2, 'L');
        $pdf->SetFont('arial', '', 10, '', true);
        $pdf->SetTextColor(...$this->colorMuted);
        $pdf->MultiCell($pageWidth - 36, 6, trim((string)($settings?->address_line1 . ' ' . $settings?->address_line2)), 0, 'L');

        $pdf->Ln(3);
    }

    private function renderPatientInfoCard(Pdf $pdf, DoctorVisit $visit, float $pageWidth): void
    {
        $patient = $visit->patient;

        $pdf->Ln(4);
        $this->ensureSpaceFor($pdf, 36);

        // Card container
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(...$this->colorBorder);
        $pdf->RoundedRect(PDF_MARGIN_LEFT, $pdf->GetY(), $pageWidth, 28, 2.5, '1111', 'D');
        $y0 = $pdf->GetY();
        $pdf->SetXY(PDF_MARGIN_LEFT + 4, $y0 + 2);

        // Left column
        $pdf->SetFont('arial', 'b', 11, '', true);
        $pdf->Cell(($pageWidth / 4), 6, 'Patient', 0, 0, 'L');
        $pdf->SetFont('arial', '', 11, '', true);
        $pdf->Cell(($pageWidth / 4), 6, (string)$patient?->name, 0, 0, 'L');

        // Middle column
        $pdf->SetFont('arial', 'b', 11, '', true);
        $pdf->Cell(($pageWidth / 4), 6, 'Doctor', 0, 0, 'L');
        $pdf->SetFont('arial', '', 11, '', true);
        $pdf->Cell(($pageWidth / 4), 6, (string)($patient?->doctor?->name ?: '-'), 0, 1, 'L');

        $pdf->SetX(PDF_MARGIN_LEFT + 4);
        $pdf->SetFont('arial', 'b', 11, '', true);
        $pdf->Cell(($pageWidth / 4), 6, 'Visit No', 0, 0, 'L');
        $pdf->SetFont('arial', '', 11, '', true);
        $pdf->Cell(($pageWidth / 4), 6, (string)($patient?->visit_number ?: $visit->id), 0, 0, 'L');

        $pdf->SetFont('arial', 'b', 11, '', true);
        $pdf->Cell(($pageWidth / 4), 6, 'Date', 0, 0, 'L');
        $pdf->SetFont('arial', '', 11, '', true);
        $pdf->Cell(($pageWidth / 4), 6, (string)($patient?->created_at?->format('Y-m-d') ?: now()->format('Y-m-d')), 0, 1, 'L');

        // Requested tests line
        $requested = $visit->patientLabRequests
            ->map(fn($lr) => $lr->mainTest?->main_test_name)
            ->filter()
            ->unique()
            ->implode(' | ');

        $pdf->SetX(PDF_MARGIN_LEFT + 4);
        $pdf->SetFont('arial', 'b', 10, '', true);
        $pdf->Cell(25, 6, 'Requested:', 0, 0, 'L');
        $pdf->SetFont('arial', '', 10, '', true);
        $pdf->MultiCell($pageWidth - 25 - 8, 6, (string)$requested, 0, 'L');

        $pdf->Ln($this->sectionSpacing);
    }

    // ----- Content rendering -----

    private function renderAllPackages(Pdf $pdf, $patient, float $pageWidth): void
    {
        $packages = Package::all();
        $pdf->SetFont('arial', '', 11, '', true);
        foreach ($packages as $package) {
            $mainTests = $patient->labrequests->filter(function ($item) use ($package) {
                return $item->mainTest->pack_id == $package->package_id;
            });

            if ($mainTests->isEmpty()) {
                continue;
            }

            $this->renderPackageSectionHeader($pdf, (string)$package->name, $pageWidth);

            foreach ($mainTests as $requestedMainTest) {
                if ($requestedMainTest->hidden == 0) {
                    continue;
                }

                $hasAnyResult = $requestedMainTest->results->contains(function ($r) {
                    return ($r->result !== '' && $r->result !== 'no sample');
                }) || $requestedMainTest->requestedOrganisms()->count() > 0;

                if (!$hasAnyResult) {
                    continue;
                }

                $this->renderMainTestBlock($pdf, $requestedMainTest, $pageWidth);
                $pdf->Ln($this->sectionSpacing);
            }
        }
    }

    private function renderPackageSectionHeader(Pdf $pdf, string $title, float $pageWidth): void
    {
        $this->ensureSpaceFor($pdf, 14);
        $pdf->SetFillColor(...$this->colorHeaderFill);
        $pdf->SetDrawColor(...$this->colorBorder);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('arial', 'b', 13, '', true);
        $pdf->RoundedRect(PDF_MARGIN_LEFT, $pdf->GetY(), $pageWidth, 10, 2.0, '1111', 'DF');
        $pdf->SetXY(PDF_MARGIN_LEFT + 3, $pdf->GetY() + 2);
        $pdf->Cell($pageWidth - 6, 6, $title, 0, 1, 'L');
        $pdf->Ln($this->smallSpacing);
    }

    private function renderMainTestBlock(Pdf $pdf, $mainTestRequest, float $pageWidth): void
    {
        $mainTest = $mainTestRequest->mainTest;

        // Title
        $this->ensureSpaceFor($pdf, 18);
        $pdf->SetFont('arial', 'b', 12, '', true);
        $pdf->SetTextColor(...$this->colorAccent);
        $pdf->Cell($pageWidth, 6, (string)$mainTest?->main_test_name, 0, 1, 'L');
        $pdf->SetTextColor(0, 0, 0);

        // Table header
        $isDivided = (int)$mainTest?->divided === 1;
        if ($isDivided) {
            $pdf->setEqualColumns(2);
            $pdf->selectColumn(0);
        }

        $colWidthBase = $isDivided ? (($pdf->getPageWidth() - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT) / 8) : ($pageWidth / 4);
        [$testW, $resultW, $unitW, $rangeW] = $this->computeColumnWidths($pdf, $colWidthBase, $isDivided);

        $this->drawTableHeader($pdf, $testW, $resultW, $unitW, $rangeW);

        // Rows
        $rowIndex = 0;
        $children = $mainTestRequest->results;

        $childrenCountWithoutEmpty = $children->filter(fn($r) => $r->result !== '')->count();
        $half = (int)ceil($childrenCountWithoutEmpty / 2);
        $written = 0;

        foreach ($children as $result) {
            if ($result->result === '') {
                continue;
            }

            $child = $result->childTest;
            if ($child === null) {
                continue;
            }

            $written++;
            // Move to second column for divided tables
            if ($isDivided && $written === ($half + 1)) {
                $pdf->selectColumn(1);
                $this->drawTableHeader($pdf, $testW, $resultW, $unitW, $rangeW);
            }

            $this->drawResultRow($pdf, $rowIndex, $child->child_test_name, (string)$result->result, (string)($child?->unit?->name ?: ''), (string)$result->normal_range, $testW, $resultW, $unitW, $rangeW);
            $rowIndex++;
        }

        // Comments
        if (is_string($mainTestRequest->comment) && trim($mainTestRequest->comment) !== '') {
            $pdf->resetColumns();
            $this->ensureSpaceFor($pdf, 16);
            $pdf->Ln($this->smallSpacing);
            $pdf->SetFont('arial', 'b', 11, '', true);
            $pdf->Cell(25, 6, 'Comment', 0, 1, 'L');
            $pdf->SetFont('arial', '', 11, '', true);
            $pdf->MultiCell($pageWidth, 6, (string)$mainTestRequest->comment, 0, 'L');
        }

        // Organisms (if any)
        if ($mainTestRequest->requestedOrganisms()->count() > 0) {
            $pdf->resetColumns();
            $pdf->Ln($this->smallSpacing);
            $this->renderOrganismsMatrix($pdf, $mainTestRequest, $pageWidth);
        }
    }

    private function drawTableHeader(Pdf $pdf, float $testW, float $resultW, float $unitW, float $rangeW): void
    {
        $this->ensureSpaceFor($pdf, 10 + $this->smallSpacing);
        $pdf->SetFont('arial', 'b', 10, '', true);
        $pdf->SetFillColor(...$this->colorHeaderFill);
        $pdf->SetDrawColor(...$this->colorBorder);
        $pdf->Cell($testW, 8, 'Test', 1, 0, 'C', 1);
        $pdf->Cell($resultW, 8, 'Result', 1, 0, 'C', 1);
        $pdf->Cell($unitW, 8, 'Unit', 1, 0, 'C', 1);
        $pdf->Cell($rangeW, 8, 'Reference', 1, 1, 'C', 1);
        $pdf->Ln(1.5);
    }

    private function drawResultRow(
        Pdf $pdf,
        int $rowIndex,
        string $testName,
        string $result,
        string $unit,
        string $reference,
        float $testW,
        float $resultW,
        float $unitW,
        float $rangeW
    ): void {
        // Pre-measure heights to avoid splits
        $rowHeight = max(
            $this->measureTextHeight($pdf, $testW, $testName, $this->baseLineHeight),
            $this->measureTextHeight($pdf, $resultW, $result, $this->baseLineHeight),
            $this->measureTextHeight($pdf, $unitW, $unit, $this->baseLineHeight),
            $this->measureTextHeight($pdf, $rangeW, $reference, $this->baseLineHeight)
        );

        $this->ensureSpaceFor($pdf, $rowHeight + 2);

        // Stripe background
        if ($rowIndex % 2 === 1) {
            $pdf->SetFillColor(...$this->colorStripe);
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $pdf->SetDrawColor(...$this->colorBorder);
        $pdf->SetFont('arial', '', 10, '', true);

        // Abnormal badge if detectable
        $flag = $this->classifyResult($result, $reference);
        $resultDisplay = $result;
        if ($flag === 'H') {
            $resultDisplay .= '  ↑';
        } elseif ($flag === 'L') {
            $resultDisplay .= '  ↓';
        }

        $pdf->MultiCell($testW, $rowHeight, $testName, 1, 'L', 1, 0, null, null, true);

        if ($flag === 'H' || $flag === 'L') {
            $pdf->SetTextColor(...$this->colorDanger);
            $pdf->SetFont('arial', 'b', 10, '', true);
        }
        $pdf->MultiCell($resultW, $rowHeight, $resultDisplay, 1, 'C', 1, 0, null, null, true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('arial', '', 10, '', true);

        $pdf->MultiCell($unitW, $rowHeight, $unit, 1, 'C', 1, 0, null, null, true);
        $pdf->MultiCell($rangeW, $rowHeight, $reference, 1, 'C', 1, 1, null, null, true);
    }

    private function renderOrganismsMatrix(Pdf $pdf, $mainTestRequest, float $pageWidth): void
    {
        $organisms = $mainTestRequest->requestedOrganisms;
        if ($organisms->isEmpty()) {
            return;
        }

        $cols = min(2, max(1, $organisms->count()));
        $colWidth = ($pageWidth - 4) / $cols;

        $pdf->SetFont('arial', 'b', 12, '', true);
        $pdf->Cell($pageWidth, 6, 'Isolated Organisms', 0, 1, 'L');

        $pdf->SetFont('arial', '', 10, '', true);
        $i = 0;
        foreach ($organisms as $org) {
            $this->ensureSpaceFor($pdf, 28);
            $x = PDF_MARGIN_LEFT + ($i % $cols) * $colWidth;
            $y = $pdf->GetY();

            $pdf->SetXY($x, $y);
            $pdf->SetDrawColor(...$this->colorBorder);
            $pdf->SetFillColor(255, 255, 255);
            $pdf->RoundedRect($x, $y, $colWidth - 2, 24, 2.0, '1111', 'D');

            $pdf->SetXY($x + 2, $y + 1.5);
            $pdf->SetFont('arial', 'b', 11, '', true);
            $pdf->Cell($colWidth - 6, 6, (string)$org->organism, 0, 1, 'L');

            $pdf->SetXY($x + 2, $y + 7.5);
            $pdf->SetFont('arial', 'b', 10, '', true);
            $pdf->Cell(($colWidth - 6) / 2, 6, 'Sensitive', 0, 0, 'L');
            $pdf->Cell(($colWidth - 6) / 2, 6, 'Resistant', 0, 1, 'L');

            $pdf->SetFont('arial', '', 10, '', true);
            $pdf->SetXY($x + 2, $y + 13);
            $pdf->MultiCell(($colWidth - 6) / 2, 10, (string)$org->sensitive, 0, 'L');
            $pdf->SetXY($x + 2 + ($colWidth - 6) / 2, $y + 13);
            $pdf->MultiCell(($colWidth - 6) / 2, 10, (string)$org->resistant, 0, 'L');

            if (($i % $cols) === ($cols - 1)) {
                $pdf->Ln(26);
            }
            $i++;
        }

        $pdf->Ln($this->smallSpacing);
    }

    // ----- Utilities -----

    private function getBottomPrintableY(Pdf $pdf): float
    {
        return $pdf->getPageHeight() - $pdf->getBreakMargin();
    }

    private function getRemainingSpace(Pdf $pdf): float
    {
        return $this->getBottomPrintableY($pdf) - $pdf->GetY();
    }

    private function ensureSpaceFor(Pdf $pdf, float $requiredHeight): void
    {
        if ($this->getRemainingSpace($pdf) <= $requiredHeight) {
            $pdf->AddPage();
        }
    }

    private function measureTextHeight(Pdf $pdf, float $width, string $text, float $lineHeight): float
    {
        $lines = max(1, (int)$pdf->getNumLines($text, $width));
        return $lines * $lineHeight;
    }

    private function computeColumnWidths(Pdf $pdf, float $base, bool $isDivided): array
    {
        $testW = $base;
        $resultW = $base * 1.6;
        $unitW = $base * 0.6;
        $rangeW = $base * 1.2;
        return [$testW, $resultW, $unitW, $rangeW];
    }

    /**
     * Classify a numeric result versus a simple numeric range in the form
     * "a - b", "<= b", or ">= a". Returns 'H', 'L', or 'N'.
     */
    private function classifyResult(string $result, string $reference): string
    {
        $value = $this->toFloat($result);
        if ($value === null) {
            return 'N';
        }

        $reference = trim($reference);

        // Pattern: a - b
        if (preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*[-–]\s*(-?\d+(?:\.\d+)?)\s*$/', $reference, $m)) {
            $min = (float)$m[1];
            $max = (float)$m[2];
            if ($value < $min) return 'L';
            if ($value > $max) return 'H';
            return 'N';
        }

        // Pattern: <= b
        if (preg_match('/^\s*(?:<=|≤)\s*(-?\d+(?:\.\d+)?)\s*$/u', $reference, $m)) {
            $max = (float)$m[1];
            return $value > $max ? 'H' : 'N';
        }

        // Pattern: >= a
        if (preg_match('/^\s*(?:>=|≥)\s*(-?\d+(?:\.\d+)?)\s*$/u', $reference, $m)) {
            $min = (float)$m[1];
            return $value < $min ? 'L' : 'N';
        }

        return 'N';
    }

    private function toFloat(string $text): ?float
    {
        if (!preg_match('/-?\d+(?:\.\d+)?/', $text, $m)) {
            return null;
        }
        return (float)$m[0];
    }
}


