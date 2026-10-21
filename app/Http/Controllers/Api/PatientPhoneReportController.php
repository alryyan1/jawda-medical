<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Patient;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PatientPhoneReportController extends Controller
{
    /**
     * Whitelisted patient columns selectable for this report, keyed by
     * column identifier with their Arabic display label.
     *
     * @var array<string, string>
     */
    private const COLUMNS = [
        'id' => 'رقم المريض',
        'name' => 'اسم المريض',
        'phone' => 'رقم الهاتف',
        'gender' => 'النوع',
        'age' => 'العمر',
        'address' => 'العنوان',
        'gov_id' => 'الرقم الوطني',
        'insurance_no' => 'رقم التأمين',
        'company_name' => 'الشركة',
        'doctor_name' => 'الطبيب',
        'created_at' => 'تاريخ التسجيل',
        'visit_date' => 'تاريخ الزيارة',
    ];

    /**
     * Report of patients with their phone numbers, filterable by
     * registration date range, gender, name, phone and company, with a
     * user-selectable subset of patient columns.
     */
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'gender' => ['nullable', Rule::in(['male', 'female', 'other'])],
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'company_id' => 'nullable|integer|exists:companies,id',
            'columns' => 'nullable|string',
        ]);

        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();
        $columns = $this->resolveSelectedColumns($validated['columns'] ?? null);

        $patients = Patient::query()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($validated['gender'] ?? null, fn ($query, $gender) => $query->where('gender', $gender))
            ->when($validated['name'] ?? null, fn ($query, $name) => $query->where('name', 'like', "%{$name}%"))
            ->when($validated['phone'] ?? null, fn ($query, $phone) => $query->where('phone', 'like', "%{$phone}%"))
            ->when($validated['company_id'] ?? null, fn ($query, $companyId) => $query->where('company_id', $companyId))
            ->with(['company:id,name', 'primaryDoctor:id,name', 'doctorVisit:id,patient_id,visit_date'])
            ->orderByDesc('created_at')
            ->get();

        $items = $patients->map(fn (Patient $patient): array => $this->mapPatient($patient, $columns));

        return response()->json([
            'data' => $items->values(),
            'columns' => array_map(fn (string $key): array => ['key' => $key, 'label' => self::COLUMNS[$key]], $columns),
            'total' => $items->count(),
            'report_period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * Excel export of the patient phones report.
     */
    public function reportExcel(Request $request): StreamedResponse
    {
        $jsonResponse = $this->report($request);
        $responseData = json_decode($jsonResponse->getContent(), true);

        $reportData = $responseData['data'] ?? [];
        $reportColumns = $responseData['columns'] ?? [];
        $reportPeriod = $responseData['report_period'] ?? [
            'from' => $request->date_from,
            'to' => $request->date_to,
        ];

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $spreadsheet->getProperties()->setTitle('تقرير أرقام هواتف المرضى');

        $columnCount = max(1, count($reportColumns));
        $lastColumnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);

        $sheet->mergeCells("A1:{$lastColumnLetter}1");
        $sheet->setCellValue('A1', 'تقرير أرقام هواتف المرضى');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $sheet->mergeCells("A2:{$lastColumnLetter}2");
        $sheet->setCellValue('A2', 'الفترة من: '.$reportPeriod['from'].' إلى: '.$reportPeriod['to']);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $headerRow = 4;
        $headers = array_map(fn (array $column): string => $column['label'], $reportColumns);
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:{$lastColumnLetter}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        foreach (array_keys($reportColumns) as $index) {
            $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
            $sheet->getColumnDimension($columnLetter)->setWidth(22);
        }

        $dataStart = $headerRow + 1;
        $rowIdx = $dataStart;
        foreach ($reportData as $row) {
            foreach (array_values($reportColumns) as $index => $column) {
                $columnLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                $sheet->setCellValue("{$columnLetter}{$rowIdx}", $row[$column['key']] ?? '-');
            }
            $rowIdx++;
        }
        $lastDataRow = $rowIdx - 1;

        if ($lastDataRow >= $dataStart) {
            $sheet->getStyle("A{$dataStart}:{$lastColumnLetter}{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$dataStart}:{$lastColumnLetter}{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('E0E0E0'));
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'patient_phones_report_'.$reportPeriod['from'].'_to_'.$reportPeriod['to'].'.xlsx';

        if (ob_get_level()) {
            ob_end_clean();
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'excel_');
        $writer->save($tempFile);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return response()->streamDownload(function () use ($tempFile): void {
            if (file_exists($tempFile)) {
                readfile($tempFile);
                unlink($tempFile);
            }
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Resolve the user-selected, comma-separated column list against the
     * whitelist, falling back to a sensible default selection.
     *
     * @return array<int, string>
     */
    private function resolveSelectedColumns(?string $columnsParam): array
    {
        $default = ['name', 'phone', 'gender', 'created_at'];

        if (! $columnsParam) {
            return $default;
        }

        $requested = array_filter(array_map('trim', explode(',', $columnsParam)));
        $valid = array_values(array_intersect($requested, array_keys(self::COLUMNS)));

        return $valid ?: $default;
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, string>
     */
    private function mapPatient(Patient $patient, array $columns): array
    {
        $row = ['id' => $patient->id];

        foreach ($columns as $column) {
            $row[$column] = $this->columnValue($patient, $column);
        }

        return $row;
    }

    private function columnValue(Patient $patient, string $column): string
    {
        return match ($column) {
            'id' => (string) $patient->id,
            'name' => (string) $patient->name,
            'phone' => (string) $patient->phone,
            'gender' => match ($patient->gender) {
                'male' => 'ذكر',
                'female' => 'أنثى',
                default => $patient->gender ?: '-',
            },
            'age' => $patient->full_age,
            'address' => $patient->address ?: '-',
            'gov_id' => $patient->gov_id ?: '-',
            'insurance_no' => $patient->insurance_no ?: '-',
            'company_name' => $patient->company?->name ?: '-',
            'doctor_name' => $patient->primaryDoctor?->name ?: '-',
            'created_at' => $patient->created_at?->toDateString() ?: '-',
            'visit_date' => $patient->doctorVisit?->visit_date?->toDateString() ?: '-',
            default => '-',
        };
    }
}
