<?php

namespace App\Services\Pdf;

use App\Models\VisitDiagnosis;

/**
 * VisitDiagnosisPdf
 *
 * Printable diagnosis / clinical note for one doctor visit, rendered from
 * its rich-text (HTML) content. Extracted from
 * VisitDiagnosisController::generatePdf so it can also be generated
 * on-demand (e.g. for a WhatsApp document reply) without an HTTP response.
 */
class VisitDiagnosisPdf
{
    public function __construct(protected VisitDiagnosis $diagnosis) {}

    public function generate(): string
    {
        $this->diagnosis->loadMissing(['user', 'doctorVisit.patient', 'doctorVisit.doctor']);

        $visit = $this->diagnosis->doctorVisit;
        $patient = $visit?->patient;
        $doctor = $visit?->doctor;

        $pdf = new MyCustomTCPDF('Visit Diagnosis Report', null, 'P', 'mm', 'A4');
        $pdf->setRTL(false);
        $pdf->SetMargins(15, 48, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $pw = $pdf->getPageWidth() - 30;
        $col = $pw / 3;
        $font = 'arial';

        $drawCell = function (string $label, string $value) use ($pdf, $col, $font) {
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->Rect($x, $y, $col, 12, 'D');
            $pdf->SetFont($font, '', 7);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetXY($x + 2, $y + 1.5);
            $pdf->Cell($col - 4, 4, strtoupper($label), 0, 0, 'L');
            $pdf->SetFont($font, 'B', 9);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetXY($x + 2, $y + 6);
            $pdf->Cell($col - 4, 5, $value, 0, 0, 'L');
            $pdf->SetXY($x + $col, $y);
        };

        $drawCell('Patient', $patient?->name ?? '—');
        $drawCell('Visit', '#'.($visit?->id ?? '—'));
        $drawCell('Doctor', $doctor?->name ?? '—');
        $pdf->Ln(12);

        $drawCell('Diagnosed By', $this->diagnosis->user?->name ?? '—');
        $drawCell('Visit Date', $visit?->visit_date?->format('Y-m-d') ?? '—');
        $drawCell(
            $this->diagnosis->completed_at ? 'Completed At' : 'Status',
            $this->diagnosis->completed_at
                ? $this->diagnosis->completed_at->format('Y-m-d h:i A')
                : 'In Progress'
        );
        $pdf->Ln(14);

        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(15, $pdf->GetY(), 15 + $pw, $pdf->GetY());
        $pdf->Ln(4);

        $pdf->SetFont($font, '', 10);
        $pdf->SetTextColor(0, 0, 0);

        $html = $this->diagnosis->diagnosis ?? '<p>No diagnosis recorded.</p>';
        $html = '<div style="direction:ltr; text-align:left;">'.$html.'</div>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $pdf->SetFont($font, 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Ln(6);
        $pdf->Cell($pw, 5, 'Printed: '.now()->format('Y-m-d h:i A').'   |   '.($this->diagnosis->user?->name ?? ''), 0, 1, 'R');

        return $pdf->Output('VisitDiagnosisReport.pdf', 'S');
    }
}
