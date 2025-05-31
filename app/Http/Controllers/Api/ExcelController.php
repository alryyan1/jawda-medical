<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\DoctorVisit;
use App\Models\ServiceGroup;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log; // For logging errors

class ExcelController extends Controller
{
    public function __construct()
    {
        // Add permissions like 'export insurance_claims'
        // $this->middleware('can:export insurance_claims');
    }

    /**
     * Export insurance claim data to Excel with selectable service group columns.
     *
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\StreamedResponse|\Illuminate\Http\JsonResponse
     */
    public function exportInsuranceClaim(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'service_group_ids' => 'nullable|array', // Array of ServiceGroup IDs to include
            'service_group_ids.*' => 'integer|exists:service_groups,id',
        ]);

        $company = Company::findOrFail($validated['company_id']);
        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();
        $selectedServiceGroupIds = $validated['service_group_ids'] ?? [];

        // Fetch service groups: either selected ones or all if none selected
        $serviceGroupsQuery = ServiceGroup::query();
        if (!empty($selectedServiceGroupIds)) {
            $serviceGroupsQuery->whereIn('id', $selectedServiceGroupIds);
        }
        $serviceGroupsToDisplay = $serviceGroupsQuery->orderBy('name')->get(); // Or by a custom order

        // Fetch patient visits for the company within the date range
        // Eager load necessary relationships for performance
        $patientVisits = DoctorVisit::with([
                'patient' => function ($query) {
                    $query->select('id', 'name', 'insurance_no', 'company_id'); // Select only needed patient fields
                },
                'patient.company:id,name', // For company name in general info if needed
                'requestedServices.service.serviceGroup', // For grouping by service group
                'labRequests.mainTest' // For lab test values
            ])
            ->whereHas('patient', function ($q) use ($company) {
                $q->where('company_id', $company->id);
            })
            ->whereBetween('doctorvisits.created_at', [$startDate, $endDate]) // Filter by visit creation date
            ->orderBy('doctorvisits.created_at') // Order visits chronologically
            ->get();

        if ($patientVisits->isEmpty()) {
            return response()->json(['message' => 'لا توجد بيانات لتصديرها بناءً على الفلاتر المحددة.'], 404);
        }

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setRightToLeft(true); // Enable RTL

            // --- Document Properties ---
            $spreadsheet->getProperties()
                ->setCreator(auth()->user()?->name ?? 'System')
                ->setLastModifiedBy(auth()->user()?->name ?? 'System')
                ->setTitle('مطالبة تأمين شركة: ' . $company->name)
                ->setSubject('مطالبة تأمين للفترة من ' . $startDate->format('Y-m-d') . ' إلى ' . $endDate->format('Y-m-d'));

            // --- Header Styles ---
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E90FF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]],
            ];
            $totalRowStyle = [
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'FF0000']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFFF99']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THICK, 'color' => ['rgb' => '000000']]],
            ];

            // --- Title and Date ---
            $sheet->mergeCells('A1:G1'); // Merge across an estimated number of static columns
            $sheet->setCellValue('A1', 'مطالبة شركة: ' . $company->name);
            $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->mergeCells('A2:G2');
            $sheet->setCellValue('A2', 'الفترة: ' . $startDate->format('Y-m-d') . ' - ' . $endDate->format('Y-m-d'));
            $sheet->getStyle('A2')->getFont()->setSize(12);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(25);
            $sheet->getRowDimension(2)->setRowHeight(20);
            $sheet->getRowDimension(3)->setRowHeight(20);

            // --- Define Columns ---
            $startRow = 4; // Start data table at row 4
            $columns = [
                ['header' => 'م', 'width' => 5, 'data_key' => 'sequence'],
                ['header' => 'رقم الزيارة', 'width' => 12, 'data_key' => 'visit_id'],
                ['header' => 'اسم المريض', 'width' => 35, 'data_key' => 'patient_name'],
                ['header' => 'تاريخ الزيارة', 'width' => 15, 'data_key' => 'visit_date'],
                ['header' => 'رقم البطاقة', 'width' => 20, 'data_key' => 'insurance_no'],
                ['header' => 'خدمات المختبر', 'width' => 20, 'data_key' => 'lab_services_value', 'is_numeric' => true, 'has_comment' => true, 'comment_key' => 'lab_services_names'],
            ];
            $staticColumnCount = count($columns);
            $currentColumnLetter = 'A';
            foreach ($columns as $i => $col) {
                $sheet->setCellValue($currentColumnLetter . $startRow, $col['header']);
                $sheet->getColumnDimension($currentColumnLetter)->setWidth($col['width']);
                $currentColumnLetter++;
            }

            // Add dynamic Service Group columns
            foreach ($serviceGroupsToDisplay as $sg) {
                $columns[] = ['header' => $sg->name, 'width' => 20, 'data_key' => 'sg_' . $sg->id, 'is_numeric' => true, 'has_comment' => true, 'comment_key' => 'sg_names_' . $sg->id];
                $sheet->setCellValue($currentColumnLetter . $startRow, $sg->name);
                $sheet->getColumnDimension($currentColumnLetter)->setWidth(20);
                $currentColumnLetter++;
            }

            // Add Total Endurance and Grand Total columns
            $columns[] = ['header' => 'إجمالي التحمل', 'width' => 20, 'data_key' => 'total_endurance', 'is_numeric' => true];
            $sheet->setCellValue($currentColumnLetter . $startRow, 'إجمالي التحمل');
            $sheet->getColumnDimension($currentColumnLetter)->setWidth(20);
            $lastDataColumnLetter = $currentColumnLetter;
            $currentColumnLetter++;

            $columns[] = ['header' => 'الإجمالي الكلي', 'width' => 20, 'data_key' => 'grand_total', 'is_numeric' => true];
            $sheet->setCellValue($currentColumnLetter . $startRow, 'الإجمالي الكلي');
            $sheet->getColumnDimension($currentColumnLetter)->setWidth(20);
            $grandTotalColumnLetter = $currentColumnLetter;

            // Apply header style
            $sheet->getStyle('A' . $startRow . ':' . $grandTotalColumnLetter . $startRow)->applyFromArray($headerStyle);
            $sheet->getRowDimension($startRow)->setRowHeight(20);

            // --- Populate Data Rows ---
            $dataRowNumber = $startRow + 1;
            foreach ($patientVisits as $index => $visit) {
                $rowData = [
                    'sequence' => $index + 1,
                    'visit_id' => $visit->id,
                    'patient_name' => $visit->patient?->name ?? '-',
                    'visit_date' => Carbon::parse($visit->visit_date)->format('Y-m-d'),
                    'insurance_no' => $visit->patient?->insurance_no ?? '-',
                ];

                // Calculate lab services value and names for comment
                $labValue = 0;
                $labNames = [];
                foreach ($visit->labRequests as $lr) {
                    // For insurance claims, you usually claim the net price after company discount, before patient payment
                    // But here, we are claiming the *endurance* amount for company patients.
                    // So, the value column should be $lr->endurance.
                    // The `total_lab_value_unpaid` or similar logic from your old code needs to be adapted.
                    // Let's assume for now `endurance` on LabRequest is what the company covers.
                    $labValue += (float) $lr->endurance; 
                    $labNames[] = $lr->mainTest?->main_test_name;
                }
                $rowData['lab_services_value'] = $labValue;
                $rowData['lab_services_names'] = implode(', ', $labNames);

                $totalVisitEndurance = $labValue;
                $grandVisitTotal = $labValue; // Start with lab value

                // Calculate service group values and names
                foreach ($serviceGroupsToDisplay as $sg) {
                    $sgValue = 0;
                    $sgNames = [];
                    foreach ($visit->requestedServices as $rs) {
                        if ($rs->service?->service_group_id == $sg->id) {
                            // Value for claim is rs->endurance (company coverage)
                            $sgValue += (float) $rs->endurance * (int)($rs->count ?? 1);
                            $sgNames[] = $rs->service?->name;
                        }
                    }
                    $rowData['sg_' . $sg->id] = $sgValue;
                    $rowData['sg_names_' . $sg->id] = implode(', ', $sgNames);
                    $totalVisitEndurance += $sgValue;
                    $grandVisitTotal += $sgValue; // Assuming endurance is part of grand total claim
                }
                
                $rowData['total_endurance'] = $totalVisitEndurance;
                // Grand total for the visit might be the sum of endurances,
                // or it could be the sum of net prices if you claim net price and company pays its part.
                // The provided old code seems to sum different values.
                // For now, let's assume grand_total for the claim is the sum of endurances.
                $rowData['grand_total'] = $totalVisitEndurance; 


                $currentColumnLetter = 'A';
                foreach ($columns as $col) {
                    $cellValue = $rowData[$col['data_key']] ?? 0; // Default to 0 for numeric sums
                    if ($col['is_numeric'] ?? false) {
                        $sheet->setCellValue($currentColumnLetter . $dataRowNumber, (float)$cellValue);
                        $sheet->getStyle($currentColumnLetter . $dataRowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
                    } else {
                        $sheet->setCellValue($currentColumnLetter . $dataRowNumber, $cellValue);
                    }
                    if ($col['has_comment'] ?? false) {
                        $commentText = $rowData[$col['comment_key']] ?? '';
                        if(!empty($commentText)) {
                            $sheet->getComment($currentColumnLetter . $dataRowNumber)->getText()->createTextRun($commentText);
                        }
                    }
                    $currentColumnLetter++;
                }
                $dataRowNumber++;
            }

            // --- Totals Row ---
            $currentColumnLetter = 'A';
            $sheet->setCellValue($currentColumnLetter . $dataRowNumber, 'الإجمالي'); // "Total"
            $currentColumnLetter++; // B
            $sheet->setCellValue($currentColumnLetter . $dataRowNumber, '');
            $currentColumnLetter++; // C
            $sheet->setCellValue($currentColumnLetter . $dataRowNumber, '');
            $currentColumnLetter++; // D
            $sheet->setCellValue($currentColumnLetter . $dataRowNumber, '');
            $currentColumnLetter++; // E (First numeric data column for sums is F - lab_services_value)
            
            // Sum for static columns then dynamic service groups
            foreach ($columns as $colIndex => $colSetup) {
                if ($colIndex < 5) continue; // Skip non-numeric/non-summed initial columns (Seq, VisitID, Name, Date, InsNo)
                
                $colLetterForSum = chr(ord('A') + $colIndex);
                if ($colSetup['is_numeric'] ?? false) {
                    $sheet->setCellValue($colLetterForSum . $dataRowNumber, "=SUM(" . $colLetterForSum . ($startRow + 1) . ":" . $colLetterForSum . ($dataRowNumber - 1) . ")");
                } else {
                     $sheet->setCellValue($colLetterForSum . $dataRowNumber, '');
                }
            }
            $sheet->getStyle('A' . $dataRowNumber . ':' . $grandTotalColumnLetter . $dataRowNumber)->applyFromArray($totalRowStyle);
            $sheet->getRowDimension($dataRowNumber)->setRowHeight(22);

            // --- Apply Borders & Alignment to Data ---
            $dataRange = 'A' . ($startRow + 1) . ':' . $grandTotalColumnLetter . ($dataRowNumber - 1);
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle($dataRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($dataRange)->getAlignment()->setWrapText(true);


            // --- File Output ---
            $writer = new Xlsx($spreadsheet);
            $fileName = 'insurance_claim_' . $company->name . '_' . date('Ymd_His') . '.xlsx';

            // Stream the file to the browser
            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (\Exception $e) {
            Log::error("Excel Export Error for company {$company->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['message' => 'حدث خطأ أثناء إنشاء ملف الإكسل.', 'error' => $e->getMessage()], 500);
        }
    }
}