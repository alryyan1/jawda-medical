<?php

namespace App\Services\Pdf;

use App\Models\DoctorVisit;
use App\Models\Package;
use App\Models\Setting;
use App\Mypdf\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LabResultReport
{
    // Sizing and spacing constants for consistent UI/UX
    private float $baseLineHeight = 5.0;         // Default line height used for table rows
    private float $sectionSpacing = 8.0;         // Spacing between major sections
    private float $headerSpacing = 6.0;          // Spacing after headers
    private float $smallSpacing = 3.0;           // Minor spacing between related elements
    private array $themeHeaderFill = [245, 247, 250]; // light header fill
    private array $themeBorderColor = [200, 205, 210];

    /**
     * Generate lab result PDF content for a doctor visit.
     *
     * @param DoctorVisit $doctorvisit
     * @param bool $base64
     * @return string PDF content
     */
    public function generate(DoctorVisit $doctorvisit, bool $base64 = false, bool $isWhatsappContext = false): string
    {
        ob_start();

        $patient = $doctorvisit->patient;
        if ($patient?->result_print_date == null) {
            $patient->update(['result_print_date' => now()]);
        }

        // Optimize PDF engine for images and performance
        $pdf = new Pdf('portrait', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Global PDF performance/image settings
        if (function_exists('ini_set')) {
            @ini_set('memory_limit', '512M');
        }
        $pdf->setCompression(true);           // compress content streams
        $pdf->setImageScale(1.25);            // reasonable image DPI scaling
        $pdf->setJPEGQuality(80);             // balance quality/performance

        $pdf->setCreator(PDF_CREATOR);
        $pdf->setAuthor('alryyan mahjoob');
        $pdf->setTitle('النتيحه');
        $pdf->setSubject('patient lab result');
        $pdf->setMargins(PDF_MARGIN_LEFT, 85, PDF_MARGIN_RIGHT);

        $pdf->setHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->setFooterMargin(0);
        $pdf->setAutoPageBreak(TRUE, 40);
        $page_width = $pdf->getPageWidth() - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT;
        
        /** @var Setting $img_base64_encoded */
        $settings = Setting::all()->first();
        $logo_name = $settings?->header_base64;
        $footer_name = $settings?->footer_base64;
        $logo_path = public_path();

        // Set up header function
        $pdf->head = function ($pdf) use ($patient, $page_width, $settings, $doctorvisit) {
            $this->renderHeader($pdf, $patient, $page_width, $settings, $doctorvisit);
        };

        // Set up footer function
        $pdf->foot = function ($pdf) use ($patient, $page_width, $settings, $footer_name, $logo_path) {
            $this->renderFooter($pdf, $patient, $page_width, $settings, $footer_name, $logo_path);
        };

        $pdf->AddPage();
        
        // Add watermark: if the visit has semen analysis, use public/sement-bg.jpeg; otherwise use settings watermark if available
        $hasSemenAnalysis = $doctorvisit->patientLabRequests
            ->contains(function ($lr) {
                return strtolower($lr->mainTest?->main_test_name ?? '') === 'semen_analysis';
            });

        $watermarkPath = null;
        if ($hasSemenAnalysis) {
            $candidate = public_path('sement-bg.jpeg');
            if (file_exists($candidate)) {
                $watermarkPath = $candidate;
            }
        }
        if ($watermarkPath === null && !empty($settings?->watermark_image)) {
            $candidate = $logo_path . '/' . $settings->watermark_image;
            if (file_exists($candidate)) {
                $watermarkPath = $candidate;
            }
        }

        if ($watermarkPath) {
            $pdf->SetAlpha(0.85); // Slightly lighter watermark
            // Fit watermark reasonably in center area
            $pdf->Image(
                $watermarkPath,
                15, 90, 180, 160, '', '', '', true, 150, '', false, false, 0, false, false, false
            );
            $pdf->SetAlpha(1);
        }

        // Add logo
        $this->addLogo($pdf, $settings, $logo_name, $logo_path, $page_width, $base64, $isWhatsappContext);

        if(!$isWhatsappContext){
            $patient->update(['result_print_date' => now()]);
        }
        
        // Render lab results
        $this->renderLabResults($pdf, $patient, $settings, $page_width);

        $pdfContent = $pdf->Output('name.pdf', 'S');

        // Clean any remaining output buffer
        ob_end_clean();

        return $pdfContent;
    }

    /**
     * Render the PDF header
     */
    private function renderHeader($pdf, $patient, $page_width, $settings, $doctorvisit): void
    {
        $pdf->SetFont('arial', '', 18, '', true);

        Log::info('$settings->lab_name',['settigns'=>$settings]);

        if ($settings?->show_title_in_lab_result) {
        $pdf->Ln(20);
        }else{
            $pdf->Ln(25);
        }
        if ($settings?->show_title_in_lab_result) {
            $pdf->Cell($page_width, 5, $settings?->lab_name ?? 'Lab Name' , 0, 1, 'C');
        }

        $pdf->Ln(20);

        $y = $pdf->GetY();
        $pdf->SetFont('arial', '', 13, '', true);
        $table_col_widht = ($page_width) / 4;
        $pdf->cell($table_col_widht / 2, 5, "Date", 0, 0, 'C');
        $pdf->cell($table_col_widht, 5, $patient->created_at->format('Y-m-d'), 0, 0, 'C');
        $pdf->SetFont('arial', '', 18, '', true);
        $pdf->cell($table_col_widht * 2 + 10, 5, $patient->name, 0, 0, 'R', stretch: 1); //patient name
        $pdf->cell($table_col_widht / 2 - 10, 5, "الاسم/ ", 0, 1, 'R'); //

        $pdf->cell($table_col_widht / 2, 5, "SN", 0, 0, 'C');
        $pdf->cell($table_col_widht, 5, $doctorvisit->id, 0, 0, 'C'); //age
        $pdf->SetFont('arial', '', 15, '', true);

        $pdf->cell($table_col_widht * 2 + 10, 5, $patient?->doctor?->name, 0, 0, 'R'); // doctor name
        $pdf->cell($table_col_widht / 2 - 10, 5, "د/ ", 0, 1, 'C');
        
        $requestedTests = $doctorvisit->patientLabRequests
            ->map(fn($lr) => $lr->mainTest?->main_test_name)
            ->filter()->unique()->implode(' | ');
            
        $this->drawHeaderLines($pdf, $requestedTests);
    }

    /**
     * Draw header lines and requested tests section
     */
    private function drawHeaderLines($pdf, $requestedTests): void
    {
        $pdf->Line(6, 5, 205, 5); //TOP LINE [H]
        $pdf->Line(6, 70, 205, 70); //SECOND [H]
        $pdf->Line(6, 80, 205, 80); //SECOND [H]
        $pdf->RoundedRect(6, 50, 199, 18, 6.50, '0101');

        $pdf->Line(6, 70, 6, 280); //line between 2 points
        $pdf->Line(205, 70, 205, 280); //line between 2 points
        $pdf->SetFont('arial', '', 9, '', true);
        $pdf->Ln();
        $pdf->cell(25, 5, "Requested: ", 0, 0, 'L');
        $pdf->MultiCell(170, 5, "$requestedTests", 0, 'L', 0, 1, '', '', true);
        $pdf->SetFont('arial', '', 15, '', true);
    }

    /**
     * Render the PDF footer
     */
    private function renderFooter($pdf, $patient, $page_width, $settings, $footer_name, $logo_path): void
    {
        $pdf->SetFont('arial', '', 9, '', true);
        // $pdf->fontsubsetting(true);
        $col = $page_width / 6;
        $user = auth()->user();
        $pdf->cell(20, 5, "Sign: ", 0, 1, 'L');
        $pdf->cell($col, 5, $patient->resultAuthUser->name ?? 'System', 0, 0, 'L');
        $pdf->cell($col, 5, " ", 0, 0, 'L');
        $pdf->cell($col, 5, " ", 0, 0, 'L');
        $pdf->cell($col, 5, " ", 0, 0, 'L');
        $pdf->cell($col, 5, "No ", 0, 0, 'R');
        $pdf->cell($col, 5, $patient->visit_number, 0, 1, 'C');

        if ($settings?->footer_content != null) {
            $pdf->SetFont('arial', '', 10, '', true);
            $pdf->MultiCell($page_width - 25, 5, $settings->footer_content, 0, 'C', 0, 1, '', '', true);
        }
        
        $y = $pdf->getY();
        if ($settings?->is_footer) {
            $pdf->Image($logo_path . '/' . $footer_name, 10, $y + 10, $page_width + 10, 10);
        }
    }

    /**
     * Add logo to the PDF
     */
    private function addLogo($pdf, $settings, $logo_name, $logo_path, $page_width, $base64, bool $isWhatsappContext = false): void
    {
        // Determine logo visibility using new settings
        $shouldShowLogo = false;
        if ($settings?->show_logo_only_whatsapp) {
            $shouldShowLogo = (bool)$isWhatsappContext; // Only show when sending via WhatsApp
        } elseif ($settings?->show_logo !== null) {
            $shouldShowLogo = (bool)$settings->show_logo; // Global on/off
        } else {
            // Backward compatibility: fall back to old is_logo flags
            $shouldShowLogo = (bool)$settings?->is_logo;
        }

        if ($shouldShowLogo) {
            // if ($settings->is_logo) {
            //     $pdf->Image(
            //         $logo_path . '/' . $logo_name,
            //         5, 5, 40, 40, '', '', '', true, 150, '', false, false, 0, false, false, false
            //     );
            // }else{
                // if (!$settings?->show_logo_only_whatsapp) {
                    $pdf->Image(
                        $logo_path . '/' . $logo_name,
                        10, 10, $page_width + 10, 30, '', '', '', true, 150, '', false, false, 0, false, false, false
                    );
                // }
            // }
        } else {
            //is_header الترويصه
            // if ($settings?->is_header == '1') {
            //     if (!$settings?->show_logo_only_whatsapp) {
            //     $pdf->Image(
            //         $logo_path . '/' . $logo_name,
            //         10, 10, $page_width + 10, 30, '', '', '', true, 150, '', false, false, 0, false, false, false
            //     );
            // }
            // }
        }
    
    }

    /**
     * Render lab results for all packages
     */
    private function renderLabResults($pdf, $patient, $settings, $page_width): void
    {
        // dd($patient->labrequests);
        $pdf->SetFillColor(240, 240, 240);
        $page_height = $pdf->getPageHeight() - PDF_MARGIN_TOP;
        $pdf->SetFont('aealarabiya', '', 10, '', true);
        $mypakages = Package::all();

        $pdf->SetFont('arial', '', 10, '', true);
        
        foreach ($mypakages as $package) {
            // dd($package);
            $this->renderPackageResults($pdf, $patient, $package, $page_width, $page_height);
        }
    }

    /**
     * Render results for a specific package
     */
    private function renderPackageResults($pdf, $patient, $package, $page_width, $page_height): void
    {
        $show_headers = true;
        // dd($patient->labrequests);
        $main_test_array = $patient->labrequests->filter(function ($item) use ($package) {
            return $item->mainTest->pack_id == $package->package_id;
        });
        $count = 0;

        foreach ($main_test_array as $m_test) {
            $count++;
            // dd($m_test);
            if ($m_test->hidden == 1) continue;
            
            $children_count = count($m_test->results);
            $children_count_empty = $m_test->results->filter(function ($curr) {
                return $curr->result == '' || $curr->result == 'no sample';
            }, 0)->count();

            //empty test
            if ($m_test->requestedOrganisms()->count() == 0) {
                if ($children_count_empty == $children_count) continue;
            }

            $children_count -= $children_count_empty;
            $number_of_lines_in_normal_range = 0;
            $total_lines = 0;
            $is_columns = false;

            if ($m_test->mainTest->divided == 1) {
                $pdf->setEqualColumns(2);
                $pdf->selectColumn(col: 0);
                $is_columns = true;
            }

            foreach ($m_test->results as $requested_result) {
                $nr = $requested_result->normal_range;
                $number_of_lines_in_normal_range = substr_count($nr, "\n");
                $lines_in_result = substr_count($requested_result->result, "\n");
                $total_lines += max($lines_in_result, $number_of_lines_in_normal_range);
            }
            
            $number_of_lines_in_normal_range = $number_of_lines_in_normal_range / 2;
            $number_of_lines_in_normal_range = $total_lines * 5;
            $add = 0;
            if ($count >= 1) {
                $add = 20;
            }
            $requared_height = ($children_count * $this->baseLineHeight) + $number_of_lines_in_normal_range + $add;
            // Ensure enough space before starting the block of this test
            $this->ensureSpaceFor($pdf, $requared_height + $this->sectionSpacing);
            
            $is_columns = false;
            if ($m_test->requestedOrganisms()->count() > 0) {
                $is_columns = false;
                $pdf->SetFont('arial', '', 18, '', true);
                $pdf->cell(180, 5, "Isolated organisms :", 0, 1, 'L', 0);
                $pdf->Ln(5);
                $pdf->setEqualColumns($m_test->requestedOrganisms()->count(), $page_width / $m_test->requestedOrganisms()->count() - 5);
                $pdf->selectColumn(0);
                $column_width = $page_width / (($m_test->requestedOrganisms()->count()) * 2);
            }
            
            $col_number = 0;

            if ($m_test->mainTest->divided == 1) {
                $table_col_widht = ($page_width) / 8;
            } else {
                $table_col_widht = ($page_width) / 4;
            }
            
            $has_more_than1_child = false;
            if ($children_count > 1) {
                $has_more_than1_child = true;
            }
            
            if ($has_more_than1_child == false) {
                if ($show_headers) {
                    $hideUnit = $m_test->mainTest->hide_unit ?? false;
                    $this->renderResultTableHeader($pdf, $table_col_widht, $m_test->mainTest->divided == 1, $hideUnit);
                }
                $show_headers = false;
            }
            
            if ($has_more_than1_child) {
                $this->renderMainTestHeader($pdf, $m_test, $table_col_widht, $page_width);
            }
            // dd($m_test);

            $this->renderTestResults($pdf, $m_test, $table_col_widht, $page_width, $is_columns, $column_width ?? 0);
            $pdf->resetColumns();

            $this->renderComments($pdf, $m_test, $page_width);
            $this->addVerticalSpacing($pdf, $this->smallSpacing);
            
            $this->renderOrganisms($pdf, $m_test, $col_number, $column_width ?? 0);
            $this->addVerticalSpacing($pdf, $this->sectionSpacing);
        }
    }

    /**
     * Render main test header for tests with multiple children
     */
    private function renderMainTestHeader($pdf, $m_test, $table_col_widht, $page_width): void
    {
        // Skip table header rendering for special tests like semen analysis
        if ($m_test->mainTest->is_special_test) {
            return;
        }
        
        $pdf->SetFont('arial', 'u', 17, '', true);
        if ($m_test->requestedOrganisms()->count() > 0) {
            // $pdf->Ln(5);
            $pdf->resetColumns();
            // $pdf->Ln(5);
        }
        if ($m_test->mainTest->divided != 1) {
            $pdf->cell(40, 5, $m_test->mainTest->main_test_name, 0, 1, 'L'); // main
        }
        // $pdf->Ln(5);
        // $pdf->Ln();
        $y_position_for_divided_section = $pdf->getY();
        $hideUnit = $m_test->mainTest->hide_unit ?? false;
        $this->renderResultTableHeader($pdf, $table_col_widht, $m_test->mainTest->divided == 1, $hideUnit);
    }

    /**
     * Render individual test results
     */
    private function renderTestResults($pdf, $m_test, $table_col_widht, $page_width, $is_columns, $column_width): void
    {
        // Check if this is a special test (like semen analysis)
        if ($m_test->mainTest->is_special_test) {
            $this->renderSpecialTestResults($pdf, $m_test, $table_col_widht, $page_width);
            return;
        }

        // Original logic for regular tests
        $old = '';
        $index_to_start_new_column = 0;
        $children_count_with_result_empty = 0;
        
        foreach ($m_test->results as $result) {
            if ($result->result == '') {
                $children_count_with_result_empty++;
                continue;
            }
            $index_to_start_new_column++;

            $child_test = $result->childTest;
            if ($child_test == null) continue;

            $half_children_count = (count($m_test->results) - $children_count_with_result_empty) / 2;
            if (ceil($half_children_count) == $index_to_start_new_column) {
                if ($m_test->mainTest->divided == 1) {
                    $pdf->selectColumn(col: 1);
                    $y_position_for_divided_section = $pdf->getY();
                    $hideUnit = $m_test->mainTest->hide_unit ?? false;
                    $this->renderResultTableHeader($pdf, $table_col_widht, true, $hideUnit);
                    $this->addVerticalSpacing($pdf, $this->smallSpacing);
                }
            }
            
            $y = $pdf->GetY();
            if ($result->childTest->childGroup?->name != null) {
                $new = $result->childTest->childGroup?->name;
                if ($old != $new) {
                    $old = $result->childTest->childGroup?->name;
                    $pdf->SetFont('arial', 'u', 14, '', true);
                    $pdf->cell(40, 5, $old, 0, 1);
                    $pdf->SetFont('arial', '', 12, '', true);
                }
            }
            
            $unit = $child_test?->unit?->name;
            $normal_range = $result->normal_range;
            $child_id = $child_test->id;
            
            if ($m_test->mainTest->divided == 1) {
                $table_col_widht = ($page_width) / 8;
            } else {
                $table_col_widht = ($page_width) / 4;
            }
            
            $resultCellHeight = $this->baseLineHeight;

            $pdf->SetFont('arial', '', 9, '', true);
            if ($is_columns) {
                $pdf->cell($column_width, 5, $child_test->child_test_name, 1, 0, 'C'); // test name
            } else {
                $pdf->cell($table_col_widht, 5, $child_test->child_test_name, 0, 0, 'C'); // test name
            }
            $pdf->SetFont('arial', '', 11, '', true);

            $report_result = $result->result;

            // Pre-measure row height using actual column widths to decide on page break
            $hideUnit = $m_test->mainTest->hide_unit ?? false;
            [$testW, $resultColWidth, $unitColWidth, $normalColWidth] = $this->computeColumnWidths($pdf, $table_col_widht, $m_test->mainTest->divided == 1, $hideUnit);
            $estimatedResultHeight = $this->measureTextHeight($pdf, $resultColWidth, (string)$report_result, $this->baseLineHeight);
            $estimatedNormalHeight = $this->measureTextHeight($pdf, $normalColWidth, (string)$normal_range, $this->baseLineHeight);
            $estimatedRowHeight = max($this->baseLineHeight, $estimatedResultHeight, $estimatedNormalHeight);
            $this->ensureSpaceFor($pdf, $estimatedRowHeight + $this->smallSpacing);

            if (($child_id == 46 || $child_id == 70 ) && $report_result != '' && is_numeric($report_result)) {
                $percent = ($report_result / 15) * 100;
                $percent = ceil($percent);
                $resultCellHeight = $pdf->MultiCell($table_col_widht * 1.5, 5, "$report_result         $percent % ", 0, 'C', 0, 0, '', '', true);
            } else {
                $lines_in_result = $pdf->getNumLines($report_result, $resultColWidth);
                if ($lines_in_result > 0) {
                $resultCellHeight = $pdf->MultiCell($resultColWidth, 5, "$report_result", 0, 'C', 0, 0, '', '', true);
                } else {
                $resultCellHeight = $pdf->MultiCell($resultColWidth, 5, "$report_result", 0, 'C', 0, 0, '', '', true);
                }
            }

            if ($m_test->mainTest->divided == 1) {
                $pdf->SetFont('arial', '', 7, '', true);
            }
            
            if (!$hideUnit) {
                $pdf->MultiCell($unitColWidth, 5, "$unit", 0, 'C', 0, 0, '', '', true);
            }
            $pdf->SetFont('arial', '', 7, '', true);

            $normalRangeCellHeight = $pdf->MultiCell($normalColWidth, 5, "$normal_range", 0, 'C', 0, 1, '', '', true);
            $pdf->SetFont('arial', '', 11, '', true);

            $y = $pdf->GetY();
            $x = $pdf->GetX();
            $highestValue = max([$normalRangeCellHeight, $resultCellHeight]);
            $pdf->SetFont('arial', '', 11, '', true);

            if ($resultCellHeight > $normalRangeCellHeight) {
                //caclulate additional height
                $additional_height = $resultCellHeight * 5 - ($normalRangeCellHeight * 5);
            } else {
                if ($m_test->mainTest->divided == 1) {
                    $column = $pdf->getColumn();
                    if ($column == 0) {
                        $pdf->Line(PDF_MARGIN_LEFT, $y, 98, $y); //line between 2 points
                    }
                    $column = $pdf->getColumn();
                    if ($column == 1) {
                        $pdf->Line(105, $y, $page_width + PDF_MARGIN_RIGHT, $y); //line between 2 points
                    }
                } else {
                    $pdf->Line(PDF_MARGIN_LEFT, $y, $page_width + PDF_MARGIN_RIGHT, $y);
                }
            }
        }
    }

    /**
     * Render special test results with grouped layout (e.g., semen analysis)
     */
    private function renderSpecialTestResults($pdf, $m_test, $table_col_widht, $page_width): void
    {
        // Check if this is semen analysis for specific formatting
        $isSemenAnalysis = strtolower($m_test->mainTest->main_test_name) === 'semen_analysis';
        
        if ($isSemenAnalysis) {
            $this->renderSemenAnalysisMultiPage($pdf, $m_test, $page_width);
        } else {
            // Original logic for other special tests
            $this->renderGenericSpecialTest($pdf, $m_test, $page_width);
        }
    }
    
    /**
     * Render semen analysis with multi-page layout
     */
    private function renderSemenAnalysisMultiPage($pdf, $m_test, $page_width): void
    {
        // Group results by child group
        $groupedResults = [];
        foreach ($m_test->results as $result) {
            if ($result->result == '' || $result->result == 'no sample') {
                continue;
            }
            
            $child_test = $result->childTest;
            if ($child_test == null) continue;
            
            $groupName = $child_test->childGroup?->name ?? 'Other';
            if (!isset($groupedResults[$groupName])) {
                $groupedResults[$groupName] = [];
            }
            $groupedResults[$groupName][] = $result;
        }
        
        if (empty($groupedResults)) {
            return;
        }
        
        // PAGE 1: Personal Information + Statistics (no AddPage - starts on current page)
        $this->renderSemenAnalysisPage1($pdf, $groupedResults, $page_width);
        
        // PAGE 2: Physico-Chemical Properties + Morphology (morphology on its own page) + comment after morphology
        $this->renderSemenAnalysisPage2($pdf, $groupedResults, $page_width, $m_test->comment ?? null);
        
        // PAGE 3: Image only (comment moved to after Morphology on separate page)
        $this->renderSemenAnalysisPage3($pdf, $groupedResults, $page_width);
    }
    
    /**
     * Render generic special test (non-semen analysis)
     */
    private function renderGenericSpecialTest($pdf, $m_test, $page_width): void
    {
        // Group results by child group
        $groupedResults = [];
        foreach ($m_test->results as $result) {
            if ($result->result == '' || $result->result == 'no sample') {
                continue;
            }
            
            $child_test = $result->childTest;
            if ($child_test == null) continue;
            
            $groupName = $child_test->childGroup?->name ?? 'Other';
            if (!isset($groupedResults[$groupName])) {
                $groupedResults[$groupName] = [];
            }
            $groupedResults[$groupName][] = $result;
        }
        
        if (empty($groupedResults)) {
            return;
        }
        
        // Render each group as a separate section
        $hideUnit = $m_test->mainTest->hide_unit ?? false;
        foreach ($groupedResults as $groupName => $results) {
            $this->renderSpecialTestGroup($pdf, $groupName, $results, $page_width, false, $hideUnit);
        }
    }
    
    /**
     * PAGE 1: Personal Information + Statistics (no AddPage - starts on current page)
     */
    private function renderSemenAnalysisPage1($pdf, $groupedResults, $page_width): void
    {
        // Main header with modern gradient-like styling
        $pdf->SetFont('arial', 'B', 20, '', true);
        $pdf->SetFillColor(41, 98, 255); // Professional blue
        $pdf->SetDrawColor(41, 98, 255);
        $pdf->SetTextColor(255, 255, 255); // White text
        
        $headerHeight = 14;
        $pdf->RoundedRect(PDF_MARGIN_LEFT, $pdf->GetY(), $page_width, $headerHeight, 4, '1111', 'DF');
        $pdf->Cell($page_width, $headerHeight, "GENERAL SEMEN ANALYSIS", 0, 1, 'C', 1);
        
        // Reset colors
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetTextColor(0, 0, 0);
        
        $this->addVerticalSpacing($pdf, $this->sectionSpacing);
        
        // Personal Information section
        if (isset($groupedResults['PERSONAL INFORMATION'])) {
            $this->renderPersonalInformationSection($pdf, $groupedResults['PERSONAL INFORMATION'], $page_width);
        }
        
        // Statistics section follows directly on the same page
        if (isset($groupedResults['STATISTICS'])) {
            $this->renderStatisticsSection($pdf, $groupedResults['STATISTICS'], $page_width, true);
        }
    }
    
    /**
     * PAGE 2: Physico-Chemical Properties + Morphology
     */
    private function renderSemenAnalysisPage2($pdf, $groupedResults, $page_width, $comment = null): void
    {
        $pdf->AddPage();
        
        // Physico-Chemical Properties section
        if (isset($groupedResults['PHYSICO – CHEMICAL PROPERTIES'])) {
            $this->renderPhysicoChemicalSection($pdf, $groupedResults['PHYSICO – CHEMICAL PROPERTIES'], $page_width);
        }
        
        // Morphology on a separate page
        if (isset($groupedResults['MORPHOLOGY'])) {
            $pdf->AddPage();
            $this->renderMorphologySection($pdf, $groupedResults['MORPHOLOGY'], $page_width);
            // Immediately after morphology, render the comment if provided
            if ($comment !== null && trim((string)$comment) !== '') {
                $this->addVerticalSpacing($pdf, 4);
                $pdf->SetFont('arial', 'u', 12, '', true);
                $pdf->Cell($page_width, 6, "Comment", 0, 1, 'L');
                $pdf->SetFont('arial', '', 11, '', true);
                $pdf->MultiCell($page_width, 5, (string)$comment, 0, 'L', 0, 1, '', '', true);
            }
        }
    }
    
    /**
     * PAGE 3: Reference diagram
     */
    private function renderSemenAnalysisPage3($pdf, $groupedResults, $page_width, $comment = null): void
    {
        $pdf->AddPage();
        
        // Add a professional header for the reference diagram
        $pdf->SetFont('arial', 'B', 13, '', true);
        $pdf->SetFillColor(248, 249, 250);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetTextColor(52, 58, 64);
        
        $headerHeight = 9;
        $pdf->RoundedRect(PDF_MARGIN_LEFT, $pdf->GetY(), $page_width, $headerHeight, 2, '1111', 'DF');
        $pdf->Cell($page_width, $headerHeight, "REFERENCE DIAGRAM", 0, 1, 'L', 1);
        
        // Reset colors
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetTextColor(0, 0, 0);
        
        $this->addVerticalSpacing($pdf, 5);

        // Comment is now rendered after Morphology on its own page, so skip here
        
        // Add semen.png image
        $imagePath = public_path('semen.png');
        if (file_exists($imagePath)) {
            // Get image dimensions
            $imageInfo = getimagesize($imagePath);
            $imageWidth = $imageInfo[0];
            $imageHeight = $imageInfo[1];
            
            // Calculate scaling to fit page width with padding
            $maxWidth = $page_width * 0.9; // 90% of page width
            $maxHeight = 200; // Maximum height
            
            $scaleWidth = $maxWidth / $imageWidth;
            $scaleHeight = $maxHeight / $imageHeight;
            $scale = min($scaleWidth, $scaleHeight, 1);
            
            $displayWidth = $imageWidth * $scale;
            $displayHeight = $imageHeight * $scale;
            
            // Center the image horizontally
            $x = PDF_MARGIN_LEFT + ($page_width - $displayWidth) / 2;
            $y = $pdf->GetY();
            
            // Add a subtle border around the image
            $pdf->SetDrawColor(220, 220, 220);
            $pdf->Rect($x - 2, $y - 2, $displayWidth + 4, $displayHeight + 4, 'D');
            
            $pdf->Image($imagePath, $x, $y, $displayWidth, $displayHeight);
        }
    }
    
    
    
    /**
     * Render Personal Information section (like the image layout)
     */
    private function renderPersonalInformationSection($pdf, $results, $page_width): void
    {
        // Section header with professional styling
        $pdf->SetFont('arial', 'B', 13, '', true);
        $pdf->SetFillColor(248, 249, 250); // Light gray
        $pdf->SetDrawColor(220, 220, 220); // Subtle border
        $pdf->SetTextColor(52, 58, 64); // Dark gray text
        
        $headerHeight = 9;
        $pdf->RoundedRect(PDF_MARGIN_LEFT, $pdf->GetY(), $page_width, $headerHeight, 2, '1111', 'DF');
        $pdf->Cell($page_width, $headerHeight, "PERSONAL INFORMATION", 0, 1, 'L', 1);
        
        // Reset colors
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetTextColor(0, 0, 0);
        
        $this->addVerticalSpacing($pdf, 2);
        
        // Create a 2-column layout for personal information
        $colWidth = $page_width / 2;
        $rowHeight = 7;
        
        $pdf->SetFont('arial', '', 10, '', true);
        
        // Group results into pairs for 2-column layout
        $pairs = array_chunk($results, 2);
        
        $isEvenRow = false;
        foreach ($pairs as $pair) {
            // Alternating row colors for better readability
            if ($isEvenRow) {
                $pdf->SetFillColor(250, 250, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $y = $pdf->GetY();
            
            // Left column
            if (isset($pair[0])) {
                $result = $pair[0];
                $child_test = $result->childTest;
                $pdf->SetFont('arial', 'B', 9, '', true);
                $pdf->Cell($colWidth * 0.45, $rowHeight, $child_test->child_test_name, 'LTB', 0, 'L', 1);
                $pdf->SetFont('arial', '', 9, '', true);
                $pdf->Cell($colWidth * 0.55, $rowHeight, $result->result, 'RTB', 0, 'L', 1);
            } else {
                $pdf->Cell($colWidth, $rowHeight, '', 'LRTB', 0, 'L', 1);
            }
            
            // Right column
            if (isset($pair[1])) {
                $result = $pair[1];
                $child_test = $result->childTest;
                $pdf->SetFont('arial', 'B', 9, '', true);
                $pdf->Cell($colWidth * 0.45, $rowHeight, $child_test->child_test_name, 'LTB', 0, 'L', 1);
                $pdf->SetFont('arial', '', 9, '', true);
                $pdf->Cell($colWidth * 0.55, $rowHeight, $result->result, 'RTB', 1, 'L', 1);
            } else {
                $pdf->Cell($colWidth, $rowHeight, '', 'LRTB', 1, 'L', 1);
            }
            
            $isEvenRow = !$isEvenRow;
        }
        
        $this->addVerticalSpacing($pdf, $this->sectionSpacing);
    }
    
    /**
     * Render Physico-Chemical Properties section (like the image layout)
     */
    private function renderPhysicoChemicalSection($pdf, $results, $page_width): void
    {
        // Section header with professional styling
        $pdf->SetFont('arial', 'B', 13, '', true);
        $pdf->SetFillColor(248, 249, 250);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetTextColor(52, 58, 64);
        
        $headerHeight = 9;
        $pdf->RoundedRect(PDF_MARGIN_LEFT, $pdf->GetY(), $page_width, $headerHeight, 2, '1111', 'DF');
        $pdf->Cell($page_width, $headerHeight, "PHYSICO – CHEMICAL PROPERTIES", 0, 1, 'L', 1);
        
        // Reset colors
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetTextColor(0, 0, 0);
        
        $this->addVerticalSpacing($pdf, 2);
        
        // Table header with modern styling
        $pdf->SetFont('arial', 'B', 10, '', true);
        $pdf->SetFillColor(41, 98, 255); // Professional blue header
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(41, 98, 255);
        
        $paramWidth = $page_width * 0.4;
        $resultWidth = $page_width * 0.3;
        $refWidth = $page_width * 0.3;
        
        $pdf->Cell($paramWidth, 8, "PARAMETER", 1, 0, 'C', 1);
        $pdf->Cell($resultWidth, 8, "PATIENT RESULTS", 1, 0, 'C', 1);
        $pdf->Cell($refWidth, 8, "REFERENCE VALUE", 1, 1, 'C', 1);
        
        // Reset for table rows
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(220, 220, 220);
        
        // Table rows with alternating colors
        $pdf->SetFont('arial', '', 9, '', true);
        $isEvenRow = false;
        foreach ($results as $result) {
            $child_test = $result->childTest;
            $normal_range = $child_test->normalRange ?? '';
            
            // Alternating row colors
            if ($isEvenRow) {
                $pdf->SetFillColor(250, 250, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            $pdf->Cell($paramWidth, 7, $child_test->child_test_name, 1, 0, 'L', 1);
            $pdf->SetFont('arial', 'B', 9, '', true);
            $pdf->Cell($resultWidth, 7, $result->result, 1, 0, 'C', 1);
            $pdf->SetFont('arial', '', 9, '', true);
            $pdf->Cell($refWidth, 7, $normal_range, 1, 1, 'C', 1);
            
            $isEvenRow = !$isEvenRow;
        }
        
        $this->addVerticalSpacing($pdf, $this->sectionSpacing);
    }
    
    /**
     * Render Morphology section
     */
    private function renderMorphologySection($pdf, $results, $page_width): void
    {
        // Section header with professional styling
        $pdf->SetFont('arial', 'B', 13, '', true);
        $pdf->SetFillColor(248, 249, 250);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetTextColor(52, 58, 64);
        
        $headerHeight = 9;
        $pdf->RoundedRect(PDF_MARGIN_LEFT, $pdf->GetY(), $page_width, $headerHeight, 2, '1111', 'DF');
        $pdf->Cell($page_width, $headerHeight, "MORPHOLOGY", 0, 1, 'L', 1);
        
        // Reset colors
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetTextColor(0, 0, 0);
        
        $this->addVerticalSpacing($pdf, 2);
        
        // Table header with modern styling
        $pdf->SetFont('arial', 'B', 10, '', true);
        $pdf->SetFillColor(41, 98, 255); // Professional blue header
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(41, 98, 255);
        
        $paramWidth = $page_width * 0.4;
        $resultWidth = $page_width * 0.3;
        $refWidth = $page_width * 0.3;
        
        $pdf->Cell($paramWidth, 8, "PARAMETER", 1, 0, 'C', 1);
        $pdf->Cell($resultWidth, 8, "PATIENT RESULTS", 1, 0, 'C', 1);
        $pdf->Cell($refWidth, 8, "REFERENCE VALUE", 1, 1, 'C', 1);
        
        // Reset for table rows
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(220, 220, 220);
        
        // Table rows with alternating colors
        $pdf->SetFont('arial', '', 9, '', true);
        $isEvenRow = false;
        foreach ($results as $result) {
            $child_test = $result->childTest;
            $normal_range = $child_test->normalRange ?? '';
            
            // Alternating row colors
            if ($isEvenRow) {
                $pdf->SetFillColor(250, 250, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            // Special formatting for percentage results
            $displayResult = $result->result;
            if ($this->isPercentageResult($child_test->child_test_name)) {
                $displayResult = $result->result . '%';
            }
            
            $pdf->Cell($paramWidth, 7, $child_test->child_test_name, 1, 0, 'L', 1);
            $pdf->SetFont('arial', 'B', 9, '', true);
            $pdf->Cell($resultWidth, 7, $displayResult, 1, 0, 'C', 1);
            $pdf->SetFont('arial', '', 9, '', true);
            $pdf->Cell($refWidth, 7, $normal_range, 1, 1, 'C', 1);
            
            $isEvenRow = !$isEvenRow;
        }
        
        $this->addVerticalSpacing($pdf, $this->sectionSpacing);
    }
    
    /**
     * Render Statistics section with WHO 2021 reference ranges
     */
    private function renderStatisticsSection($pdf, $results, $page_width, bool $isSemen = false): void
    {
        // Section header with professional styling
        $pdf->SetFont('arial', 'B', 13, '', true);
        $pdf->SetFillColor(248, 249, 250);
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetTextColor(52, 58, 64);
        
        $headerHeight = 9;
        $pdf->RoundedRect(PDF_MARGIN_LEFT, $pdf->GetY(), $page_width, $headerHeight, 2, '1111', 'DF');
        $pdf->Cell($page_width, $headerHeight, "STATISTICS ", 0, 1, 'L', 1);
        
        // Reset colors
        $pdf->SetDrawColor(220, 220, 220);
        $pdf->SetTextColor(0, 0, 0);
        
        $this->addVerticalSpacing($pdf, 2);
        
        // Table header with modern styling - Professional blue background
        $pdf->SetFont('arial', 'B', 10, '', true);
        $pdf->SetFillColor(41, 98, 255);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(41, 98, 255);

        if ($isSemen) {
            // Multi-row header: PARAMETER | PATIENT RESULTS | REFERENCE VALUE (Lower | Mean | Upper)
            $paramWidth = $page_width * 0.35;
            $resultWidth = $page_width * 0.20;
            $refHeaderWidth = $page_width * 0.45;
            $subRef = $refHeaderWidth / 3;

            // First header row
            $pdf->Cell($paramWidth, 8, "PARAMETER", 1, 0, 'C', 1);
            $pdf->Cell($resultWidth, 8, "PATIENT RESULTS", 1, 0, 'C', 1);
            $pdf->Cell($refHeaderWidth, 8, "REFERENCE VALUE", 1, 1, 'C', 1);

            // Second header row for refs
            $pdf->SetFont('arial', 'B', 8, '', true);
            $pdf->Cell($paramWidth, 7, "", 1, 0, 'C', 1);
            $pdf->Cell($resultWidth, 7, "", 1, 0, 'C', 1);
            $pdf->Cell($subRef, 7, "LOWER", 1, 0, 'C', 1);
            $pdf->Cell($subRef, 7, "MEAN", 1, 0, 'C', 1);
            $pdf->Cell($subRef, 7, "UPPER", 1, 1, 'C', 1);
        } else {
            $paramWidth = $page_width * 0.4;
            $resultWidth = $page_width * 0.3;
            $refWidth = $page_width * 0.3;

            $pdf->Cell($paramWidth, 8, "PARAMETER", 1, 0, 'C', 1);
            $pdf->Cell($resultWidth, 8, "PATIENT RESULTS", 1, 0, 'C', 1);
            $pdf->Cell($refWidth, 8, "REFERENCE VALUE", 1, 1, 'C', 1);
        }
        
        // Reset for table rows
        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(220, 220, 220);
        
        // Table rows with alternating colors
        $pdf->SetFont('arial', '', 9, '', true);
        $isEvenRow = false;
        foreach ($results as $result) {
            $child_test = $result->childTest;
            $normal_range = $child_test->normalRange ?? '';
            
            // Alternating row colors
            if ($isEvenRow) {
                $pdf->SetFillColor(250, 250, 250);
            } else {
                $pdf->SetFillColor(255, 255, 255);
            }
            
            // Special formatting for percentage results
            $displayResult = $result->result;
            if ($this->isPercentageResult($child_test->child_test_name)) {
                $displayResult = $result->result . '%';
            }
            
            if ($isSemen) {
                $pdf->Cell($paramWidth, 7, $child_test->child_test_name, 1, 0, 'L', 1);
                $pdf->SetFont('arial', 'B', 9, '', true);
                // Append exponent unit for Total sperm number within semen statistics
                $displayForCell = $displayResult;
                if (trim((string)$child_test->child_test_name) === 'Total sperm number (x10⁶  / ejaculate )') {
                    $displayForCell = rtrim((string)$displayResult) . ' x10⁶';
                }
                $pdf->Cell($resultWidth, 7, $displayForCell, 1, 0, 'C', 1);
                $pdf->SetFont('arial', '', 9, '', true);
                $lower = $child_test->lower_limit ?? '';
                $mean = $child_test->mean ?? '';
                $upper = $child_test->upper_limit ?? '';
                if ($lower !== '' || $mean !== '' || $upper !== '') {
                    $pdf->Cell($subRef, 7, $lower, 1, 0, 'C', 1);
                    $pdf->Cell($subRef, 7, $mean, 1, 0, 'C', 1);
                    $pdf->Cell($subRef, 7, $upper, 1, 1, 'C', 1);
                } else {
                    // If no discrete limits, span the whole reference area with normalRange
                    $pdf->Cell($refHeaderWidth, 7, $normal_range, 1, 1, 'C', 1);
                }
            } else {
                $pdf->Cell($paramWidth, 7, $child_test->child_test_name, 1, 0, 'L', 1);
                $pdf->SetFont('arial', 'B', 9, '', true);
                $pdf->Cell($resultWidth, 7, $displayResult, 1, 0, 'C', 1);
                $pdf->SetFont('arial', '', 9, '', true);
                $pdf->Cell($refWidth, 7, $normal_range, 1, 1, 'C', 1);
            }
            
            $isEvenRow = !$isEvenRow;
        }
        
        $this->addVerticalSpacing($pdf, $this->sectionSpacing);
    }

    /**
     * Render a single group within a special test
     */
    private function renderSpecialTestGroup($pdf, $groupName, $results, $page_width, $isSemenAnalysis = false, bool $hideUnit = false): void
    {
        // Special styling for semen analysis groups
        if ($isSemenAnalysis) {
            $this->renderSemenAnalysisGroupHeader($pdf, $groupName, $page_width);
        } else {
            // Standard group header styling
            $pdf->SetFont('arial', 'B', 14, '', true);
            $pdf->SetFillColor(230, 235, 240); // Light blue background
            $pdf->SetDrawColor(180, 190, 200); // Border color
            $pdf->SetTextColor(50, 60, 70); // Dark text
            
            $headerHeight = 8;
            $this->ensureSpaceFor($pdf, $headerHeight + $this->smallSpacing);
            
            $pdf->RoundedRect(PDF_MARGIN_LEFT, $pdf->GetY(), $page_width, $headerHeight, 3, '1111');
            $pdf->Cell($page_width, $headerHeight, $groupName, 1, 1, 'C', 1);
            
            // Reset colors
            $pdf->SetFillColor(255, 255, 255);
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetTextColor(0, 0, 0);
        }
        
        $this->addVerticalSpacing($pdf, $this->smallSpacing);
        
        // Render table header for this group
        $this->renderSpecialTestTableHeader($pdf, $page_width, $hideUnit);
        
        // Render each result in the group
        foreach ($results as $result) {
            $this->renderSpecialTestResultRow($pdf, $result, $page_width, $isSemenAnalysis, $hideUnit);
        }
        
        // Add spacing after group
        $this->addVerticalSpacing($pdf, $this->sectionSpacing);
    }
    
    /**
     * Render semen analysis specific group headers with enhanced styling
     */
    private function renderSemenAnalysisGroupHeader($pdf, $groupName, $page_width): void
    {
        // Define colors for different groups
        $groupColors = [
            'PERSONAL INFORMATION' => [255, 240, 245], // Light pink
            'PHYSICO – CHEMICAL PROPERTIES' => [240, 255, 240], // Light green
            'MORPHOLOGY' => [255, 248, 220], // Light yellow
            'STATISTICS' => [240, 248, 255], // Light blue
        ];
        
        $colors = $groupColors[$groupName] ?? [230, 235, 240]; // Default light gray
        
        $pdf->SetFont('arial', 'B', 14, '', true);
        $pdf->SetFillColor(...$colors);
        $pdf->SetDrawColor(150, 150, 150);
        $pdf->SetTextColor(40, 40, 40);
        
        $headerHeight = 8;
        $this->ensureSpaceFor($pdf, $headerHeight + $this->smallSpacing);
        
        // Draw group header with rounded rectangle
        $pdf->RoundedRect(PDF_MARGIN_LEFT, $pdf->GetY(), $page_width, $headerHeight, 3, '1111');
        $pdf->Cell($page_width, $headerHeight, $groupName, 1, 1, 'C', 1);
        
        // Reset colors
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetDrawColor(0, 0, 0);
        $pdf->SetTextColor(0, 0, 0);
    }
    
    /**
     * Render table header for special test groups
     */
    private function renderSpecialTestTableHeader($pdf, $page_width, bool $hideUnit = false): void
    {
        $pdf->SetFont('arial', 'B', 10, '', true);
        $pdf->SetFillColor(245, 247, 250);
        $pdf->SetDrawColor(200, 205, 210);
        
        // Calculate column widths for special test layout
        $testColWidth = $page_width * 0.4; // 40% for test name
        $resultColWidth = $page_width * 0.25; // 25% for result
        $unitColWidth = $hideUnit ? 0 : $page_width * 0.15; // 15% for unit (0 if hidden)
        $rangeColWidth = $hideUnit ? $page_width * 0.35 : $page_width * 0.2; // 35% if unit hidden, 20% if shown
        
        $pdf->Cell($testColWidth, 6, "Test", 1, 0, 'C', 1);
        $pdf->Cell($resultColWidth, 6, "Result", 1, 0, 'C', 1);
        if (!$hideUnit) {
            $pdf->Cell($unitColWidth, 6, "Unit", 1, 0, 'C', 1);
        }
        $pdf->Cell($rangeColWidth, 6, "Normal Range", 1, 1, 'C', 1);
        
        $this->addVerticalSpacing($pdf, $this->smallSpacing);
    }
    
    /**
     * Render a single result row for special tests
     */
    private function renderSpecialTestResultRow($pdf, $result, $page_width, $isSemenAnalysis = false, bool $hideUnit = false): void
    {
        $child_test = $result->childTest;
        $unit = $child_test?->unit?->name ?? '';
        $normal_range = $result->normal_range ?? '';
        $report_result = $result->result;
        
        // Calculate column widths (same as header)
        $testColWidth = $page_width * 0.4;
        $resultColWidth = $page_width * 0.25;
        $unitColWidth = $hideUnit ? 0 : $page_width * 0.15;
        $rangeColWidth = $hideUnit ? $page_width * 0.35 : $page_width * 0.2;
        
        // Estimate row height for page break
        $estimatedResultHeight = $this->measureTextHeight($pdf, $resultColWidth, (string)$report_result, $this->baseLineHeight);
        $estimatedNormalHeight = $this->measureTextHeight($pdf, $rangeColWidth, (string)$normal_range, $this->baseLineHeight);
        $estimatedRowHeight = max($this->baseLineHeight, $estimatedResultHeight, $estimatedNormalHeight);
        $this->ensureSpaceFor($pdf, $estimatedRowHeight + $this->smallSpacing);
        
        // Test name
        $pdf->SetFont('arial', '', 9, '', true);
        $pdf->Cell($testColWidth, 5, $child_test->child_test_name, 1, 0, 'L');
        
        // Result with special formatting for semen analysis
        $pdf->SetFont('arial', '', 10, '', true);
        
        // Special formatting for certain semen analysis results
        if ($isSemenAnalysis && $this->isPercentageResult($child_test->child_test_name)) {
            $pdf->SetFont('arial', 'B', 10, '', true);
            $resultCellHeight = $pdf->MultiCell($resultColWidth, 5, $report_result . '%', 1, 'C', 0, 0, '', '', true);
        } else {
            $resultCellHeight = $pdf->MultiCell($resultColWidth, 5, $report_result, 1, 'C', 0, 0, '', '', true);
        }
        
        // Unit
        if (!$hideUnit) {
            $pdf->SetFont('arial', '', 9, '', true);
            $pdf->MultiCell($unitColWidth, 5, $unit, 1, 'C', 0, 0, '', '', true);
        }
        
        // Normal range
        $pdf->SetFont('arial', '', 9, '', true);
        $normalRangeCellHeight = $pdf->MultiCell($rangeColWidth, 5, $normal_range, 1, 'C', 0, 1, '', '', true);
        
        // Draw horizontal line
        $y = $pdf->GetY();
        $pdf->Line(PDF_MARGIN_LEFT, $y, PDF_MARGIN_LEFT + $page_width, $y);
    }
    
    /**
     * Check if a test result should be displayed as a percentage
     */
    private function isPercentageResult($testName): bool
    {
        $percentageTests = [
            'Normal morphology %',
            'Abnormal morphology %',
            'Rapidly progressive PR (grade A)',
            'Slow progressive PR (grade B)',
            'NP-Sluggish (grade C)',
            'Immotile (grade D)',
            'Vitality'
        ];
        
        return in_array($testName, $percentageTests);
    }

    /**
     * Render comments for the test
     */
    private function renderComments($pdf, $m_test, $page_width): void
    {
        if (str_word_count($m_test->comment) > 0) {
            // Ensure there is room for the comment label and content
            $estimated = $this->measureTextHeight($pdf, $page_width, (string)$m_test->comment, $this->baseLineHeight) + $this->headerSpacing + $this->smallSpacing;
            $this->ensureSpaceFor($pdf, $estimated);
            $pdf->SetFont('arial', 'u', 14, '', true);
            $pdf->resetColumns();
            if ($m_test->mainTest->divided == 1) {
                $pdf->Ln(15);

            }else{

                $pdf->Ln(5);
            }
            $pdf->cell(20, 5, "Comment", 0, 1, 'C'); // bcforh
            $y = $pdf->GetY();
            $pdf->SetFont('arial', 'b', 12, '', true);
            $pdf->MultiCell($page_width, 5, "♠ " . $m_test->comment, 0, "", 0);
            $pdf->SetFont('arial', '', 12, '', true);
        }
    }

    /**
     * Render organisms section
     */
    private function renderOrganisms($pdf, $m_test, $col_number, $column_width): void
    {
        $organismCount = $m_test->requestedOrganisms()->count();
        
        if ($organismCount > 0) {
            // Set up columns for multiple organisms
            if ($organismCount > 1) {
                $pdf->setEqualColumns($organismCount, $pdf->getPageWidth() - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT - 10);
            }
            
            foreach ($m_test->requestedOrganisms as $index => $organism) {
                // Estimate organism block height: title + headers + max(text heights)
                $sensHeight = $this->measureTextHeight($pdf, $column_width, (string)$organism->sensitive, $this->baseLineHeight);
                $resHeight = $this->measureTextHeight($pdf, $column_width, (string)$organism->resistant, $this->baseLineHeight);
                $blockHeight = 10 + 5 + max($sensHeight, $resHeight) + $this->smallSpacing;
                $this->ensureSpaceFor($pdf, $blockHeight);
                
                // Select the appropriate column
                if ($organismCount > 1) {
                    $pdf->selectColumn($index);
                } else {
                    $pdf->selectColumn($col_number);
                }
                
                $pdf->SetFont('arial', 'b', 15, '', true);
                $pdf->SetFillColor(...$this->themeHeaderFill);
                $pdf->SetDrawColor(...$this->themeBorderColor);
                $pdf->cell($column_width * 2, 10, $organism->organism, 1, 1, 'C', 1);
                $pdf->SetFont('arial', '', 11, '', true);
                $pdf->SetFont('arial', '', 12, '', true);
                $pdf->cell($column_width, 5, 'Sensitivity', 1, 0, 'C', 0);
                $pdf->cell($column_width, 5, 'Resistant', 1, 1, 'C', 0);
                $pdf->MultiCell($column_width, 5, $organism->sensitive, 1, 'C', ln: 0);
                $pdf->MultiCell($column_width, 5, $organism->resistant, 1, 'C', ln: 1);
                $col_number++;
            }
            
            // Reset columns after rendering organisms
            $pdf->resetColumns();
        }
    }

    /**
     * Draw a standardized result table header for both single and divided layouts
     */
    private function renderResultTableHeader($pdf, $table_col_widht, bool $isDivided, bool $hideUnit = false): void
    {
        $pdf->SetFont('arial', 'b', 11, '', true);
        [$colW, $resultW, $unitW, $rangeW] = $this->computeColumnWidths($pdf, $table_col_widht, $isDivided, $hideUnit);
        $pdf->SetFillColor(...$this->themeHeaderFill);
        $pdf->SetDrawColor(...$this->themeBorderColor);
        $pdf->cell($colW, 6, "Test", 1, 0, 'C', 1);
        $pdf->cell($resultW, 6, "Result", 1, 0, 'C', 1);
        if (!$hideUnit) {
            $pdf->cell($unitW, 6, "Unit", 1, 0, 'C', 1);
        }
        $pdf->cell($rangeW, 6, "R.Values", 1, 1, 'C', 1);
        $this->addVerticalSpacing($pdf, $this->smallSpacing);
    }

    /**
     * Compute column widths (test, result, unit, range) depending on layout
     */
    private function computeColumnWidths($pdf, float $table_col_widht, bool $isDivided, bool $hideUnit = false): array
    {
        $base = $isDivided ? (($pdf->getPageWidth() - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT) / 8) : $table_col_widht;
        $testW = $base;
        $resultW = $base * 1.5;
        $unitW = $hideUnit ? 0 : $base / 2;
        $rangeW = $hideUnit ? $base + ($base / 2) : $base; // Add unit width to range when hiding unit
        return [$testW, $resultW, $unitW, $rangeW];
    }

    /**
     * Add vertical spacing with auto page-break awareness
     */
    private function addVerticalSpacing($pdf, float $space): void
    {
        $this->ensureSpaceFor($pdf, $space);
        $pdf->Ln($space);
    }

    // ----- Layout helpers -----

    private function getBottomPrintableY($pdf): float
    {
        // Bottom printable Y considering the automatic page break margin
        return $pdf->getPageHeight() - $pdf->getBreakMargin();
    }

    private function getRemainingSpace($pdf): float
    {
        return $this->getBottomPrintableY($pdf) - $pdf->GetY();
    }

    private function ensureSpaceFor($pdf, float $requiredHeight): void
    {
        if ($this->getRemainingSpace($pdf)+ 5 <= $requiredHeight) {
            $pdf->AddPage();
        }
    }

    private function measureTextHeight($pdf, float $width, string $text, float $lineHeight): float
    {
        $lines = max(1, (int)$pdf->getNumLines($text, $width));
        return $lines * $lineHeight;
    }
}
