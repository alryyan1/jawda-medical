<?php

namespace App\Services\Excel;

use App\Models\DoctorShift;
use App\Models\Doctor;
use App\Models\User;
use App\Models\Shift;
use App\Models\Specialist;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use Carbon\Carbon;

class SpecialistShiftsReport
{
    public function generate(Request $request, $includeBreakdown = true)
    {
        // Get the same data as the doctor shifts report
        $query = DoctorShift::with(['doctor', 'user', 'generalShift', 'doctor.specialist']);

        // Apply filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('shift_id')) {
            $query->where('shift_id', $request->shift_id);
        }
        if ($request->filled('user_id_opened')) {
            $query->where('user_id', $request->user_id_opened);
        }
        if ($request->filled('doctor_name_search')) {
            $query->whereHas('doctor', function ($q) use ($request) {
                $q->where('name', 'like', '%' . $request->doctor_name_search . '%');
            });
        }
        if ($request->filled('specialist_id')) {
            $query->whereHas('doctor', function ($q) use ($request) {
                $q->where('specialist_id', $request->specialist_id);
            });
        }

        $doctorShifts = $query->get();

        // Group by specialist
        $groupedBySpecialist = [];
        foreach ($doctorShifts as $shift) {
            $specialistName = $shift->doctor?->specialist?->name ?? 'غير محدد';
            $specialistId = $shift->doctor?->specialist?->id ?? 0;

            if (!isset($groupedBySpecialist[$specialistId])) {
                $groupedBySpecialist[$specialistId] = [
                    'specialist_id' => $specialistId,
                    'specialist_name' => $specialistName,
                    'shifts' => [],
                    'totals' => [
                        'total_income' => 0,
                        'clinic_enurance' => 0,
                        'cash_entitlement' => 0,
                        'insurance_entitlement' => 0,
                        'total_doctor_entitlement' => 0,
                        'shifts_count' => 0,
                    ]
                ];
            }

            $cashEntitlement = $shift->doctor_credit_cash();
            $insuranceEntitlement = $shift->doctor_credit_company();
            $staticWageApplied = ($shift->status == false && $shift->doctor) ? (float)$shift->doctor->static_wage : 0;
            $totalDoctorEntitlementValue = $cashEntitlement + $insuranceEntitlement + $staticWageApplied;
            $totalIncomeValue = $shift->total_paid_services();
            $enduranceValue = $shift->clinic_enurance();

            $groupedBySpecialist[$specialistId]['shifts'][] = [
                'shift' => $shift,
                'cash_entitlement' => $cashEntitlement,
                'insurance_entitlement' => $insuranceEntitlement,
                'total_doctor_entitlement' => $totalDoctorEntitlementValue,
                'total_income' => $totalIncomeValue,
                'endurance' => $enduranceValue,
            ];

            // Add to specialist totals
            $groupedBySpecialist[$specialistId]['totals']['total_income'] += $totalIncomeValue;
            $groupedBySpecialist[$specialistId]['totals']['clinic_enurance'] += $enduranceValue;
            $groupedBySpecialist[$specialistId]['totals']['cash_entitlement'] += $cashEntitlement;
            $groupedBySpecialist[$specialistId]['totals']['insurance_entitlement'] += $insuranceEntitlement;
            $groupedBySpecialist[$specialistId]['totals']['total_doctor_entitlement'] += $totalDoctorEntitlementValue;
            $groupedBySpecialist[$specialistId]['totals']['shifts_count']++;
        }

        // Sort by specialist name
        uasort($groupedBySpecialist, function ($a, $b) {
            return strcmp($a['specialist_name'], $b['specialist_name']);
        });

        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Specialist Shifts Report');

        // Document properties
        $spreadsheet->getProperties()
            ->setCreator('Jawda Medical')
            ->setLastModifiedBy('Jawda Medical')
            ->setTitle('Specialist Shifts Report')
            ->setSubject('Specialist Shifts Report')
            ->setDescription('A professionally formatted report of doctor shifts grouped by specialist.');

        // Sheet settings
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');

        // Set headers
        if ($includeBreakdown) {
            // Headers for summary + breakdown view
            $headers = [
                'A1' => 'التخصص / التاريخ',
                'B1' => 'الطبيب / عدد المناوبات',
                'C1' => 'إجمالي المدفوع',
                'D1' => 'التحمل',
                'E1' => 'استحقاق (كاش)',
                'F1' => 'استحقاق (تأمين)',
                'G1' => 'إجمالي الاستحقاق',
            ];
        } else {
            // Headers for summary only view
            $headers = [
                'A1' => 'التخصص',
                'B1' => 'عدد المناوبات',
                'C1' => 'إجمالي المدفوع',
                'D1' => 'التحمل',
                'E1' => 'استحقاق (كاش)',
                'F1' => 'استحقاق (تأمين)',
                'G1' => 'إجمالي الاستحقاق',
            ];
        }

