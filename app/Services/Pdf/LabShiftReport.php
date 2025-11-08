<?php

namespace App\Services\Pdf;

use App\Models\Shift;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use TCPDF;

class LabShiftReport
{
    /**
     * Generate the Lab Shift PDF report content.
     * Returns raw PDF bytes as a string (TCPDF 'S' output mode).
     */
    public function generate(Shift $shift): string
    {
        // --- Data Retrieval ---
        $userIds = DB::table('labrequests')
            ->join('patients', 'patients.id', '=', 'labrequests.pid')
            ->where('patients.shift_id', $shift->id)
            ->whereNotNull('labrequests.user_deposited')
            ->distinct()
            ->pluck('labrequests.user_deposited');

        $depositingUsers = User::whereIn('id', $userIds)->get();

        $patientsWithLabRequests = Patient::where('shift_id', $shift->id)
            ->whereHas('labrequests')
            ->with(['labrequests', 'doctor', 'company'])
            ->orderBy('visit_number', 'asc')
            ->get();

        // --- PDF Initialization and Configuration ---
        $pdf = new TCPDF('L', PDF_UNIT, 'A4', true, 'UTF-8', false);

        $pdf->SetCreator(config('app.name', 'Your Application Name'));
        $pdf->SetAuthor(config('app.name', 'Your Application Name'));
        $pdf->SetTitle('تقرير المختبر للوردية رقم: ' . $shift->id);
        $pdf->SetSubject('ملخص إيرادات وتفاصيل فحوصات المختبر');
        $pdf->SetKeywords('مختبر, تقرير, وردية, فحوصات, مالي');

        // Header & Footer
        $logoPath = public_path('path/to/your/logo.png');
        $headerLogoWidth = 15;
        $headerTitle = config('app.name', 'اسم المختبر/العيادة');
        $headerString = "تقرير المختبر للوردية رقم: " . $shift->id . "\n" .
                        "تاريخ: " . $shift->created_at->format('Y/m/d') . " - " .
                        "الوقت: " . $shift->created_at->format('H:i A');
        // $pdf->SetFont('arial', '', 10);
        if (file_exists($logoPath)) {
            $pdf->SetHeaderData($logoPath, $headerLogoWidth, $headerTitle, $headerString);
        } else {
            $pdf->SetHeaderData('', 0, $headerTitle, $headerString);
        }

        $pdf->setHeaderFont(['arial', '', 10]);
        $pdf->setFooterFont(['arial', '', 10]);

        // Margins, Auto Page Breaks, Language, Font
        $pdf->SetMargins(10, 25, 10);
        $pdf->SetHeaderMargin(10);
        $pdf->SetFooterMargin(10);
        $pdf->SetAutoPageBreak(true, 15);

        $lg = [];
        $lg['a_meta_charset'] = 'UTF-8';
        $lg['a_meta_dir'] = 'rtl';
        $lg['a_meta_language'] = 'ar';
        $lg['w_page'] = 'صفحة';
        $pdf->setLanguageArray($lg);
        $pdf->setPrintHeader(true);
        $pdf->setPrintFooter(true);

        $pdf->setFont('arial', '', 18);

        // Colors
        $discountTextColor = [200, 0, 0];
        $defaultTextColor = [0, 0, 0];
        $headerFillColor = [220, 220, 220];

        // --- Page 1: Summary by Depositing User ---
        $pdf->AddPage();
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];

        $pdf->SetFont('arial', 'B', 18);
        $pdf->Cell(0, 10, 'ملخص إيرادات المختبر حسب المستخدم (المحصل)', 0, 1, 'C');
        $pdf->Ln(2);

        $pdf->SetFillColorArray($headerFillColor);
        $pdf->SetTextColorArray($defaultTextColor);
        $pdf->SetDrawColor(128, 128, 128);
        $pdf->SetLineWidth(0.2);

        $summaryColCount = 4;
        $summaryColWidth = $pageWidth / ($summaryColCount + 1);

