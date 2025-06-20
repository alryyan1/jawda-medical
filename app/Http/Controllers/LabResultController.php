<?php

namespace App\Http\Controllers;

use App\Models\DoctorVisit;
use App\Models\LabRequest;
use App\Models\Setting;
use App\Services\Pdf\MyCustomTCPDF;
use Illuminate\Http\Request;

class LabResultController extends Controller
{
     /**
     * Generate a full lab result report for a visit.
     */
    public function generateLabVisitReportPdf(Request $request, DoctorVisit $visit)
    {
        // Permission Check (Example)
        // if (!Auth::user()->can('print lab_report', $visit)) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Eager load all necessary data using the scope or direct with()
        $visit->loadDefaultLabReportRelations(); // Ensure this scope is defined on DoctorVisit Model

        $labRequestsToReport = $visit->labRequests->filter(function ($lr) {
            // Ensure mainTest relation is loaded for every labRequest
            if (!$lr->mainTest) return false;
            
            return $lr->results->where(fn($r) => $r->result !== null && $r->result !== '')->isNotEmpty() || // Has entered results
                   !$lr->mainTest->divided || // Is not a divided test (might have comment as result)
                   $lr->requestedOrganisms->isNotEmpty(); // Has organisms
        });

        if ($labRequestsToReport->isEmpty()) {
            return response()->json(['message' => 'No results or relevant tests to report for this visit.'], 404);
        }

        $appSettings = Setting::instance();

        $pdf = new MyCustomTCPDF(
            'Lab Result Report',
            'Visit ID: ' . $visit->id . ' | Patient: ' . ($visit->patient->name ?? 'N/A'), // Pass the DoctorVisit instance for context in Header/Footer
            'P', 'mm', 'A4'
        );
        
        // Ensure the default font set in MyCustomTCPDF is your primary Arabic font
        // For example, if it's 'aealarabiya' or 'arial' (if configured for Arabic)
        // $pdf->SetFont($pdf->getDefaultFontFamily(), '', 10); // Set a base font for content

        $pdf->AddPage(); // This will trigger the custom Header() method in MyCustomTCPDF

        $firstTestOnPage = true;
        // Determine font names to use (ensure they are loaded/available to TCPDF)
        $fontMain = $pdf->getDefaultFontFamily(); // Your primary (Arabic) font
        $fontEnglish = 'helvetica'; // A standard fallback for English parts if needed, or your 'arial'/'roboto'

        foreach ($labRequestsToReport as $labRequest) {
            $mainTest = $labRequest->mainTest;
            if (!$mainTest) continue;

            $estimatedHeight = $this->estimateMainTestBlockHeight($pdf, $labRequest);
            if (!$firstTestOnPage && ($mainTest->pageBreak || ($pdf->GetY() + $estimatedHeight > ($pdf->getPageHeight() - $pdf->getBreakMargin())))) {
                $pdf->AddPage();
            } elseif (!$firstTestOnPage) {
                $pdf->Ln(3);
            }
            
            $this->drawSingleMainTestBlock($pdf, $labRequest, $appSettings, $fontMain, $fontEnglish);
            $firstTestOnPage = false;
        }
        
        $patientNameSanitized = preg_replace('/[^A-Za-z0-9\-\_\ء-ي]/u', '_', $visit->patient->name);
        $pdfFileName = 'LabReport_Visit_' . $visit->id . '_' . $patientNameSanitized . '.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }

    protected function estimateMainTestBlockHeight(MyCustomTCPDF $pdf, LabRequest $labRequest): float
    {
        $height = 10; // Main test name
        if ($labRequest->results->isNotEmpty() && $labRequest->mainTest && $labRequest->mainTest->divided) {
            $height += 6; // Child test table headers
            foreach($labRequest->results as $result) {
                $height += 5; // Min height per result row
                if(!empty($result->result_comment)) $height += 3; // Approx for comment
            }
        }
        if ($labRequest->requestedOrganisms->isNotEmpty()) {
            $height += 7; // Header for organisms
            foreach ($labRequest->requestedOrganisms as $org) {
                $height += 5; // Organism name
                $height += 5; // Sensitive/Resistant headers
                $maxRows = max(substr_count($org->sensitive ?? '', "\n") + 1, substr_count($org->resistant ?? '', "\n") + 1);
                $height += $maxRows * 3.5;
            }
        }
        if ($labRequest->comment) $height += 10;
        return $height;
    }

    protected function drawSingleMainTestBlock(MyCustomTCPDF $pdf, LabRequest $labRequest, ?Setting $settings, string $fontMain, string $fontEnglish)
    {
        $isRTL = $pdf->getRTL();
        $lineHeight = 5;
        $pageUsableWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $mainTest = $labRequest->mainTest;

        // Main Test Name Header
        $pdf->SetFont($fontMain, 'BU', 12); // Slightly smaller than old code's 17, but still prominent
        $pdf->Cell(0, $lineHeight + 2, $mainTest->main_test_name, 0, 1, $isRTL ? 'R' : 'L');
        $pdf->Ln(1);

        // Child Results Table (if main test is divided and has results)
        if ($mainTest->divided && $labRequest->results->isNotEmpty()) {
            $this->drawChildResultsTable($pdf, $labRequest->results, $fontMain, $fontEnglish, $pageUsableWidth, $lineHeight, $isRTL);
        } 
        // If not divided, result might be in main comment or a dedicated field (not shown here)
        // Or if it's not divided but has results (e.g. a single parameter test where results are still in requested_results)
        elseif (!$mainTest->divided && $labRequest->results->isNotEmpty()) {
             $this->drawChildResultsTable($pdf, $labRequest->results, $fontMain, $fontEnglish, $pageUsableWidth, $lineHeight, $isRTL, false); // Pass false for no headers
        }
        elseif ($labRequest->results->isEmpty() && $mainTest->divided) {
            $pdf->SetFont($fontMain, 'I', 8);
            $pdf->Cell(0, $lineHeight, ($isRTL ? 'لم يتم إدخال نتائج فرعية بعد.' : 'No sub-results entered yet.'), 0, 1, 'C');
        }
        $pdf->Ln(0.5);

        // Organisms Section
        if ($labRequest->requestedOrganisms->isNotEmpty()) {
            $this->drawOrganismsSection($pdf, $labRequest->requestedOrganisms, $fontMain, $fontEnglish, $pageUsableWidth, $lineHeight, $isRTL);
        }

        // Main Test Overall Comment
        if ($labRequest->comment) {
            $pdf->Ln(1);
            $pdf->SetFont($fontMain, 'BI', 9);
            $pdf->Cell(0, $lineHeight, ($isRTL ? "ملاحظة عامة على الفحص:" : "Main Test Comment:"), 0, 1, $isRTL ? 'R' : 'L');
            $pdf->SetFont($fontMain, 'I', 8.5);
            $pdf->MultiCell(0, $lineHeight - 1, " " . $labRequest->comment, 0, $isRTL ? 'R' : 'L', false, 1);
        }

        // Watermark
        if ($settings?->show_water_mark && $labRequest->approve) { // Check LabRequest 'approve' flag
            $pdf->drawTextWatermark(($isRTL ? "معتمد" : "AUTHORIZED"), $fontMain); // Use helper from MyCustomTCPDF
        }
        $pdf->Ln(1);
    }
    
    protected function drawChildResultsTable($pdf, $results, $fontMain, $fontEnglish, $pageWidth, $baseLineHeight, $isRTL, $drawHeaders = true) {
        $colWidths = [$pageWidth*0.30, $pageWidth*0.20, $pageWidth*0.15, $pageWidth*0.25, $pageWidth*0.10]; // Test, Result, Unit, Range, Flags
        $colAligns = [$isRTL ? 'R' : 'L', 'C', 'C', 'C', 'C']; // Adjusted normal range align to C like your old code
        
        if ($drawHeaders) {
            $pdf->SetFont($fontMain, 'B', 7.5); // Your old code used 11 for headers, then 12, this is smaller
            $pdf->SetFillColor(240, 240, 240);
            $pdf->Cell($colWidths[0], $baseLineHeight, ($isRTL ? 'الفحص' : "Test"), 'TB', 0, 'C', true);
            $pdf->Cell($colWidths[1], $baseLineHeight, ($isRTL ? 'النتيجة' : "Result"), 'TB', 0, 'C', true);
            $pdf->Cell($colWidths[2], $baseLineHeight, ($isRTL ? 'الوحدة' : "Unit"), 'TB', 0, 'C', true);
            $pdf->Cell($colWidths[3], $baseLineHeight, ($isRTL ? 'المعدل الطبيعي' : "R.Values"), 'TB', 0, 'C', true);
            $pdf->Cell($colWidths[4], $baseLineHeight, ($isRTL ? 'علامات' : "Flags"), 'TB', 1, 'C', true); // ln=1
            $pdf->Ln(0.5);
        }
        $pdf->SetFont($fontMain, '', 8.5); // Your old code used 12 for results
        $fill = false;

        foreach ($results as $result) {
            $childTest = $result->childTest;
            if(!$childTest) continue;

            $isAbnormal = false;
            $numericResult = filter_var($result->result, FILTER_VALIDATE_FLOAT);
            if ($numericResult !== false && $childTest->low !== null && $childTest->upper !== null) {
                if ($numericResult < $childTest->low || $numericResult > $childTest->upper) $isAbnormal = true;
            } elseif (!empty($result->flags) && in_array(strtoupper($result->flags), ['H', 'L', 'A', 'ABN', 'ABNORMAL'])) { // Added ABN
                $isAbnormal = true;
            }

            $texts = [
                $childTest->child_test_name,
                $result->result ?? '-',
                $result->unit?->name ?? $childTest->unit?->name ?? '-',
                $result->normal_range ?? '-', // Snapshot from RequestedResult
                $result->flags ?? '-'
            ];
             $maxLines = 0;
             for($i=0; $i<count($texts); $i++) $maxLines = max($maxLines, $pdf->getNumLines($texts[$i], $colWidths[$i]));
             if ($maxLines == 0) $maxLines = 1;
             $rowH = ($baseLineHeight - 1) * $maxLines + (($maxLines > 1) ? ($maxLines-1)*0.5 : 0) ;
             if($rowH < ($baseLineHeight-0.5)) $rowH = $baseLineHeight-0.5;

             if ($pdf->GetY() + $rowH > ($pdf->getPageHeight() - $pdf->getBreakMargin())) {
                $pdf->AddPage(); 
                if ($drawHeaders) { /* Redraw headers logic */
                    $pdf->SetFont($fontMain, 'B', 7.5); $pdf->SetFillColor(240,240,240);
                    $pdf->Cell($colWidths[0], $baseLineHeight, ($isRTL ? 'الفحص' : "Test"), 'TB', 0, 'C', true);
                    $pdf->Cell($colWidths[1], $baseLineHeight, ($isRTL ? 'النتيجة' : "Result"), 'TB', 0, 'C', true);
                    $pdf->Cell($colWidths[2], $baseLineHeight, ($isRTL ? 'الوحدة' : "Unit"), 'TB', 0, 'C', true);
                    $pdf->Cell($colWidths[3], $baseLineHeight, ($isRTL ? 'المعدل الطبيعي' : "R.Values"), 'TB', 0, 'C', true);
                    $pdf->Cell($colWidths[4], $baseLineHeight, ($isRTL ? 'علامات' : "Flags"), 'TB', 1, 'C', true);
                    $pdf->Ln(0.5);
                }
                $pdf->SetFont($fontMain, '', 8.5);
             }
            
            $curY = $pdf->GetY(); $curX = $pdf->GetX();
            $pdf->SetFillColor($fill ? 248 : 255, $fill ? 248 : 255, $fill ? 248 : 255);
            
            $pdf->MultiCell($colWidths[0], $rowH, $texts[0], 0, $colAligns[0], $fill, 0, $curX, $curY, true,0,false,true,$rowH,'M'); $curX += $colWidths[0];
            $pdf->SetFont($fontMain, $isAbnormal ? 'B' : '', 8.5);
            $pdf->MultiCell($colWidths[1], $rowH, $texts[1], 0, $colAligns[1], $fill, 0, $curX, $curY, true,0,false,true,$rowH,'M'); $curX += $colWidths[1];
            $pdf->SetFont($fontMain, '', 8.5);
            $pdf->MultiCell($colWidths[2], $rowH, $texts[2], 0, $colAligns[2], $fill, 0, $curX, $curY, true,0,false,true,$rowH,'M'); $curX += $colWidths[2];
            $pdf->MultiCell($colWidths[3], $rowH, $texts[3], 0, $colAligns[3], $fill, 0, $curX, $curY, true,0,false,true,$rowH,'M'); $curX += $colWidths[3];
            $pdf->MultiCell($colWidths[4], $rowH, $texts[4], 0, $colAligns[4], $fill, 1, $curX, $curY, true,0,false,true,$rowH,'M'); // ln=1
            
            $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY()); // Line after each result row
            $fill = !$fill;
            
            if(!empty($result->result_comment)){ 
                $pdf->SetFont($fontMain, 'I', 7.5);
                $pdf->MultiCell(0, $baseLineHeight -1.5, ($isRTL ? "تعليق: " : "Comment: ") . $result->result_comment, 'LRB', $isRTL ? 'R' : 'L', $fill, 1); // Give it a border
                $pdf->SetFont($fontMain, '', 8.5);
                $fill = !$fill; // Alternate fill for comment too
            }
        }
    }