        // Set header values
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }

        // Style headers
        $headerStyle = [
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF']
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];

        $sheet->getStyle('A1:G1')->applyFromArray($headerStyle);

        // Set data rows
        $row = 2;
        $grandTotals = [
            'total_income' => 0,
            'clinic_enurance' => 0,
            'cash_entitlement' => 0,
            'insurance_entitlement' => 0,
            'total_doctor_entitlement' => 0,
            'shifts_count' => 0,
        ];

        // Specialist summary style
        $specialistSummaryStyle = [
            'font' => [
                'bold' => true,
                'size' => 11
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D9E1F2']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];

        // Doctor shift detail style
        $detailStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ]
        ];

        foreach ($groupedBySpecialist as $specialistData) {
            $specialistRow = $row;
            
            // Specialist summary row
            $sheet->setCellValue('A' . $row, $specialistData['specialist_name']);
            $sheet->setCellValue('B' . $row, $specialistData['totals']['shifts_count']);
            $sheet->setCellValue('C' . $row, $specialistData['totals']['total_income']);
            $sheet->setCellValue('D' . $row, $specialistData['totals']['clinic_enurance']);
            $sheet->setCellValue('E' . $row, $specialistData['totals']['cash_entitlement']);
            $sheet->setCellValue('F' . $row, $specialistData['totals']['insurance_entitlement']);
            $sheet->setCellValue('G' . $row, $specialistData['totals']['total_doctor_entitlement']);

            // Apply specialist summary style
            $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($specialistSummaryStyle);
            
            // Add to grand totals
            $grandTotals['total_income'] += $specialistData['totals']['total_income'];
            $grandTotals['clinic_enurance'] += $specialistData['totals']['clinic_enurance'];
            $grandTotals['cash_entitlement'] += $specialistData['totals']['cash_entitlement'];
            $grandTotals['insurance_entitlement'] += $specialistData['totals']['insurance_entitlement'];
            $grandTotals['total_doctor_entitlement'] += $specialistData['totals']['total_doctor_entitlement'];
            $grandTotals['shifts_count'] += $specialistData['totals']['shifts_count'];

            $row++;

            // Add detail rows for each doctor shift under this specialist (only if breakdown is requested)
            if ($includeBreakdown) {
                foreach ($specialistData['shifts'] as $shiftData) {
                    $shift = $shiftData['shift'];
                    
                    // Indent detail rows slightly for visual hierarchy
                    $sheet->setCellValue('A' . $row, '  → ' . ($shift->created_at ? Carbon::parse($shift->created_at)->format('Y-m-d H:i') : ''));
                    $sheet->setCellValue('B' . $row, $shift->doctor?->name ?? '');
                    $sheet->setCellValue('C' . $row, $shiftData['total_income']);
                    $sheet->setCellValue('D' . $row, $shiftData['endurance']);
                    $sheet->setCellValue('E' . $row, $shiftData['cash_entitlement']);
                    $sheet->setCellValue('F' . $row, $shiftData['insurance_entitlement']);
                    $sheet->setCellValue('G' . $row, $shiftData['total_doctor_entitlement']);

                    // Apply detail style
                    $sheet->getStyle('A' . $row . ':G' . $row)->applyFromArray($detailStyle);

                    // Zebra striping for detail rows
                    if (($row - $specialistRow) % 2 === 0) {
                        $sheet->getStyle('A' . $row . ':G' . $row)->getFill()
                            ->setFillType(Fill::FILL_SOLID)
                            ->getStartColor()->setRGB('F7F7F7');
                    }

                    $row++;
                }

                // Add empty row between specialists
                $row++;
            }
        }

        // Add grand totals row
        $totalsRow = $row;
        $sheet->setCellValue('A' . $totalsRow, 'المجموع الكلي');
        $sheet->setCellValue('B' . $totalsRow, $grandTotals['shifts_count']);
        $sheet->setCellValue('C' . $totalsRow, $grandTotals['total_income']);
        $sheet->setCellValue('D' . $totalsRow, $grandTotals['clinic_enurance']);
        $sheet->setCellValue('E' . $totalsRow, $grandTotals['cash_entitlement']);
        $sheet->setCellValue('F' . $totalsRow, $grandTotals['insurance_entitlement']);
        $sheet->setCellValue('G' . $totalsRow, $grandTotals['total_doctor_entitlement']);

        // Style totals row
        $totalsStyle = [
            'font' => [
                'bold' => true,
                'size' => 11
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'E7E6E6']
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000']
                ]
            ]
        ];

        $sheet->getStyle('A' . $totalsRow . ':G' . $totalsRow)->applyFromArray($totalsStyle);

        // Number formats
        if ($row > 2) {
            $dataEndRow = $row - 1;
            // Format date column (only for detail rows, not summary rows)
            // We'll need to identify which rows are dates - for now, format all numeric columns
            $sheet->getStyle('C2:G' . $totalsRow)->getNumberFormat()->setFormatCode('#,##0.00');
            
            // Format date cells (detail rows only)
            // This is a simplified approach - in a real scenario, you'd track which rows are dates
        }

        // Auto-size columns
        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Page setup for printing/exporting
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getHeaderFooter()->setOddFooter('&LGenerated by Jawda Medical&RPage &P of &N');

        // Create writer and return content
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'specialist_shifts_report');
        $writer->save($tempFile);
        
        $content = file_get_contents($tempFile);
        unlink($tempFile);
        
        return $content;
    }
}

