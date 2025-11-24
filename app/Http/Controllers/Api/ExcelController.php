<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ServiceGroup;
use App\Models\AuditedPatientRecord;
use App\Models\AuditedRequestedService;
use App\Models\Cost;
use App\Models\CostCategory;
use App\Models\RequestedServiceDeposit;
use App\Models\Service;
use App\Models\DoctorVisit;
use App\Models\User;
use App\Models\Shift;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Log;

class ExcelController extends Controller
{
    public function reclaim(Request $request)
    {
        $company = Company::find($request->query("company"));

        if (!$company) {
            return response()->json(['error' => 'Company not found'], 404);
        }

        // Style headers
        $headerStyle = [
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E90FF'],
            ],
            'alignment' => [
                'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
            ],
        ];
        
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getActiveSheet()->setRightToLeft(true);

        // Get data
        $first = $request->query('first');
        $second = $request->query('second');
        $startDate = Carbon::parse($first)->startOfDay();
        $endDate = Carbon::parse($second)->endOfDay();

        $serviceGroups = ServiceGroup::all();

        $patients = DoctorVisit::whereHas('patient', function ($q) use ($company) {
            $q->where('company_id', '=', $company->id);
        })->whereBetween('created_at', [$startDate, $endDate])->get();
        
        $count = $patients->count() + 6;
        // Dynamic service-group columns will start after our fixed columns (C..J), so from K onward
        $letters = ['K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z'];
        $start_col_name = 'C';
        $end_col_name = $letters[$serviceGroups->count()];
        $last_col_name = $letters[$serviceGroups->count() + 1];

        $spreadsheet->getProperties()->setTitle('مطالبه التامين');
        $spreadsheet->getActiveSheet()->mergeCells('F2:I2');
        $spreadsheet->getActiveSheet()->getStyle('F2')->getFont()->setSize(18);
        $spreadsheet->getActiveSheet()->getStyle('F2')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $spreadsheet->getActiveSheet()->getStyle('F2')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $spreadsheet->getActiveSheet()->setCellValue("F2", $company->name);
        
