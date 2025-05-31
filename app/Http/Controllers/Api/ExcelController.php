<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\ServiceGroup;
use App\Models\AuditedPatientRecord;
use App\Models\AuditedRequestedService;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class ExcelController extends Controller
{
    // ... (constructor) ...

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

            return response()->streamDownload(function () use ($writer) {
                $writer->save('php://output');
            }, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (\Exception $e) {
            Log::error("Audited Excel V2 Export Error for company {$company->id}: " . $e->getMessage() . "\n" . $e->getTraceAsString());
            return response()->json(['message' => 'حدث خطأ أثناء إنشاء ملف الإكسل المدقق.', 'error' => $e->getMessage()], 500);
        }
    }
}