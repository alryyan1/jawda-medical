<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\LabRequest;
use App\Services\Pdf\MyCustomTCPDF;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LabRequestDiscountReportController extends Controller
{
    /**
     * Report of patients whose lab requests carry a discount,
     * filterable by visit date range, doctor, minimum discount percentage,
     * patient discount comment, and patient name.
     */
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'doctor_id' => 'nullable|integer|exists:doctors,id',
            'min_discount_per' => 'nullable|integer|min:1|max:100',
            'comment' => 'nullable|string|max:255',
            'patient_name' => 'nullable|string|max:255',
        ]);

        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();
        $doctorId = $validated['doctor_id'] ?? null;
        $minDiscountPer = $validated['min_discount_per'] ?? null;
        $comment = $validated['comment'] ?? null;
        $patientName = $validated['patient_name'] ?? null;

        $labRequests = LabRequest::query()
            ->where('labrequests.discount_per', '>=', $minDiscountPer ?? 1)
            ->when($comment, function ($query) use ($comment): void {
                $query->whereHas('patient', function ($query) use ($comment): void {
                    $query->where('discount_comment', 'like', "%{$comment}%");
                });
            })
            ->when($patientName, function ($query) use ($patientName): void {
                $query->whereHas('patient', function ($query) use ($patientName): void {
                    $query->where('name', 'like', "%{$patientName}%");
                });
            })
            ->whereHas('doctorVisit', function ($query) use ($startDate, $endDate, $doctorId): void {
                $query->whereBetween('visit_date', [$startDate, $endDate]);
                if ($doctorId) {
                    $query->where('doctor_id', $doctorId);
                }
            })
            ->with([
                'patient:id,name,discount_comment',
                'mainTest:id,main_test_name',
                'doctorVisit:id,patient_id,visit_date,doctor_id',
                'doctorVisit.doctor:id,name',
            ])
            ->orderBy('doctor_visit_id')
            ->get();

        $items = $labRequests->map(function (LabRequest $lr): array {
            $subtotal = (float) $lr->price * max(1, (int) $lr->count);
            $discountAmount = round($subtotal * ((int) $lr->discount_per / 100), 2);

            return [
                'lab_request_id' => $lr->id,
                'patient_id' => $lr->patient?->id,
                'patient_name' => $lr->patient?->name,
                'visit_date' => $lr->doctorVisit?->visit_date?->toDateString(),
                'doctor_name' => $lr->doctorVisit?->doctor?->name,
                'test_name' => $lr->mainTest?->main_test_name,
                'price' => round((float) $lr->price, 2),
                'count' => (int) $lr->count,
                'discount_per' => (int) $lr->discount_per,
                'discount_amount' => $discountAmount,
                'endurance' => round((float) $lr->endurance, 2),
                'comment' => $lr->patient?->discount_comment,
            ];
        });

        return response()->json([
            'data' => $items->values(),
            'total_discount' => round((float) $items->sum('discount_amount'), 2),
            'report_period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * PDF export of the patient lab-request discounts report.
     */
    public function reportPdf(Request $request): Response
    {
        $jsonResponse = $this->report($request);
        $responseData = json_decode($jsonResponse->getContent(), true);

        $reportData = $responseData['data'] ?? [];
        $reportPeriod = $responseData['report_period'] ?? [
            'from' => $request->date_from,
            'to' => $request->date_to,
        ];
        $grandTotal = (float) ($responseData['total_discount'] ?? 0);

        $reportTitle = 'تقرير خصومات المختبر للمرضى';
        $filterCriteria = 'الفترة من: '.$reportPeriod['from'].' إلى: '.$reportPeriod['to'];
        if ($request->filled('doctor_id')) {
            $doctor = Doctor::find($request->doctor_id);
            if ($doctor) {
                $filterCriteria .= ' | الطبيب: '.$doctor->name;
            }
        }
        if ($request->filled('min_discount_per')) {
            $filterCriteria .= ' | الحد الأدنى لنسبة الخصم: '.$request->min_discount_per.'%';
        }
        if ($request->filled('patient_name')) {
            $filterCriteria .= ' | اسم المريض: '.$request->patient_name;
        }
        if ($request->filled('comment')) {
            $filterCriteria .= ' | الملاحظات: '.$request->comment;
        }

        $pdf = new MyCustomTCPDF(reportTitle: $reportTitle, orientation: 'P', unit: 'mm', format: 'A4', filterCriteria: $filterCriteria);
        $pdf->AddPage();
        $fontname = 'helvetica';

        $headers = ['اسم المريض', 'تاريخ الزيارة', 'الطبيب', 'التحليل', 'نسبة الخصم', 'قيمة الخصم'];
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [$pageWidth * 0.22, $pageWidth * 0.14, $pageWidth * 0.2, $pageWidth * 0.24, $pageWidth * 0.1, 0];
        $colWidths[count($colWidths) - 1] = $pageWidth - array_sum(array_slice($colWidths, 0, -1));
        $alignments = ['R', 'C', 'R', 'R', 'C', 'C'];

        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        $pdf->SetFont($fontname, '', 8);
        $fill = false;

        if (empty($reportData)) {
            $pdf->Cell(array_sum($colWidths), 10, 'لا توجد بيانات.', 1, 1, 'C', $fill);
        } else {
            foreach ($reportData as $row) {
                $rowData = [
                    $row['patient_name'] ?? '-',
                    $row['visit_date'] ?? '-',
                    $row['doctor_name'] ?? '-',
                    $row['test_name'] ?? '-',
                    ($row['discount_per'] ?? 0).'%',
                    number_format((float) $row['discount_amount'], 2),
                ];
                $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill, 7);
                $fill = ! $fill;
            }
        }

        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        if (! empty($reportData)) {
            $pdf->SetFont($fontname, 'B', 8.5);
            $summaryRowPdf = ['الإجمالي العام:', '', '', '', '', number_format($grandTotal, 2)];
            $pdf->DrawTableRow($summaryRowPdf, $colWidths, $alignments, true, 8);
        }

        $pdfFileName = 'lab_request_discounts_report_'.$reportPeriod['from'].'_to_'.$reportPeriod['to'].'.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }

    /**
     * Excel export of the patient lab-request discounts report.
     */
    public function reportExcel(Request $request): StreamedResponse
    {
        $jsonResponse = $this->report($request);
        $responseData = json_decode($jsonResponse->getContent(), true);

        $reportData = $responseData['data'] ?? [];
        $reportPeriod = $responseData['report_period'] ?? [
            'from' => $request->date_from,
            'to' => $request->date_to,
        ];
        $grandTotal = (float) ($responseData['total_discount'] ?? 0);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setRightToLeft(true);
        $spreadsheet->getProperties()->setTitle('تقرير خصومات المختبر للمرضى');

        $sheet->mergeCells('A1:H1');
        $sheet->setCellValue('A1', 'تقرير خصومات المختبر للمرضى');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(1)->setRowHeight(25);

        $sheet->mergeCells('A2:H2');
        $sheet->setCellValue('A2', 'الفترة من: '.$reportPeriod['from'].' إلى: '.$reportPeriod['to']);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $headerRow = 4;
        $headers = ['اسم المريض', 'تاريخ الزيارة', 'الطبيب', 'التحليل', 'السعر', 'نسبة الخصم', 'قيمة الخصم', 'ملاحظات'];
        $sheet->fromArray($headers, null, "A{$headerRow}");
        $sheet->getStyle("A{$headerRow}:H{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '4F46E5']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E0E0E0']]],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(20);

        $sheet->getColumnDimension('A')->setWidth(28);
        $sheet->getColumnDimension('B')->setWidth(14);
        $sheet->getColumnDimension('C')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(28);
        $sheet->getColumnDimension('E')->setWidth(14);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(14);
        $sheet->getColumnDimension('H')->setWidth(30);

        $dataStart = $headerRow + 1;
        $rowIdx = $dataStart;
        foreach ($reportData as $row) {
            $sheet->setCellValue("A{$rowIdx}", $row['patient_name'] ?? '-');
            $sheet->setCellValue("B{$rowIdx}", $row['visit_date'] ?? '-');
            $sheet->setCellValue("C{$rowIdx}", $row['doctor_name'] ?? '-');
            $sheet->setCellValue("D{$rowIdx}", $row['test_name'] ?? '-');
            $sheet->setCellValue("E{$rowIdx}", (float) ($row['price'] ?? 0));
            $sheet->setCellValue("F{$rowIdx}", ((int) ($row['discount_per'] ?? 0)).'%');
            $sheet->setCellValue("G{$rowIdx}", (float) ($row['discount_amount'] ?? 0));
            $sheet->setCellValue("H{$rowIdx}", $row['comment'] ?? '-');
            $rowIdx++;
        }
        $lastDataRow = $rowIdx - 1;

        if ($lastDataRow >= $dataStart) {
            $sheet->getStyle("E{$dataStart}:E{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("G{$dataStart}:G{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle("A{$dataStart}:H{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("A{$dataStart}:D{$lastDataRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle("A{$dataStart}:H{$lastDataRow}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('E0E0E0'));

            $totalsRow = $lastDataRow + 1;
            $sheet->setCellValue("F{$totalsRow}", 'الإجمالي العام:');
            $sheet->setCellValue("G{$totalsRow}", $grandTotal);
            $sheet->getStyle("A{$totalsRow}:H{$totalsRow}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => 'CC0000']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'FFF2CC']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['rgb' => '000000']]],
            ]);
            $sheet->getStyle("G{$totalsRow}")->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getRowDimension($totalsRow)->setRowHeight(22);
        }

        $writer = new Xlsx($spreadsheet);
        $fileName = 'lab_request_discounts_report_'.$reportPeriod['from'].'_to_'.$reportPeriod['to'].'.xlsx';

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
}