        $spreadsheet->getActiveSheet()->mergeCells('F3:I3');
        $spreadsheet->getActiveSheet()->getStyle('F3')->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $spreadsheet->getActiveSheet()->getStyle('F3')->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);
        $carbon = Carbon::parse($first);
        $spreadsheet->getActiveSheet()->setCellValue("F3", $carbon->monthName);

        // Add borders
        $spreadsheet->getActiveSheet()->getStyle("C5:$last_col_name" . $count)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $spreadsheet->getActiveSheet()->getStyle("C5:$last_col_name" . $count)->getBorders()->getAllBorders()->getColor()->setRGB('000000');

        // Center alignment
        $spreadsheet->getActiveSheet()->getStyle("C1:$last_col_name" . $count)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        $spreadsheet->getActiveSheet()->getStyle("C1:$last_col_name" . $count)->getAlignment()->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // Apply header style
        $spreadsheet->getActiveSheet()->getStyle($start_col_name . '5:' . $end_col_name . '5')->applyFromArray($headerStyle);

        // Set column widths
        $spreadsheet->getActiveSheet()->getColumnDimension('C')->setWidth(10);  // الرقم
        $spreadsheet->getActiveSheet()->getColumnDimension('D')->setWidth(30);  // الاسم
        $spreadsheet->getActiveSheet()->getColumnDimension('E')->setWidth(15);  // التاريخ
        $spreadsheet->getActiveSheet()->getColumnDimension('F')->setWidth(20);  // رقم البطاقه
        $spreadsheet->getActiveSheet()->getColumnDimension('G')->setWidth(20);  // المعمل (lab total)
        $spreadsheet->getActiveSheet()->getColumnDimension('H')->setWidth(18);  // subcompany_id
        $spreadsheet->getActiveSheet()->getColumnDimension('I')->setWidth(18);  // company_relation_id
        $spreadsheet->getActiveSheet()->getColumnDimension('J')->setWidth(24);  // الضامن (guarantor)

        // Add SUM formulas and styling
        $spreadsheet->getActiveSheet()->setCellValue('G' . $count + 1, "=SUM(G4:G$count)");
        $spreadsheet->getActiveSheet()->getStyle('G' . $count + 1)->getFont()->setSize(20);

        $sumCellStyle = [
            'font' => [
                'bold' => true,
                'size' => 14,
                'color' => ['rgb' => 'FF0000'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'fill' => [
                'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'FFFF99'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THICK,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ];
        $spreadsheet->getActiveSheet()->getStyle('G' . $count + 1)->applyFromArray($sumCellStyle);
        $spreadsheet->getActiveSheet()->getStyle($last_col_name . $count + 1)->applyFromArray($sumCellStyle);

        // Set header names
        $spreadsheet->getActiveSheet()->setCellValue("C5", "الرقم ");
        $spreadsheet->getActiveSheet()->setCellValue("D5", "الاسم");
        $spreadsheet->getActiveSheet()->setCellValue("E5", "التاريخ ");
        $spreadsheet->getActiveSheet()->setCellValue("F5", "رقم البطاقه");
        $spreadsheet->getActiveSheet()->setCellValue("G5", "المعمل");
        $spreadsheet->getActiveSheet()->setCellValue("H5", "الشركة الفرعية");
        $spreadsheet->getActiveSheet()->setCellValue("I5", "علاقة الشركة");
        $spreadsheet->getActiveSheet()->setCellValue("J5", "الضامن");

        // Add service group headers and formulas
        $index = 0;
        foreach ($serviceGroups as $service_group) {
            $letter = $letters[$index];
            $spreadsheet->getActiveSheet()->setCellValue($letter . "5", $service_group->name);
            $spreadsheet->getActiveSheet()->setCellValue($letter . $count + 1, "=SUM($letter" . "6:" . $letter . "$count)");
            $spreadsheet->getActiveSheet()->getStyle($letter . $count + 1)->getFont()->setSize(20);
            $index++;
        }

        // Add totals
        $spreadsheet->getActiveSheet()->setCellValue($end_col_name . $count + 1, "=SUM($end_col_name" . "6:" . $end_col_name . "$count)");
        $spreadsheet->getActiveSheet()->setCellValue($end_col_name . "5", "التحمل");
        $spreadsheet->getActiveSheet()->setCellValue($last_col_name . "5", "اجمالي");
        $spreadsheet->getActiveSheet()->setCellValue($last_col_name . $count + 1, "=SUM($last_col_name" . "6:" . $last_col_name . "$count)");

        // Add patient data
        $patientsCells = [];
        $index = 1;
        $startRow = 5;
        
        foreach ($patients as $patient) {
            $inner_array = [];
            $inner_array[] = $index;
            $inner_array[] = $patient->patient->name;
            $inner_array[] = $patient->created_at->format('Y-m-d');
            $inner_array[] = $patient->patient->insurance_no;
            $inner_array[] = $patient->patient->total_lab_value_unpaid();
            // New fixed columns (H, I, J)
            $inner_array[] = $patient->patient->subcompany_id ?? '';
            $inner_array[] = $patient->patient->company_relation_id ?? '';
            $inner_array[] = $patient->patient->guarantor ?? '';
            
            // Set comment for Lab
            $comment = $spreadsheet->getActiveSheet()->getComment('G' . $startRow + $index);
            $comment->getText()->createTextRun($patient->patient->tests_concatinated());
            $comment->setAuthor('Ryan');

            // Add sum formula for each patient across dynamic group columns (from K..end_col_name)
            $st = $startRow + $index;
            $groupStartCol = 'K';
            $spreadsheet->getActiveSheet()->setCellValue($last_col_name . $st, "=SUM($groupStartCol$st:$end_col_name$st)");

            $innerIndex = 0;
            foreach ($serviceGroups as $service_group) {
                $total = $patient->total_services_according_to_service_group($service_group->id);

                // Set comment for services
                if ($total > 0) {
                    $comment = $spreadsheet->getActiveSheet()->getComment($letters[$innerIndex] . $startRow + $index);
                    $comment->getText()->createTextRun($patient->services_concatinated());
                    $comment->setAuthor('Ryan');
                }
                $inner_array[] = $total;
                $innerIndex++;
            }

            $inner_array[] = $patient->total_paid_services_insurance() + $patient->patient->paid_lab();
            $patientsCells[] = $inner_array;
            $index++;
        }
        
        $spreadsheet->getActiveSheet()->fromArray($patientsCells, null, 'C6');

        $writer = new Xlsx($spreadsheet);
        $filename = 'insurance_reclaim_' . $company->name . '_' . date('Y-m-d') . '.xlsx';
        
        return response()->streamDownload(function() use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . urlencode($filename) . '"'
        ]);
    }

    public function exportInsuranceClaim(Request $request)
    {
        $validated = $request->validate([
            'company_id' => 'required|integer|exists:companies,id',
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'service_group_ids' => 'nullable|array',
            'service_group_ids.*' => 'integer|exists:service_groups,id',
            'audit_status' => 'nullable|string|in:verified,approved_for_claim', // Usually 'verified' for claims
        ]);

        $company = Company::findOrFail($validated['company_id']);
        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();
        $selectedServiceGroupIds = $validated['service_group_ids'] ?? [];

        $serviceGroupsQuery = ServiceGroup::query();
        if (!empty($selectedServiceGroupIds)) {
            $serviceGroupsQuery->whereIn('id', $selectedServiceGroupIds);
        }
        $serviceGroupsToDisplay = $serviceGroupsQuery->orderBy('name')->get();

        $auditedRecordsQuery = AuditedPatientRecord::with([
                'patient:id,name,insurance_no,company_id',
                'doctorVisit:id,visit_date',
                'auditedRequestedServices.service.serviceGroup',
            ])
            ->whereHas('patient', fn ($q) => $q->where('company_id', $company->id))
            ->whereHas('doctorVisit', fn ($q) => $q->whereBetween('visit_date', [$startDate, $endDate]));

        $auditStatusFilter = $request->filled('audit_status') ? $validated['audit_status'] : 'verified';
        $auditedRecordsQuery->where('status', $auditStatusFilter);
        
        $auditedPatientRecords = $auditedRecordsQuery->orderBy('created_at')->get();

        if ($auditedPatientRecords->isEmpty()) {
            return response()->json(['message' => 'لا توجد سجلات تدقيق (' . $auditStatusFilter . ') لتصديرها.'], 404);
        }

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setRightToLeft(true);
            // ... (Spreadsheet properties and styles setup - same as before) ...
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

            $sheet->mergeCells('A1:G1'); 
            $sheet->setCellValue('A1', 'مطالبة شركة: ' . $company->name);
            $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->mergeCells('A2:G2');
            $sheet->setCellValue('A2', 'الفترة: ' . $startDate->format('Y-m-d') . ' - ' . $endDate->format('Y-m-d') . ' (حالة التدقيق: ' . $auditStatusFilter . ')');
            $sheet->getStyle('A2')->getFont()->setSize(12);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(25);
            $sheet->getRowDimension(2)->setRowHeight(20);
            

            $startRow = 4;
            $columnsConfig = [
                ['header' => 'م', 'width' => 5, 'data_key' => 'sequence'],
                ['header' => 'رقم الزيارة', 'width' => 12, 'data_key' => 'visit_id'],
                ['header' => 'اسم المريض', 'width' => 30, 'data_key' => 'patient_name'],
                ['header' => 'تاريخ الزيارة', 'width' => 15, 'data_key' => 'visit_date'],
                ['header' => 'رقم البطاقة', 'width' => 18, 'data_key' => 'insurance_no'],
            ];
            
            $currentColIndex = 0;
            foreach ($columnsConfig as $col) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currentColIndex + 1);
                $sheet->setCellValue($colLetter . $startRow, $col['header']);
                $sheet->getColumnDimension($colLetter)->setWidth($col['width']);
                $currentColIndex++;
            }

            // Dynamic Service Group columns (will now show NET AUDITED PRICE for the group)
            foreach ($serviceGroupsToDisplay as $sg) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currentColIndex + 1);
                $columnsConfig[] = ['header' => $sg->name, 'width' => 22, 'data_key' => 'sg_net_price_' . $sg->id, 'is_numeric' => true, 'has_comment' => true, 'comment_key' => 'sg_names_' . $sg->id];
                $sheet->setCellValue($colLetter . $startRow, $sg->name);
                $sheet->getColumnDimension($colLetter)->setWidth(22);
                $currentColIndex++;
            }

            // New Static Columns after dynamic groups
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currentColIndex + 1);
            $columnsConfig[] = ['header' => 'إجمالي صافي قيمة الخدمات', 'width' => 22, 'data_key' => 'total_audited_net_price', 'is_numeric' => true];
            $sheet->setCellValue($colLetter . $startRow, 'إجمالي صافي قيمة الخدمات');
            $sheet->getColumnDimension($colLetter)->setWidth(22);
            $currentColIndex++;
            
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currentColIndex + 1);
            $columnsConfig[] = ['header' => 'إجمالي تحمل الشركة (المدقق)', 'width' => 22, 'data_key' => 'total_audited_endurance', 'is_numeric' => true];
            $sheet->setCellValue($colLetter . $startRow, 'إجمالي تحمل الشركة (المدقق)');
            $sheet->getColumnDimension($colLetter)->setWidth(22);
            $currentColIndex++;

            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($currentColIndex + 1);
            $columnsConfig[] = ['header' => 'صافي مستحق على المريض', 'width' => 22, 'data_key' => 'net_patient_responsibility', 'is_numeric' => true];
            $sheet->setCellValue($colLetter . $startRow, 'صافي مستحق على المريض');
            $sheet->getColumnDimension($colLetter)->setWidth(22);
            $lastColumnLetter = $colLetter;

            $sheet->getStyle('A' . $startRow . ':' . $lastColumnLetter . $startRow)->applyFromArray($headerStyle);
            $sheet->getRowDimension($startRow)->setRowHeight(20);

            // --- Populate Data Rows ---
            $dataRowNumber = $startRow + 1;
            foreach ($auditedPatientRecords as $index => $auditRecord) {
                $patientContext = $auditRecord->patient;
                $visitContext = $auditRecord->doctorVisit;

                $rowData = [
                    'sequence' => $index + 1,
                    'visit_id' => $auditRecord->doctor_visit_id,
                    'patient_name' => $auditRecord->edited_patient_name ?? $patientContext?->name ?? '-',
                    'visit_date' => $visitContext?->visit_date ? Carbon::parse($visitContext->visit_date)->format('Y-m-d') : '-',
                    'insurance_no' => $auditRecord->edited_insurance_no ?? $patientContext?->insurance_no ?? '-',
                ];

                $visitTotalAuditedNetPrice = 0;
                $visitTotalAuditedEndurance = 0;

                foreach ($serviceGroupsToDisplay as $sg) {
                    $sgNetPrice = 0;
                    $sgServiceNames = [];
                    foreach ($auditRecord->auditedRequestedServices as $ars) {
                        if ($ars->service?->service_group_id == $sg->id) {
                            $itemPrice = (float) $ars->audited_price;
                            $itemCount = (int) ($ars->audited_count ?? 1);
                            $itemSubTotal = $itemPrice * $itemCount;
                            $itemDiscountFixed = (float) ($ars->audited_discount_fixed ?? 0);
                            $itemDiscountPer = ($itemSubTotal * (float)($ars->audited_discount_per ?? 0)) / 100;
                            $itemNet = $itemSubTotal - $itemDiscountFixed - $itemDiscountPer;
                            
                            $sgNetPrice += $itemNet;
                            $sgServiceNames[] = $ars->service?->name;
                        }
                    }
                    $rowData['sg_net_price_' . $sg->id] = $sgNetPrice;
                    $rowData['sg_names_' . $sg->id] = implode('، ', array_unique($sgServiceNames));
                    $visitTotalAuditedNetPrice += $sgNetPrice;
                }
                
                // Calculate total endurance for approved items
                foreach ($auditRecord->auditedRequestedServices as $ars) {
                    // if ($ars->audited_status === 'approved_for_claim' ) {
                        $visitTotalAuditedEndurance += (float) $ars->audited_endurance * (int)($ars->audited_count ?? 1);
                    // }
                }
                
                $rowData['total_audited_net_price'] = $visitTotalAuditedNetPrice;
                $rowData['total_audited_endurance'] = $visitTotalAuditedEndurance;
                $rowData['net_patient_responsibility'] = $visitTotalAuditedNetPrice - $visitTotalAuditedEndurance;

                // Write row data
                foreach ($columnsConfig as $colIdx => $colSetup) {
                    $colLetterWrite = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIdx + 1);
                    $cellValue = $rowData[$colSetup['data_key']] ?? ($colSetup['is_numeric'] ?? false ? 0 : '-');
                    if ($colSetup['is_numeric'] ?? false) {
                        $sheet->setCellValue($colLetterWrite . $dataRowNumber, (float)$cellValue);
                        $sheet->getStyle($colLetterWrite . $dataRowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
                    } else {
                        $sheet->setCellValue($colLetterWrite . $dataRowNumber, $cellValue);
                    }
                    if (($colSetup['has_comment'] ?? false) && !empty($rowData[$colSetup['comment_key']])) {
                        $sheet->getComment($colLetterWrite . $dataRowNumber)->getText()->createTextRun($rowData[$colSetup['comment_key']]);
                    }
                }
                $dataRowNumber++;
            }

            // --- Totals Row ---
            $sheet->setCellValue('A' . $dataRowNumber, 'الإجمالي');
            // Summing from the first dynamic group column up to the last calculated column
            $firstSumColIndex = 5; // Index of the first service group column (0-based in $columnsConfig)
            for ($i = $firstSumColIndex; $i < count($columnsConfig); $i++) {
                $colLetterForSum = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i + 1);
                if ($columnsConfig[$i]['is_numeric'] ?? false) {
                     $sheet->setCellValue($colLetterForSum . $dataRowNumber, "=SUM(" . $colLetterForSum . ($startRow + 1) . ":" . $colLetterForSum . ($dataRowNumber - 1) . ")");
                }
            }
            $sheet->getStyle('A' . $dataRowNumber . ':' . $lastColumnLetter . $dataRowNumber)->applyFromArray($totalRowStyle);
            $sheet->getRowDimension($dataRowNumber)->setRowHeight(22);

            // Apply Borders & Alignment to Data
            $dataRange = 'A' . ($startRow + 1) . ':' . $lastColumnLetter . ($dataRowNumber - 1);
            // ... (apply borders and alignment as before) ...
            $sheet->getStyle($dataRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            $sheet->getStyle($dataRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle($dataRange)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $sheet->getStyle($dataRange)->getAlignment()->setWrapText(true);


            $writer = new Xlsx($spreadsheet);
            $fileName = 'audited_insurance_claim_V2_' . $company->name . '_' . date('Ymd_His') . '.xlsx';

            // Clear any output buffers
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
            $writer->save($tempFile);

            // Dispose of the spreadsheet to free memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return response()->streamDownload(function () use ($tempFile) {
                if (file_exists($tempFile)) {
                    readfile($tempFile);
                    unlink($tempFile); // Clean up temp file
                }
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);

        } catch (\Exception $e) {
            Log::error("Audited Excel V2 Export Error for company {$company->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['message' => 'حدث خطأ أثناء إنشاء ملف الإكسل المدقق.', 'error' => $e->getMessage()], 500);
        }
    }
    private function getMonthlyServiceDepositsIncomeData(Request $request): array
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:' . (date('Y') + 5),
            // 'user_id' => 'nullable|integer|exists:users,id', // Optional filter
            'show_empty_days' => 'nullable|boolean', // For PDF/Excel, you might always want to show all days
        ]);

        $year = $validated['year'];
        $month = $validated['month'];

        $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth();
        $period = CarbonPeriod::create($startDate, '1 day', $endDate);

        $dailyData = [];
        $grandTotals = [
            'total_deposits' => 0,
            'total_cash_deposits' => 0,
            'total_bank_deposits' => 0,
            'total_costs_for_days_with_activity' => 0, // Costs on days that had deposits OR costs
        ];

        $allDepositsForMonth = RequestedServiceDeposit::whereBetween('created_at', [$startDate, $endDate])
            // ->when($request->filled('user_id'), fn($q) => $q->where('user_id', $request->user_id))
            ->get();

        $allCostsForMonth = Cost::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            // ->when($request->filled('user_id'), fn($q) => $q->where('user_cost', $request->user_id))
            ->get();

        foreach ($period as $date) {
            $currentDateStr = $date->format('Y-m-d');
            
            $depositsOnThisDay = $allDepositsForMonth->filter(fn ($d) => Carbon::parse($d->created_at)->isSameDay($date));
            $costsOnThisDay = $allCostsForMonth->filter(fn ($c) => Carbon::parse($c->created_at)->isSameDay($date));

            if ($depositsOnThisDay->isEmpty() && $costsOnThisDay->isEmpty() && !$request->input('show_empty_days', true)) { // Default to true for reports
                continue;
            }

            $dailyTotalDeposits = $depositsOnThisDay->sum('amount');
            $dailyCashDeposits = $depositsOnThisDay->where('is_bank', false)->sum('amount');
            $dailyBankDeposits = $depositsOnThisDay->where('is_bank', true)->sum('amount');
            
            $dailyCashCosts = $costsOnThisDay->sum('amount');
            $dailyBankCosts = $costsOnThisDay->sum('amount_bankak');
            $dailyTotalCosts = $dailyCashCosts + $dailyBankCosts;

            $dailyData[] = [
                'date_obj' => $date, // Keep Carbon instance for PDF formatting
                'date' => $currentDateStr,
                'total_income' => (float) $dailyTotalDeposits,
                'total_cash_income' => (float) $dailyCashDeposits,
                'total_bank_income' => (float) $dailyBankDeposits,
                'total_cost' => (float) $dailyTotalCosts,
                'net_cash' => (float) ($dailyCashDeposits - $dailyCashCosts),
                'net_bank' => (float) ($dailyBankDeposits - $dailyBankCosts),
                'net_income_for_day' => (float) ($dailyTotalDeposits - $dailyTotalCosts),
            ];

            $grandTotals['total_deposits'] += $dailyTotalDeposits;
            $grandTotals['total_cash_deposits'] += $dailyCashDeposits;
            $grandTotals['total_bank_deposits'] += $dailyBankDeposits;
            $grandTotals['total_costs_for_days_with_activity'] += $dailyTotalCosts;
        }
        
        $grandTotals['net_total_income'] = $grandTotals['total_deposits'] - $grandTotals['total_costs_for_days_with_activity'];
        $grandTotals['net_cash_flow'] = $grandTotals['total_cash_deposits'] - $allCostsForMonth->sum('amount');
        $grandTotals['net_bank_flow'] = $grandTotals['total_bank_deposits'] - $allCostsForMonth->sum('amount_bankak');

        return [
            'daily_data' => $dailyData,
            'summary' => $grandTotals,
            'report_period' => [
                'month_name' => $startDate->translatedFormat('F Y'),
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ]
        ];
    }

    public function exportMonthlyServiceDepositsIncomeExcel(Request $request)
    {
        // $this->authorize('export monthly_service_income_report');
        $data = $this->getMonthlyServiceDepositsIncomeData(new Request($request->all() + ['show_empty_days' => true]));
        
        $dailyData = $data['daily_data'];
        $summary = $data['summary'];
        $reportPeriod = $data['report_period'];

        if (empty($dailyData)) {
            return response()->json(['message' => 'لا توجد بيانات لإنشاء التقرير.'], 404);
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);

        $spreadsheet->getProperties()->setTitle('تقرير الإيرادات الشهرية من الخدمات');
        
        $headerStyle = [ /* Same as ExcelController */];
        $totalRowStyle = [ /* Same as ExcelController */];
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

        $sheet->setCellValue('A1', 'تقرير الإيرادات الشهرية من الخدمات');
        $sheet->mergeCells('A1:H1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->setCellValue('A2', "لشهر: {$reportPeriod['month_name']}");
        $sheet->mergeCells('A2:H2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $headers = ['التاريخ', 'إجمالي الإيداعات', 'إيداعات نقدية', 'إيداعات بنكية', 'إجمالي المصروفات', 'صافي النقدية', 'صافي البنك', 'صافي الدخل اليومي'];
        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle('A4:H4')->applyFromArray($headerStyle);
        $sheet->getRowDimension(4)->setRowHeight(20);

        $dataRows = [];
        foreach ($dailyData as $day) {
            $dataRows[] = [
                Carbon::parse($day['date'])->translatedFormat('D, M j, Y'),
                (float)$day['total_income'],
                (float)$day['total_cash_income'],
                (float)$day['total_bank_income'],
                (float)$day['total_cost'],
                (float)$day['net_cash'],
                (float)$day['net_bank'],
                (float)$day['net_income_for_day'],
            ];
        }
        $sheet->fromArray($dataRows, null, 'A5');
        
        $lastDataRow = 4 + count($dataRows);
        
        // Column widths and number formats
        $sheet->getColumnDimension('A')->setWidth(25);
        for ($col = 'B'; $col <= 'H'; $col++) {
            $sheet->getColumnDimension($col)->setWidth(18);
            $sheet->getStyle($col . '5:' . $col . $lastDataRow)->getNumberFormat()->setFormatCode('#,##0.00');
        }

        // Totals Row
        $totalRowIdx = $lastDataRow + 1;
        $sheet->setCellValue('A' . $totalRowIdx, 'الإجمالي الشهري:');
        $sheet->setCellValue('B' . $totalRowIdx, "=SUM(B5:B{$lastDataRow})");
        $sheet->setCellValue('C' . $totalRowIdx, "=SUM(C5:C{$lastDataRow})");
        $sheet->setCellValue('D' . $totalRowIdx, "=SUM(D5:D{$lastDataRow})");
        $sheet->setCellValue('E' . $totalRowIdx, "=SUM(E5:E{$lastDataRow})");
        $sheet->setCellValue('F' . $totalRowIdx, "=SUM(F5:F{$lastDataRow})");
        $sheet->setCellValue('G' . $totalRowIdx, "=SUM(G5:G{$lastDataRow})");
        $sheet->setCellValue('H' . $totalRowIdx, "=SUM(H5:H{$lastDataRow})");
        $sheet->getStyle('A' . $totalRowIdx . ':H' . $totalRowIdx)->applyFromArray($totalRowStyle);
        $sheet->getRowDimension($totalRowIdx)->setRowHeight(22);
        
        // Borders for data
        $sheet->getStyle('A5:H'.$lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle('A5:H'.$lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $writer = new Xlsx($spreadsheet);
        $fileName = 'monthly_service_income_' . $reportPeriod['from'] . '_' . $reportPeriod['to'] . '.xlsx';
        
        // Clear any output buffers
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Create temporary file
        $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
        $writer->save($tempFile);

        // Dispose of the spreadsheet to free memory
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->streamDownload(function () use ($tempFile) {
            if (file_exists($tempFile)) {
                readfile($tempFile);
                unlink($tempFile); // Clean up temp file
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
    }
    /**
     * Export the list of services to an Excel file.
     */
    public function exportServicesListToExcel(Request $request)
    {
        // Add permission check if needed:
        // if (!Auth::user()->can('export services_list')) { /* ... */ }

        // Reuse filtering logic from ServiceController@index if possible
        $request->validate([
            'search' => 'nullable|string|max:255',
            // Add any other filters your frontend list page uses
        ]);

        $query = Service::with('serviceGroup:id,name')->orderBy('name');

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }


        $services = $query->get();
        
        if ($services->isEmpty()) {
            // It's better to return an empty Excel sheet with headers than a 404
            // return response()->json(['message' => 'No services found to export.'], 404);
        }

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $isRTL = app()->getLocale() === 'ar'; // Check for RTL
            $sheet->setRightToLeft($isRTL);

            // --- Header & Title ---
            $sheet->mergeCells('A1:F1');
            $sheet->setCellValue('A1', 'قائمة الخدمات الطبية');
            $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(25);

            // --- Column Headers ---
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']], // Indigo
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
            ];

            $headers = [
                'ID',
                'اسم الخدمة',
                'مجموعة الخدمة',
                'السعر',
                'الحالة',
                'متغيرة السعر؟'
            ];
            
            $sheet->fromArray($headers, null, 'A3');
            $sheet->getStyle('A3:F3')->applyFromArray($headerStyle);
            $sheet->getRowDimension(3)->setRowHeight(18);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(8);
            $sheet->getColumnDimension('B')->setWidth(40);
            $sheet->getColumnDimension('C')->setWidth(25);
            $sheet->getColumnDimension('D')->setWidth(15);
            $sheet->getColumnDimension('E')->setWidth(15);
            $sheet->getColumnDimension('F')->setWidth(18);
            
            // --- Data Rows ---
            $dataRowNumber = 4;
            foreach ($services as $service) {
                $sheet->setCellValue('A' . $dataRowNumber, $service->id);
                $sheet->setCellValue('B' . $dataRowNumber, $service->name);
                $sheet->setCellValue('C' . $dataRowNumber, $service->serviceGroup?->name ?? 'N/A');
                $sheet->setCellValue('D' . $dataRowNumber, (float)$service->price);
                $sheet->setCellValue('E' . $dataRowNumber, $service->activate ? 'نشطة' : 'غير نشطة');
                $sheet->setCellValue('F' . $dataRowNumber, $service->variable ? 'نعم' : 'لا');
                $dataRowNumber++;
            }
            
            // Apply styles to data rows
            $lastDataRow = $dataRowNumber - 1;
            if ($lastDataRow >= 4) {
                $sheet->getStyle('D4:D'.$lastDataRow)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('A4:F'.$lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B4:C'.$lastDataRow)->getAlignment()->setHorizontal($isRTL ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('A4:F'.$lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('E0E0E0'));
            }

            $writer = new Xlsx($spreadsheet);
            $fileName = 'Services_List_' . date('Y-m-d') . '.xlsx';

            // Clear any output buffers
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
            $writer->save($tempFile);

            // Dispose of the spreadsheet to free memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return response()->streamDownload(function () use ($tempFile) {
                if (file_exists($tempFile)) {
                    readfile($tempFile);
                    unlink($tempFile); // Clean up temp file
                }
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);

        } catch (\Exception $e) {
            Log::error("Services List Excel Export Error: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while generating the Excel file.'], 500);
        }
    }
    /**
     * Export a detailed list of services and their associated cost definitions to Excel.
     */
    public function exportServicesWithCostsToExcel(Request $request)
    {
        // Add permission check if needed:
        // if (!Auth::user()->can('export_service_costs_report')) { /* ... */ }

        try {
            // Fetch only services that HAVE associated costs. Eager load for performance.
            $servicesWithCosts = Service::whereHas('serviceCosts')
                ->with(['serviceGroup:id,name', 'serviceCosts.subServiceCost:id,name'])
                ->orderBy('name')
                ->get();

            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $isRTL = app()->getLocale() === 'ar';
            $sheet->setRightToLeft($isRTL);

            // --- Header & Title ---
            $sheet->mergeCells('A1:G1');
            $sheet->setCellValue('A1', 'تقرير تفصيل تكاليف الخدمات المعرفة');
            $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(25);

            // --- Column Headers ---
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1E40AF']], // Darker Blue
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
            ];

            $headers = [
                'الخدمة الرئيسية',
                'المجموعة',
                'اسم تعريف التكلفة',
                'نوع التكلفة (المكون)',
                'أساس الحساب',
                'النسبة المئوية (%)',
                'المبلغ الثابت',
            ];
            
            $sheet->fromArray($headers, null, 'A3');
            $sheet->getStyle('A3:G3')->applyFromArray($headerStyle);
            $sheet->getRowDimension(3)->setRowHeight(18);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(35);
            $sheet->getColumnDimension('B')->setWidth(20);
            $sheet->getColumnDimension('C')->setWidth(30);
            $sheet->getColumnDimension('D')->setWidth(25);
            $sheet->getColumnDimension('E')->setWidth(20);
            $sheet->getColumnDimension('F')->setWidth(18);
            $sheet->getColumnDimension('G')->setWidth(18);
            
            // --- Data Rows ---
            $dataRowNumber = 4;
            foreach ($servicesWithCosts as $service) {
                // For the first cost of a service, we print the service name.
                // For subsequent costs of the same service, we leave the first columns blank.
                $isFirstCostForRow = true;
                foreach ($service->serviceCosts as $cost) {
                    if ($isFirstCostForRow) {
                        $sheet->setCellValue('A' . $dataRowNumber, $service->name);
                        $sheet->setCellValue('B' . $dataRowNumber, $service->serviceGroup?->name ?? 'N/A');
                    } else {
                        // Merge cells for subsequent rows of the same service for better readability
                        // This can get complex, so for now we just leave them blank
                        $sheet->setCellValue('A' . $dataRowNumber, '');
                        $sheet->setCellValue('B' . $dataRowNumber, '');
                    }

                    $sheet->setCellValue('C' . $dataRowNumber, $cost->name);
                    $sheet->setCellValue('D' . $dataRowNumber, $cost->subServiceCost?->name ?? 'N/A');
                    $sheet->setCellValue('E' . $dataRowNumber, $cost->cost_type === 'total' ? 'من الإجمالي' : 'بعد المصروفات الأخرى');
                    $sheet->setCellValue('F' . $dataRowNumber, $cost->percentage ? (float)$cost->percentage : '-');
                    $sheet->setCellValue('G' . $dataRowNumber, $cost->fixed ? (float)$cost->fixed : '-');
                    
                    $dataRowNumber++;
                    $isFirstCostForRow = false;
                }
                 // Add a separator row after each service block
                if($servicesWithCosts->last()->id !== $service->id){
                    $sheet->mergeCells('A'.$dataRowNumber.':G'.$dataRowNumber);
                    $sheet->getStyle('A'.$dataRowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('E5E7EB');
                    $dataRowNumber++;
                }
            }
            
            // Apply styles to data rows
            $lastDataRow = $dataRowNumber - 1;
            if ($lastDataRow >= 4) {
                $sheet->getStyle('F4:G'.$lastDataRow)->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle('A4:G'.$lastDataRow)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getStyle('A4:D'.$lastDataRow)->getAlignment()->setHorizontal($isRTL ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('E4:G'.$lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('A4:G'.$lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('E0E0E0'));
            }

            $writer = new Xlsx($spreadsheet);
            $fileName = 'Services_With_Cost_Details_' . date('Y-m-d') . '.xlsx';

            return response()->streamDownload(function () use ($writer) {
                if (ob_get_level() > 0) ob_end_clean();
                $writer->save('php://output');
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (\Exception $e) {
            Log::error("Services With Costs Excel Export Error: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while generating the Excel file.'], 500);
        }
    }

    /**
     * Export doctor shifts report to Excel.
     */
    public function doctorShiftsReportExcel(Request $request)
    {
        // if (!Auth::user()->can('print doctor_shift_reports')) { /* ... */ }

        try {
            $doctorShiftsReport = new \App\Services\Excel\DoctorShiftsReport();
            $excelContent = $doctorShiftsReport->generate($request);
            
            $excelFileName = 'Doctor_Shifts_Report_' . date('Ymd_His') . '.xlsx';
            return response($excelContent, 200)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', "attachment; filename=\"{$excelFileName}\"");
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * Export specialist shifts report to Excel (grouped by specialist).
     */
    public function specialistShiftsReportExcel(Request $request)
    {
        // if (!Auth::user()->can('print specialist_shift_reports')) { /* ... */ }

        try {
            $specialistShiftsReport = new \App\Services\Excel\SpecialistShiftsReport();
            $includeBreakdown = $request->get('include_breakdown', 'true') === 'true';
            $excelContent = $specialistShiftsReport->generate($request, $includeBreakdown);
            
            $fileNameSuffix = $includeBreakdown ? 'With_Breakdown' : 'Summary_Only';
            $excelFileName = 'Specialist_Shifts_Report_' . $fileNameSuffix . '_' . date('Ymd_His') . '.xlsx';
            return response($excelContent, 200)
                ->header('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
                ->header('Content-Disposition', "attachment; filename=\"{$excelFileName}\"");
        } catch (\Exception $e) {
            return response()->json(['message' => $e->getMessage()], 404);
        }
    }

    /**
     * Export costs report to Excel.
     */
    public function exportCostsReportToExcel(Request $request)
    {
        // Permission Check: e.g., can('export cost_report')
        // if (!Auth::user()->can('export cost_report')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        // Validation (same as CostController@index for filters)
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'cost_category_id' => 'nullable|integer|exists:cost_categories,id',
            'user_cost_id' => 'nullable|integer|exists:users,id',
            'shift_id' => 'nullable|integer|exists:shifts,id',
            'payment_method' => 'nullable|string|in:cash,bank,mixed,all',
            'search_description' => 'nullable|string|max:255',
            'sort_by' => 'nullable|string|in:created_at,total_cost,description',
            'sort_direction' => 'nullable|string|in:asc,desc',
        ]);

        // --- Fetch Data (same logic as ReportController@generateCostsReportPdf) ---
        $query = Cost::with(['costCategory:id,name', 'userCost:id,name', 'shift:id']);

        if ($request->filled('date_from')) {
            $from = Carbon::parse($request->date_from)->startOfDay();
            $query->whereDate('created_at', '>=', $from);
        }
        if ($request->filled('date_to')) {
            $to = Carbon::parse($request->date_to)->endOfDay();
            $query->whereDate('created_at', '<=', $to);
        }
        if ($request->filled('cost_category_id')) {
            $query->where('cost_category_id', $request->cost_category_id);
        }
        if ($request->filled('user_cost_id')) {
            $query->where('user_cost', $request->user_cost_id);
        }
        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }
        if ($request->filled('payment_method') && $request->payment_method !== 'all') {
            $method = $request->payment_method;
            if ($method === 'cash') {
                $query->where('amount', '>', 0)->where('amount_bankak', '=', 0);
            } elseif ($method === 'bank') {
                $query->where('amount_bankak', '>', 0)->where('amount', '=', 0);
            } elseif ($method === 'mixed') {
                $query->where('amount', '>', 0)->where('amount_bankak', '>', 0);
            }
        }
        if ($request->filled('search_description')) {
            $query->where('description', 'LIKE', '%' . $request->search_description . '%');
        }

        $sortBy = $request->input('sort_by', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        if ($sortBy === 'total_cost') {
            $query->orderByRaw('(amount + amount_bankak) ' . $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }
        $costs = $query->get();

        if ($costs->isEmpty()) {
            return response()->json(['message' => 'No cost data found for the selected filters to generate Excel.'], 404);
        }

        // Calculate Summary Totals
        $totalCashPaid = $costs->sum('amount');
        $totalBankPaid = $costs->sum('amount_bankak');
        $grandTotalPaid = $costs->sum(fn($cost) => $cost->amount + $cost->amount_bankak);

        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $isRTL = app()->getLocale() === 'ar';
            $sheet->setRightToLeft($isRTL);

            // --- Header & Title ---
            $sheet->mergeCells('A1:H1');
            $sheet->setCellValue('A1', 'تقرير المصروفات');
            $sheet->getStyle('A1')->getFont()->setSize(16)->setBold(true);
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(25);

            // --- Summary Section ---
            $sheet->mergeCells('A2:H2');
            $sheet->setCellValue('A2', 'ملخص المصروفات الإجمالي للفترة المحددة');
            $sheet->getStyle('A2')->getFont()->setSize(12)->setBold(true);
            $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension(2)->setRowHeight(20);

            // Summary row
            $sheet->setCellValue('A3', 'إجمالي المصروفات النقدية:');
            $sheet->setCellValue('B3', $totalCashPaid);
            $sheet->setCellValue('C3', 'إجمالي المصروفات البنكية:');
            $sheet->setCellValue('D3', $totalBankPaid);
            $sheet->setCellValue('E3', 'إجمالي المصروفات الكلي:');
            $sheet->setCellValue('F3', $grandTotalPaid);
            $sheet->getStyle('B3')->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('D3')->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('F3')->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('A3:F3')->getFont()->setBold(true);
            $sheet->getRowDimension(3)->setRowHeight(18);

            // --- Column Headers ---
            $headerStyle = [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
            ];

            $headers = [
                'التاريخ',
                'الوصف',
                'الفئة',
                'المستخدم',
                'طريقة الدفع',
                'نقداً',
                'بنك/شبكة',
                'الإجمالي'
            ];
            
            $sheet->fromArray($headers, null, 'A5');
            $sheet->getStyle('A5:H5')->applyFromArray($headerStyle);
            $sheet->getRowDimension(5)->setRowHeight(18);

            // Set column widths
            $sheet->getColumnDimension('A')->setWidth(20);  // التاريخ
            $sheet->getColumnDimension('B')->setWidth(40);  // الوصف
            $sheet->getColumnDimension('C')->setWidth(20);  // الفئة
            $sheet->getColumnDimension('D')->setWidth(20);  // المستخدم
            $sheet->getColumnDimension('E')->setWidth(15); // طريقة الدفع
            $sheet->getColumnDimension('F')->setWidth(15); // نقداً
            $sheet->getColumnDimension('G')->setWidth(15); // بنك/شبكة
            $sheet->getColumnDimension('H')->setWidth(15); // الإجمالي

            // --- Data Rows ---
            $dataRowNumber = 6;
            foreach ($costs as $cost) {
                $totalCostForRow = $cost->amount + $cost->amount_bankak;
                $paymentMethodDisplay = '-';
                if ($cost->amount > 0 && $cost->amount_bankak > 0) {
                    $paymentMethodDisplay = 'مختلط';
                } elseif ($cost->amount > 0) {
                    $paymentMethodDisplay = 'نقداً';
                } elseif ($cost->amount_bankak > 0) {
                    $paymentMethodDisplay = 'بنك';
                }

                $sheet->setCellValue('A' . $dataRowNumber, Carbon::parse($cost->created_at)->format('Y-m-d H:i'));
                $sheet->setCellValue('B' . $dataRowNumber, $cost->description);
                $sheet->setCellValue('C' . $dataRowNumber, $cost->costCategory?->name ?? '-');
                $sheet->setCellValue('D' . $dataRowNumber, $cost->userCost?->name ?? '-');
                $sheet->setCellValue('E' . $dataRowNumber, $paymentMethodDisplay);
                $sheet->setCellValue('F' . $dataRowNumber, (float)$cost->amount);
                $sheet->setCellValue('G' . $dataRowNumber, (float)$cost->amount_bankak);
                $sheet->setCellValue('H' . $dataRowNumber, (float)$totalCostForRow);
                $dataRowNumber++;
            }

            // Apply styles to data rows
            $lastDataRow = $dataRowNumber - 1;
            if ($lastDataRow >= 6) {
                // Number format for currency columns
                $sheet->getStyle('F6:H' . $lastDataRow)->getNumberFormat()->setFormatCode('#,##0.00');
                // Alignment
                $sheet->getStyle('A6:A' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('B6:D' . $lastDataRow)->getAlignment()->setHorizontal($isRTL ? Alignment::HORIZONTAL_RIGHT : Alignment::HORIZONTAL_LEFT);
                $sheet->getStyle('E6:H' . $lastDataRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Borders
                $sheet->getStyle('A6:H' . $lastDataRow)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('E0E0E0'));
            }

            // Grand Total Row
            $sheet->setCellValue('A' . $dataRowNumber, 'الإجمالي');
            $sheet->setCellValue('F' . $dataRowNumber, $totalCashPaid);
            $sheet->setCellValue('G' . $dataRowNumber, $totalBankPaid);
            $sheet->setCellValue('H' . $dataRowNumber, $grandTotalPaid);
            $sheet->getStyle('A' . $dataRowNumber . ':H' . $dataRowNumber)->getFont()->setBold(true);
            $sheet->getStyle('F' . $dataRowNumber . ':H' . $dataRowNumber)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle('A' . $dataRowNumber . ':H' . $dataRowNumber)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E0E0E0');
            $sheet->getStyle('A' . $dataRowNumber . ':H' . $dataRowNumber)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

            $writer = new Xlsx($spreadsheet);
            $fileName = 'Costs_Report_' . ($request->date_from ?? 'all') . '_to_' . ($request->date_to ?? 'all') . '.xlsx';

            // Clear any output buffers
            if (ob_get_level()) {
                ob_end_clean();
            }

            // Create temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
            $writer->save($tempFile);

            // Dispose of the spreadsheet to free memory
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return response()->streamDownload(function () use ($tempFile) {
                if (file_exists($tempFile)) {
                    readfile($tempFile);
                    unlink($tempFile);
                }
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            ]);

        } catch (\Exception $e) {
            Log::error("Costs Report Excel Export Error: " . $e->getMessage());
            return response()->json(['message' => 'An error occurred while generating the Excel file.'], 500);
        }
    }
}