        if ($depositingUsers->count() > 0) {
            $pdf->SetFont('arial', 'B', 14);
            $pdf->Cell($summaryColWidth, 7, 'المستخدم المحصل', 1, 0, 'C', 1);
            $pdf->Cell($summaryColWidth, 7, 'إجمالي المدفوع', 1, 0, 'C', 1);
            $pdf->Cell($summaryColWidth, 7, 'إجمالي التخفيض', 1, 0, 'C', 1);
            $pdf->Cell($summaryColWidth, 7, 'مدفوع بنكك', 1, 0, 'C', 1);
            $pdf->Cell($summaryColWidth, 7, 'مدفوع نقدي', 1, 1, 'C', 1);
            $pdf->SetFont('arial', '', 14);

            foreach ($depositingUsers as $user) {
                $paidLab = $shift->paidLab($user->id);
                $bankakLab = $shift->bankakLab($user->id);
                $cashLab = $paidLab - $bankakLab;
                $totalDiscount = $shift->totalLabDiscount($user->id);

                $pdf->Cell($summaryColWidth, 6, $user->username, 1, 0, 'R');
                $pdf->Cell($summaryColWidth, 6, number_format($paidLab, 2), 1, 0, 'C');

                if ($totalDiscount > 0) {
                    $pdf->SetTextColorArray($discountTextColor);
                }
                $pdf->Cell($summaryColWidth, 6, number_format($totalDiscount, 2), 1, 0, 'C');
                $pdf->SetTextColorArray($defaultTextColor);

                $pdf->Cell($summaryColWidth, 6, number_format($bankakLab, 2), 1, 0, 'C');
                $pdf->Cell($summaryColWidth, 6, number_format($cashLab, 2), 1, 1, 'C');
            }

            // Grand Totals
            $pdf->SetFont('arial', 'B', 14);
            $pdf->Cell($summaryColWidth, 7, 'الإجمالي العام', 1, 0, 'C', 1);
            $pdf->Cell($summaryColWidth, 7, number_format($shift->paidLab(), 2), 1, 0, 'C', 1);

            $grandTotalDiscount = $shift->totalLabDiscount();
            if ($grandTotalDiscount > 0) {
                $pdf->SetTextColorArray($discountTextColor);
            }
            $pdf->Cell($summaryColWidth, 7, number_format($grandTotalDiscount, 2), 1, 0, 'C', 1);
            $pdf->SetTextColorArray($defaultTextColor);

            $pdf->Cell($summaryColWidth, 7, number_format($shift->bankakLab(), 2), 1, 0, 'C', 1);
            $pdf->Cell($summaryColWidth, 7, number_format($shift->paidLab() - $shift->bankakLab(), 2), 1, 1, 'C', 1);
        } else {
            $pdf->SetFont('arial', '', 10);
            $pdf->Cell(0, 7, 'لا توجد بيانات متحصلين لعرضها لهذه الوردية.', 0, 1, 'C');
        }

        $pdf->Ln(5);

        // --- Page 2: Detailed Lab Requests ---
        if ($patientsWithLabRequests->count() > 0) {
            $pdf->AddPage();
            $pageWidthDetails = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];

            $pdf->SetFont('arial', 'B', 18);
            $pdf->Cell(0, 10, 'تفاصيل طلبات المختبر للمرضى', 0, 1, 'C');
            $pdf->Ln(2);

            $pdf->SetFont('arial', 'B', 12);
            $colWidths = [
                'visit_no' => $pageWidthDetails * 0.05,
                'name' => $pageWidthDetails * 0.18,
                'doctor' => $pageWidthDetails * 0.12,
                'total_val' => $pageWidthDetails * 0.08,
                'paid' => $pageWidthDetails * 0.08,
                'discount' => $pageWidthDetails * 0.07,
                'bank' => $pageWidthDetails * 0.07,
                'company' => $pageWidthDetails * 0.10,
                'tests' => $pageWidthDetails * 0.25,
            ];

            $pdf->SetFillColorArray($headerFillColor);
            $pdf->SetTextColorArray($defaultTextColor);
            $pdf->Cell($colWidths['visit_no'], 7, 'رقم ', 1, 0, 'C', 1);
            $pdf->Cell($colWidths['name'], 7, 'اسم المريض', 1, 0, 'C', 1);
            $pdf->Cell($colWidths['doctor'], 7, 'الطبيب المعالج', 1, 0, 'C', 1);
            $pdf->Cell($colWidths['total_val'], 7, 'إجمالي القيمة', 1, 0, 'C', 1);
            $pdf->Cell($colWidths['paid'], 7, 'المدفوع', 1, 0, 'C', 1);
            $pdf->Cell($colWidths['discount'], 7, 'التخفيض', 1, 0, 'C', 1);
            $pdf->Cell($colWidths['bank'], 7, 'بنكك', 1, 0, 'C', 1);
            $pdf->Cell($colWidths['company'], 7, 'الشركة/الجهة', 1, 0, 'C', 1);
            $pdf->Cell($colWidths['tests'], 7, 'الفحوصات المطلوبة', 1, 1, 'C', 1);

