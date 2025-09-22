<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\Pdf\MyCustomTCPDF;
use Illuminate\Http\Request;

class CompanyReportController extends Controller
{
    public function exportAllCompaniesPdf(Request $request)
    {
        $companies = Company::orderBy('name')->get();

        $pdf = new MyCustomTCPDF('قائمة الشركات', null, 'P', 'mm', 'A4', true, 'UTF-8', false, false, 'جميع الشركات');
        $pdf->AddPage();
        $pdf->setRTL(true);

        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];

        // Header
        $pdf->SetFont('arial', 'B', 14);
        $pdf->Cell($pageWidth, 8, 'قائمة الشركات', 0, 1, 'C');
        $pdf->SetFont('arial', '', 10);
        $pdf->Cell($pageWidth, 6, 'إجمالي: ' . $companies->count(), 0, 1, 'R');
        $pdf->Ln(2);

        // Table
        $headers = ['#', 'الاسم', 'الهاتف', 'نشط', 'تحمل الخدمات', 'تحمل المختبر'];
        $colWidths = [15, 70, 35, 20, 25, 0];
        $colWidths[count($colWidths) - 1] = $pageWidth - array_sum(array_slice($colWidths, 0, -1));
        $aligns = ['C', 'R', 'C', 'C', 'C', 'C'];

        $pdf->DrawTableHeader($headers, $colWidths, $aligns);
        $pdf->SetFont('arial', '', 9);
        $fill = false;
        foreach ($companies as $c) {
            $row = [
                (string)$c->id,
                $c->name ?? '-',
                $c->phone ?? '-',
                $c->status ? 'نعم' : 'لا',
                (string)($c->service_endurance ?? ''),
                (string)($c->lab_endurance ?? ''),
            ];
            $pdf->DrawTableRow($row, $colWidths, $aligns, $fill);
            $fill = !$fill;
        }

        $fileName = 'companies_' . date('Ymd_His') . '.pdf';
        $pdfContent = $pdf->Output($fileName, 'S');
        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$fileName}\"");
    }
}
