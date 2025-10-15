<?php

namespace App\Services\Pdf;

use App\Models\DoctorShift;
use App\Models\Shift;
use App\Models\User;
use App\Mypdf\Pdf;

class InsuranceReport
{
    /**
     * Generate insurance report PDF content.
     *
     * @param int|null $shiftId
     * @param int|null $userId
     * @return string PDF content (binary string)
     */
    public function generate(?int $shiftId = null, ?int $userId = null): string
    {
        $shift = $shiftId ? Shift::find($shiftId) : Shift::orderByDesc('id')->first();

        if (!$shift) {
            abort(404, 'Shift not found');
        }

        if ($userId) {
            $doctor_shifts = DoctorShift::with(['doctor', 'visits'])
                ->where('user_id', $userId)
                ->where('status', 1)
                ->where('shift_id', $shift->id)
                ->get();
        } else {
            $doctor_shifts = DoctorShift::with(['doctor', 'visits'])
                ->where('shift_id', $shift->id)
                ->get();
        }

        $pdf = new Pdf('landscape', PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $lg = [];
        $lg['a_meta_charset'] = 'UTF-8';
        $lg['a_meta_dir'] = 'rtl';
        $lg['a_meta_language'] = 'fa';
        $lg['w_page'] = 'page';
        $pdf->setLanguageArray($lg);
        $pdf->setCreator(PDF_CREATOR);
        $pdf->setAuthor('Jawda System');
        $pdf->setTitle('التامين');
        $pdf->setSubject('Insurance Report');
        $pdf->setKeywords('TCPDF, PDF, insurance, report');
        $pdf->setHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, PDF_HEADER_TITLE, PDF_HEADER_STRING);
        $pdf->setHeaderFont([PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN]);
        $pdf->setFooterFont([PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA]);
        $pdf->setDefaultMonospacedFont(PDF_FONT_MONOSPACED);
        $pdf->setMargins(PDF_MARGIN_LEFT, 5, PDF_MARGIN_RIGHT);
        $pdf->setHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->setFooterMargin(PDF_MARGIN_FOOTER);
        $pdf->setAutoPageBreak(true, PDF_MARGIN_BOTTOM);
        $pdf->setFont('arial', 'B', 12);
        $pdf->AddPage();
        $page_width = $pdf->getPageWidth() - PDF_MARGIN_LEFT - PDF_MARGIN_RIGHT;


        $pdf->Cell($page_width, 5, 'التعاقدات', 0, 1, 'C');
        $pdf->setFont('arial', 'b', 14);
        $table_col_widht = ($page_width) / 6;

        $pdf->Cell($table_col_widht, 5, "التاريخ", 0, 0, 'L');
        $pdf->Cell($table_col_widht, 5, (string) $shift->created_at->format('Y/m/d'), 0, 0, 'R');
        $pdf->Cell($table_col_widht * 2, 5, "رقم الورديه المالي " . $shift->id, 0, 0, 'C');
        $pdf->Cell($table_col_widht, 5, "الوقت", 0, 0, 'L');
        $pdf->Cell($table_col_widht, 5, (string) $shift->created_at->format('H:i A'), 0, 0, 'R');
        $pdf->Ln();

        $pdf->setFont('arial', '', 12);
        $col = $page_width / 8;

        $pdf->Ln();
        $iterator = 0;
        $users = User::all(); // kept for parity with original snippet if needed later
        $pdf->Ln();
        $pdf->SetFillColor(200, 200, 200);

        $pdf->Cell($col, 5, 'الرقم', 0, 0, 'C', 1);
        $pdf->Cell($col, 5, 'الاسم', 0, 0, 'C', 1);
        $pdf->Cell($col, 5, 'الزمن', 0, 0, 'C', 1);
        $pdf->Cell($col, 5, 'رقم البطاقه', 0, 0, 'C', 1);
        $pdf->Cell($col, 5, 'الشركه', 0, 0, 'C', 1);
        $pdf->Cell($col, 5, 'الضامن', 0, 0, 'C', 1);
        $pdf->Cell($col, 5, 'الاجمالي', 0, 0, 'C', 1);
        $pdf->Cell($col, 5, 'التحمل', 0, 1, 'C', 1);

        foreach ($shift->patients as $doctorvisit) {
            if (!$doctorvisit->patient->company) {
                continue;
            }

            $pdf->Cell($col, 5, (string) $doctorvisit->patient->visit_number, 0, 0, 'C');
            $pdf->Cell($col, 5, (string) $doctorvisit->patient->name, 0, 0, 'C');
            $pdf->Cell($col, 5, (string) $doctorvisit->created_at->format('H:i A'), 0, 0, 'C');
            $pdf->Cell($col, 5, (string) ($doctorvisit->patient->insurance_no ?? ''), 0, 0, 'C');
            $pdf->Cell($col, 5, (string) ($doctorvisit->patient->company->name ?? ''), 0, 0, 'C');
            $pdf->Cell($col, 5, (string) ($doctorvisit->patient->guarantor ?? ''), 0, 0, 'C');
            $pdf->Cell($col, 5, (string) number_format($doctorvisit->total_services() + $doctorvisit->patient->total_price(), 1), 0, 0, 'C');
            $pdf->Cell($col, 5, (string) number_format($doctorvisit->total_paid_services_insurance() + $doctorvisit->patient->paid_lab(), 1), 0, 1, 'C');
        }

        return $pdf->Output('insurance.pdf', 'S');
    }
}


