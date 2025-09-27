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
    public function generate(DoctorVisit $doctorvisit, bool $base64 = false): string
    {
        ob_start();

        $patient = $doctorvisit->patient;
        if ($patient?->result_print_date == null) {
            $patient->update(['result_print_date' => now()]);
        }

        $pdf = new Pdf('portrait', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

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
        
        // Add watermark if enabled
        if ($settings?->show_water_mark && $logo_name != '') {
            $pdf->SetAlpha(0.2); // Transparency for watermark
            $pdf->Image($logo_path . '/' . $logo_name, 30, 100, 150, 150); // Image watermark
            $pdf->SetAlpha(1); // Reset transparency
        }

        // Add logo
        $this->addLogo($pdf, $settings, $logo_name, $logo_path, $page_width, $base64);

        $patient->update(['result_print_date' => now()]);
        
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

        $pdf->Ln(20);
        $pdf->Cell($page_width, 5, $settings?->lab_name ?? 'Lab Name' , 0, 1, 'C');

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
        $pdf->SetFont('arial', '', 10, '', true);
        $col = $page_width / 6;
        $user = auth()->user();
        $pdf->cell(20, 5, "Sign: ", 0, 1, 'L');
        $pdf->cell($col, 5, $user?->name ?? 'System', 0, 0, 'L');
        $pdf->cell($col, 5, " ", 0, 0, 'L');
        $pdf->cell($col, 5, " ", 0, 0, 'L');
        $pdf->cell($col, 5, " ", 0, 0, 'L');
        $pdf->cell($col, 5, "No ", 0, 0, 'R');
        $pdf->cell($col, 5, $patient->visit_number, 0, 1, 'C');

        if ($settings?->footer_content != null) {
            $pdf->SetFont('arial', '', 14, '', true);
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
    private function addLogo($pdf, $settings, $logo_name, $logo_path, $page_width, $base64): void
    {
        if ($settings?->is_logo) {
            if ($logo_name != '') {
                $pdf->Image($logo_path . '/' . $logo_name, 5, 5, 40, 40);
            }
        } else {
            //is_header الترويصه
            if ($settings?->is_header == '1') {
                $pdf->Image($logo_path . '/' . $logo_name, 10, 10, $page_width + 10, 30);
            }
        }
        
        if (!$base64) {
            //is_header الترويصه
            if ($settings?->is_header == 1) {
                $pdf->Image($logo_path . '/' . $logo_name, 10, 10, $page_width + 10, 30);
            }
        }
    }

    /**
     * Render lab results for all packages
     */
    private function renderLabResults($pdf, $patient, $settings, $page_width): void
    {
        $pdf->SetFillColor(240, 240, 240);
        $page_height = $pdf->getPageHeight() - PDF_MARGIN_TOP;
        $pdf->SetFont('aealarabiya', '', 10, '', true);
        $mypakages = Package::all();

        $pdf->SetFont('arial', '', 10, '', true);
        
        foreach ($mypakages as $package) {
            $this->renderPackageResults($pdf, $patient, $package, $page_width, $page_height);
        }
    }

    /**
     * Render results for a specific package
     */
    private function renderPackageResults($pdf, $patient, $package, $page_width, $page_height): void
    {
        $show_headers = true;
        $main_test_array = $patient->labrequests->filter(function ($item) use ($package) {
            return $item->mainTest->pack_id == $package->package_id;
        });
        $count = 0;

        foreach ($main_test_array as $m_test) {
            $count++;

            if ($m_test->hidden == 0) continue;
            
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
                    $this->renderResultTableHeader($pdf, $table_col_widht, $m_test->mainTest->divided == 1);
                }
                $show_headers = false;
            }
            
            if ($has_more_than1_child) {
                $this->renderMainTestHeader($pdf, $m_test, $table_col_widht, $page_width);
            }

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
        $this->renderResultTableHeader($pdf, $table_col_widht, $m_test->mainTest->divided == 1);
    }

    /**
     * Render individual test results
     */
    private function renderTestResults($pdf, $m_test, $table_col_widht, $page_width, $is_columns, $column_width): void
    {
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
                    $this->renderResultTableHeader($pdf, $table_col_widht, true);
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
            [$testW, $resultColWidth, $unitColWidth, $normalColWidth] = $this->computeColumnWidths($pdf, $table_col_widht, $m_test->mainTest->divided == 1);
            $estimatedResultHeight = $this->measureTextHeight($pdf, $resultColWidth, (string)$report_result, $this->baseLineHeight);
            $estimatedNormalHeight = $this->measureTextHeight($pdf, $normalColWidth, (string)$normal_range, $this->baseLineHeight);
            $estimatedRowHeight = max($this->baseLineHeight, $estimatedResultHeight, $estimatedNormalHeight);
            $this->ensureSpaceFor($pdf, $estimatedRowHeight + $this->smallSpacing);

            if (($child_id == 46 || $child_id == 70 || $child_id == 213) && $report_result != '' && is_numeric($report_result)) {
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
            
            $pdf->MultiCell($unitColWidth, 5, "$unit", 0, 'C', 0, 0, '', '', true);
            $pdf->SetFont('arial', '', 9, '', true);

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
        foreach ($m_test->requestedOrganisms as $organism) {
            // Estimate organism block height: title + headers + max(text heights)
            $sensHeight = $this->measureTextHeight($pdf, $column_width, (string)$organism->sensitive, $this->baseLineHeight);
            $resHeight = $this->measureTextHeight($pdf, $column_width, (string)$organism->resistant, $this->baseLineHeight);
            $blockHeight = 10 + 5 + max($sensHeight, $resHeight) + $this->smallSpacing;
            $this->ensureSpaceFor($pdf, $blockHeight);
            $pdf->selectColumn($col_number);
            $pdf->SetFont('arial', 'b', 15, '', true);
            $pdf->SetFillColor(...$this->themeHeaderFill);
            $pdf->SetDrawColor(...$this->themeBorderColor);
            $pdf->cell($column_width * 2, 10, $organism->organism, 1, 1, 'C', 1);
            $pdf->SetFont('arial', '', 11, '', true);
            $pdf->SetFont('arial', '', 12, '', true);
            $pdf->cell($column_width, 5, 'Sensitive', 1, 0, 'C', 0);
            $pdf->cell($column_width, 5, 'Resistant', 1, 1, 'C', 0);
            $pdf->MultiCell($column_width, 5, $organism->sensitive, 1, 'L', ln: 0);
            $pdf->MultiCell($column_width, 5, $organism->resistant, 1, 'L', ln: 1);
            $col_number++;
        }
    }

    /**
     * Draw a standardized result table header for both single and divided layouts
     */
    private function renderResultTableHeader($pdf, $table_col_widht, bool $isDivided): void
    {
        $pdf->SetFont('arial', 'b', 11, '', true);
        [$colW, $resultW, $unitW, $rangeW] = $this->computeColumnWidths($pdf, $table_col_widht, $isDivided);
        $pdf->SetFillColor(...$this->themeHeaderFill);
        $pdf->SetDrawColor(...$this->themeBorderColor);
        $pdf->cell($colW, 6, "Test", 1, 0, 'C', 1);
        $pdf->cell($resultW, 6, "Result", 1, 0, 'C', 1);
        $pdf->cell($unitW, 6, "Unit", 1, 0, 'C', 1);
        $pdf->cell($rangeW, 6, "R.Values", 1, 1, 'C', 1);
        $this->addVerticalSpacing($pdf, $this->smallSpacing);
    }

    /**
     * Compute column widths (test, result, unit, range) depending on layout
     */
    private function computeColumnWidths($pdf, float $table_col_widht, bool $isDivided): array
    {
        $base = $isDivided ? (($pdf->getPageWidth() - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT) / 8) : $table_col_widht;
        $testW = $base;
        $resultW = $base * 1.5;
        $unitW = $base / 2;
        $rangeW = $base;
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
        if ($this->getRemainingSpace($pdf) <= $requiredHeight) {
            $pdf->AddPage();
        }
    }

    private function measureTextHeight($pdf, float $width, string $text, float $lineHeight): float
    {
        $lines = max(1, (int)$pdf->getNumLines($text, $width));
        return $lines * $lineHeight;
    }
}
