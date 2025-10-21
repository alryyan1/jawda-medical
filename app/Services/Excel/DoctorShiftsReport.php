<?php

namespace App\Services\Excel;

use App\Models\DoctorShift;
use App\Models\Doctor;
use App\Models\User;
use App\Models\Shift;
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

class DoctorShiftsReport
{
    public function generate(Request $request)
    {
        // Get the same data as the PDF report
        $query = DoctorShift::with(['doctor', 'user', 'generalShift', 'doctor.specialist']);

        // Apply filters
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->filled('doctor_id')) {
            $query->where('doctor_id', $request->doctor_id);
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

        $doctorShifts = $query->get();

        // Create new spreadsheet
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Doctor Shifts Report');

        // Document properties
        $spreadsheet->getProperties()
            ->setCreator('Jawda Medical')
            ->setLastModifiedBy('Jawda Medical')
            ->setTitle('Doctor Shifts Report')
            ->setSubject('Doctor Shifts Report')
            ->setDescription('A professionally formatted report of doctor shifts including financials.');

        // Sheet settings
        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');

        // Set headers
        $headers = [
            'A1' => 'تاريخ الإنشاء',
            'B1' => 'التخصص',
            'C1' => 'الطبيب',
            'D1' => 'إجمالي الإيراد',
            'E1' => 'التحمل',
            'F1' => 'استحقاق (كاش)',
            'G1' => 'استحقاق (تأمين)',
            'H1' => 'إجمالي المستحق',
            'I1' => 'الموظف'
        ];

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

        $sheet->getStyle('A1:I1')->applyFromArray($headerStyle);

        // Set data rows
        $row = 2;
        $totalIncome = 0;
        $totalEndurance = 0;
        $totalCashEntitlement = 0;
        $totalInsuranceEntitlement = 0;
        $totalDoctorEntitlement = 0;

        foreach ($doctorShifts as $shift) {
            $cashEntitlement = $shift->doctor_credit_cash();
            $insuranceEntitlement = $shift->doctor_credit_company();
            $staticWageApplied = ($shift->status == false && $shift->doctor) ? (float)$shift->doctor->static_wage : 0;
            $totalDoctorEntitlementValue = $cashEntitlement + $insuranceEntitlement + $staticWageApplied;
            $totalIncomeValue = $shift->total_services();
            $enduranceValue = $shift->clinic_enurance();

            // Date as Excel serial with proper format
            if ($shift->created_at) {
                $sheet->setCellValue('A' . $row, ExcelDate::PHPToExcel($shift->created_at));
            } else {
                $sheet->setCellValue('A' . $row, '');
            }
            $sheet->setCellValue('B' . $row, $shift->doctor?->specialist?->name ?? '');
            $sheet->setCellValue('C' . $row, $shift->doctor?->name ?? '');
            $sheet->setCellValue('D' . $row, $totalIncomeValue);
            $sheet->setCellValue('E' . $row, $enduranceValue);
            $sheet->setCellValue('F' . $row, $cashEntitlement);
            $sheet->setCellValue('G' . $row, $insuranceEntitlement);
            $sheet->setCellValue('H' . $row, $totalDoctorEntitlementValue);
            $sheet->setCellValue('I' . $row, $shift->user?->name ?? '');

            // Add to totals
            $totalIncome += $totalIncomeValue;
            $totalEndurance += $enduranceValue;
            $totalCashEntitlement += $cashEntitlement;
            $totalInsuranceEntitlement += $insuranceEntitlement;
            $totalDoctorEntitlement += $totalDoctorEntitlementValue;

            $row++;
        }

        // Add totals row
        $totalsRow = $row;
        $sheet->setCellValue('A' . $totalsRow, 'المجموع');
        $sheet->setCellValue('B' . $totalsRow, '');
        $sheet->setCellValue('C' . $totalsRow, '');
        // Use formulas for totals for transparency
        $sheet->setCellValue('D' . $totalsRow, '=SUM(D2:D' . ($row - 1) . ')');
        $sheet->setCellValue('E' . $totalsRow, '=SUM(E2:E' . ($row - 1) . ')');
        $sheet->setCellValue('F' . $totalsRow, '=SUM(F2:F' . ($row - 1) . ')');
        $sheet->setCellValue('G' . $totalsRow, '=SUM(G2:G' . ($row - 1) . ')');
        $sheet->setCellValue('H' . $totalsRow, '=SUM(H2:H' . ($row - 1) . ')');
        $sheet->setCellValue('I' . $totalsRow, '');

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

        $sheet->getStyle('A' . $totalsRow . ':I' . $totalsRow)->applyFromArray($totalsStyle);

        // Style data rows
        $dataStyle = [
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

        if ($row > 2) {
            $dataEndRow = $row - 1;
            $sheet->getStyle('A2:I' . $dataEndRow)->applyFromArray($dataStyle);

            // Number formats
            $sheet->getStyle('A2:A' . $dataEndRow)->getNumberFormat()->setFormatCode('mm/dd/yyyy hh:mm');
            $sheet->getStyle('D2:H' . $totalsRow)->getNumberFormat()->setFormatCode('#,##0.00');

            // Auto filter (exclude totals row)
            $sheet->setAutoFilter('A1:I' . $dataEndRow);

            // Zebra striping for readability
            for ($r = 2; $r <= $dataEndRow; $r++) {
                if ($r % 2 === 0) {
                    $sheet->getStyle('A' . $r . ':I' . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F7F7F7');
                }
            }

            // Conditional formatting: endurance (E) in red if > 0
            $conditional = new Conditional();
            $conditional->setConditionType(Conditional::CONDITION_CELLIS)
                ->setOperatorType(Conditional::OPERATOR_GREATERTHAN)
                ->addCondition('0');
            $conditional->getStyle()->getFont()->getColor()->setARGB(Color::COLOR_RED);
            $conditionalStyles = $sheet->getStyle('E2:E' . $dataEndRow)->getConditionalStyles();
            $conditionalStyles[] = $conditional;
            $sheet->getStyle('E2:E' . $dataEndRow)->setConditionalStyles($conditionalStyles);
        }

        // Auto-size columns
        foreach (range('A', 'I') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        // Page setup for printing/exporting
        $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
        $sheet->getPageSetup()->setFitToWidth(1);
        $sheet->getPageSetup()->setFitToHeight(0);
        $sheet->getHeaderFooter()->setOddFooter('&LGenerated by Jawda Medical&RPage &P of &N');

        // Create writer and return content
        $writer = new Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'doctor_shifts_report');
        $writer->save($tempFile);
        
        $content = file_get_contents($tempFile);
        unlink($tempFile);
        
        return $content;
    }
}