    protected function drawOrganismsSection(MyCustomTCPDF $pdf, $organisms, $fontMain, $fontEnglish, $pageWidth, $baseLineHeight, $isRTL) {
        if ($organisms->isEmpty()) return;
        $pdf->Ln(2);
        $pdf->SetFont($fontMain, 'B', 10);
        $pdf->Cell(0, $baseLineHeight, ($isRTL ? "الكائنات المعزولة و الحساسية:" : "Culture & Sensitivity:"), 0, 1, $isRTL ? 'R' : 'L');
        $pdf->Ln(0.5);
        
        // Use a simple table for each organism rather than complex TCPDF columns
        foreach ($organisms as $org) {
            if ($pdf->GetY() + 40 > ($pdf->getPageHeight() - $pdf->getBreakMargin())) $pdf->AddPage(); // Check space

            $pdf->SetFont($fontMain, 'B', 9);
            $pdf->SetFillColor(230,230,230);
            $pdf->Cell($pageWidth, $baseLineHeight, $org->organism, 1, 1, 'C', true); // Organism Name as header
            
            $pdf->SetFont($fontMain, 'B', 8);
            $halfWidth = $pageWidth / 2;
            $pdf->Cell($halfWidth, $baseLineHeight-1, ($isRTL ? 'حساس لـ:' : 'Sensitive To:'), 'LTB', 0, 'C');
            $pdf->Cell($halfWidth, $baseLineHeight-1, ($isRTL ? 'مقاوم لـ:' : 'Resistant To:'), 'TRB', 1, 'C');

            $pdf->SetFont($fontMain, '', 8);
            $sensArr = !empty($org->sensitive) ? array_map('trim', preg_split('/[\n,]+/', $org->sensitive)) : [];
            $resArr = !empty($org->resistant) ? array_map('trim', preg_split('/[\n,]+/', $org->resistant)) : [];
            $maxRows = max(count($sensArr), count($resArr));
            if ($maxRows == 0) $maxRows = 1; // Ensure at least one row for borders

            for ($i = 0; $i < $maxRows; $i++) {
                $s_text = $sensArr[$i] ?? '';
                $r_text = $resArr[$i] ?? '';
                $s_lines = $pdf->getNumLines($s_text, $halfWidth - 2); // -2 for padding
                $r_lines = $pdf->getNumLines($r_text, $halfWidth - 2);
                $rowDynamicHeight = max($s_lines, $r_lines) * ($baseLineHeight - 1.5);
                if ($rowDynamicHeight < ($baseLineHeight-1)) $rowDynamicHeight = $baseLineHeight-1;

                $currentY = $pdf->GetY();
                 if ($currentY + $rowDynamicHeight > ($pdf->getPageHeight() - $pdf->getBreakMargin())) {
                     $pdf->AddPage();
                     $pdf->SetFont($fontMain, 'B', 8);
                     $pdf->Cell($halfWidth, $baseLineHeight-1, ($isRTL ? 'حساس لـ:' : 'Sensitive To:'), 'LTB', 0, 'C');
                     $pdf->Cell($halfWidth, $baseLineHeight-1, ($isRTL ? 'مقاوم لـ:' : 'Resistant To:'), 'TRB', 1, 'C');
                     $pdf->SetFont($fontMain, '', 8);
                     $currentY = $pdf->GetY();
                 }

                $pdf->MultiCell($halfWidth, $rowDynamicHeight, $s_text, 'L'.($i == $maxRows-1 ? 'B' : ''), $isRTL ? 'R' : 'L', false, 0, '', $currentY, true, 0, false, true, $rowDynamicHeight, 'T');
                $pdf->MultiCell($halfWidth, $rowDynamicHeight, $r_text, 'R'.($i == $maxRows-1 ? 'B' : ''), $isRTL ? 'R' : 'L', false, 1, $pdf->GetX(), $currentY, true, 0, false, true, $rowDynamicHeight, 'T');
            }
            $pdf->Ln(2);
        }
    }
}