            $pdf->SetFont('arial', '', 10);
            $rowNum = 1;
            $alternateFillColor1 = [255, 255, 255];
            $alternateFillColor2 = [245, 245, 245];

            foreach ($patientsWithLabRequests as $patient) {
                $currentFillColor = ($rowNum % 2 == 0) ? $alternateFillColor2 : $alternateFillColor1;
                $pdf->SetFillColorArray($currentFillColor);
                $fillRow = true;

                $visitNumber = $patient->visit_number ?? 'N/A';
                $patientName = $patient->name ?? 'N/A';
                $doctorName = $patient->doctor->name ?? 'N/A';
                $totalLabValue = $patient->total_lab_value_unpaid();
                $paidLabForPatient = $patient->paid_lab();
                $discountAmount = $patient->discountAmount();
                $labBank = $patient->lab_bank();
                $companyName = $patient->company->name ?? 'نقدي';
                $testsConcatenated = $patient->tests_concatinated();

                $cellHeight = 6;
                $testStringHeight = $pdf->getStringHeight($colWidths['tests'], $testsConcatenated, false, true, '', 1);
                if ($testStringHeight > $cellHeight) {
                    $cellHeight = $testStringHeight;
                }

                $pdf->SetTextColorArray($defaultTextColor);
                $pdf->Cell($colWidths['visit_no'], $cellHeight, $visitNumber, 1, 0, 'C', $fillRow);
                $pdf->Cell($colWidths['name'], $cellHeight, $patientName, 1, 0, 'R', $fillRow);
                $pdf->Cell($colWidths['doctor'], $cellHeight, $doctorName, 1, 0, 'R', $fillRow);
                $pdf->Cell($colWidths['total_val'], $cellHeight, number_format($totalLabValue, 2), 1, 0, 'C', $fillRow);
                $pdf->Cell($colWidths['paid'], $cellHeight, number_format($paidLabForPatient, 2), 1, 0, 'C', $fillRow);

                if ($discountAmount > 0) {
                    $pdf->SetTextColorArray($discountTextColor);
                }
                $pdf->Cell($colWidths['discount'], $cellHeight, number_format($discountAmount, 2), 1, 0, 'C', $fillRow);
                $pdf->SetTextColorArray($defaultTextColor);

                $pdf->Cell($colWidths['bank'], $cellHeight, number_format($labBank, 2), 1, 0, 'C', $fillRow);
                $pdf->Cell($colWidths['company'], $cellHeight, $companyName, 1, 0, 'R', $fillRow);

                $currentY = $pdf->GetY();
                $pdf->MultiCell($colWidths['tests'], $cellHeight, $testsConcatenated, 1, 'R', $fillRow, 1, $pdf->GetX(), $currentY, true, 0, false, true, $cellHeight, 'M');

                $rowNum++;
            }
        } else {
            if ($pdf->getPage() == 1 && $depositingUsers->count() > 0) {
                $pdf->AddPage();
                $pdf->SetFont('arial', 'B', 18);
                $pdf->Cell(0, 10, 'تفاصيل طلبات المختبر للمرضى', 0, 1, 'C');
                $pdf->Ln(2);
            }
            $pdf->SetFont('arial', '', 10);
            $pdf->Cell(0, 7, 'لا توجد تفاصيل طلبات مختبر لعرضها لهذه الوردية.', 0, 1, 'C');
        }

        // Footer info
        $pdf->Ln(10);
        $pdf->SetFont('arial', '', 8);
        $pdf->Cell(0, 5, 'تم إنشاء هذا التقرير بواسطة: ' . config('app.name', 'نظام إدارة المختبر'), 0, 1, 'L');
        $pdf->Cell(0, 5, 'تاريخ الإنشاء: ' . now()->format('Y/m/d H:i:s'), 0, 1, 'L');

        $fileName = 'LabReport_Shift_' . $shift->id . '_' . now()->format('Ymd_His') . '.pdf';
        return $pdf->Output($fileName, 'S');
    }
}


