<?php

namespace App\Services\Pdf;

use App\Models\VisitMedicalReport;
use TCPDF;

/**
 * VisitMedicalReportPdf
 *
 * Printable medical report for one doctor visit: the free-text report
 * authored by the doctor, rendered from its rich-text (HTML) content.
 */
class VisitMedicalReportPdf extends TCPDF
{
    protected VisitMedicalReport $report;

    protected float $pageUsableWidth;

    public function __construct(VisitMedicalReport $report)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->report = $report;

        $this->setCreator('Jawda Medical');
        $this->setAuthor('Jawda Medical System');
        $this->setTitle('Medical Report - Visit #'.$report->doctor_visit_id);

        $this->setMargins(15, 15, 15);
        $this->setHeaderMargin(0);
        $this->setFooterMargin(15);
        $this->setPrintHeader(false);
        $this->setAutoPageBreak(true, 20);

        $this->pageUsableWidth = $this->getPageWidth() - 30;
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetDrawColor(220, 220, 220);
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->Ln(2);
        $this->SetFont('arial', 'I', 8);
        $this->SetTextColor(127, 140, 141);
        $this->Cell(0, 5, 'Page '.$this->getAliasNumPage().' of '.$this->getAliasNbPages(), 0, 0, 'C');
    }

    public function generate(): string
    {
        $this->setRTL(false);
        $this->AddPage();
        $this->renderIdentityBlock();
        $this->renderContent();

        return $this->Output('VisitMedicalReport_'.$this->report->doctor_visit_id.'.pdf', 'S');
    }

    protected function renderIdentityBlock(): void
    {
        $visit = $this->report->doctorVisit;
        $patient = $visit?->patient;
        $doctor = $visit?->doctor;
        $font = 'arial';
        $col = $this->pageUsableWidth / 3;

        $this->SetFont($font, 'B', 16);
        $this->SetTextColor(41, 98, 255);
        $this->Cell($this->pageUsableWidth, 10, 'Medical Report', 0, 1, 'C');
        $this->Ln(2);

        $drawCell = function (string $label, string $value) use ($col, $font) {
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $col, 12, 'D');
            $this->SetFont($font, '', 7);
            $this->SetTextColor(120, 120, 120);
            $this->SetXY($x + 2, $y + 1.5);
            $this->Cell($col - 4, 4, strtoupper($label), 0, 0, 'L');
            $this->SetFont($font, 'B', 9);
            $this->SetTextColor(30, 30, 30);
            $this->SetXY($x + 2, $y + 6);
            $this->Cell($col - 4, 5, $value, 0, 0, 'L');
            $this->SetXY($x + $col, $y);
        };

        $drawCell('Patient', $patient?->name ?? '—');
        $drawCell('Visit', '#'.($visit?->id ?? '—'));
        $drawCell('Doctor', $doctor?->name ?? '—');
        $this->Ln(12);

        $drawCell('Written By', $this->report->user?->name ?? '—');
        $drawCell('Visit Date', optional($visit?->visit_date)->format('Y-m-d') ?? '—');
        $drawCell(
            $this->report->completed_at ? 'Completed At' : 'Status',
            $this->report->completed_at
                ? $this->report->completed_at->format('Y-m-d h:i A')
                : ($this->report->complete ? 'Completed' : 'In Progress')
        );
        $this->Ln(16);

        $this->SetDrawColor(189, 195, 199);
        $this->Line(15, $this->GetY(), $this->getPageWidth() - 15, $this->GetY());
        $this->Ln(4);
    }

    protected function renderContent(): void
    {
        $this->SetFont('arial', '', 10);
        $this->SetTextColor(0, 0, 0);

        $html = $this->report->content ?: '<p>No content recorded.</p>';
        $html = '<div style="direction:ltr; text-align:left;">'.$html.'</div>';
        $this->writeHTML($html, true, false, true, false, '');
    }
}
