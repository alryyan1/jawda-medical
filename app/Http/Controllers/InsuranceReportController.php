<?php

namespace App\Http\Controllers;

use App\Services\Pdf\InsuranceReport;
use Illuminate\Http\Request;

class InsuranceReportController extends Controller
{
    public function insuranceReport(Request $request)
    {
        $shiftId = $request->has('shift') ? (int) $request->get('shift') : null;
        $userId = $request->has('user') ? (int) $request->get('user') : null;

        $report = new InsuranceReport();
        $pdfContent = $report->generate($shiftId, $userId);

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="insurance.pdf"',
        ]);
    }
}






