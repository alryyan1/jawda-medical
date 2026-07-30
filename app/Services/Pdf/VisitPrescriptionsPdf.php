<?php

namespace App\Services\Pdf;

use App\Models\DoctorVisit;
use App\Models\VisitPrescription;
use TCPDF;

/**
 * VisitPrescriptionsPdf
 *
 * Printable sheet listing every prescription order recorded during one
 * doctor visit, each with its own medication table.
 */
class VisitPrescriptionsPdf extends TCPDF
{
    protected DoctorVisit $visit;

    protected float $pageUsableWidth;

    public function __construct(DoctorVisit $visit)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->visit = $visit;

        $this->setCreator('Jawda Medical');
        $this->setAuthor('Jawda Medical System');
        $this->setTitle('Prescriptions - Visit #'.$visit->id);

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

        $prescriptions = $this->visit->prescriptions;

        if ($prescriptions->isEmpty()) {
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(150, 150, 150);
            $this->Cell($this->pageUsableWidth, 6, 'No prescriptions recorded for this visit.', 0, 1, 'L');
        } else {
            foreach ($prescriptions as $prescription) {
                $this->renderPrescription($prescription);
            }
        }

        return $this->Output('VisitPrescriptions_'.$this->visit->id.'.pdf', 'S');
    }

    protected function renderIdentityBlock(): void
    {
        $patient = $this->visit->patient;
        $doctor = $this->visit->doctor;
        $font = 'arial';
        $col = $this->pageUsableWidth / 3;

        $this->SetFont($font, 'B', 16);
        $this->SetTextColor(41, 98, 255);
        $this->Cell($this->pageUsableWidth, 10, 'Prescriptions', 0, 1, 'C');
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
        $drawCell('Visit', '#'.$this->visit->id);
        $drawCell('Doctor', $doctor?->name ?? '—');
        $this->Ln(16);
    }

    protected function renderPrescription(VisitPrescription $prescription): void
    {
        $this->SetFont('arial', 'B', 11);
        $this->SetFillColor(240, 244, 248);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(189, 195, 199);
        $title = 'Prescription #'.$prescription->id.' — '.(optional($prescription->created_at)->format('Y-m-d H:i') ?? '—');
        $this->Cell($this->pageUsableWidth, 8, $title, 1, 1, 'L', true);
        $this->Ln(1);

        $wMed = $this->pageUsableWidth * 0.3;
        $wDosage = $this->pageUsableWidth * 0.15;
        $wFreq = $this->pageUsableWidth * 0.15;
        $wDuration = $this->pageUsableWidth * 0.15;
        $wInstructions = $this->pageUsableWidth - $wMed - $wDosage - $wFreq - $wDuration;

        $this->SetFont('arial', 'B', 9);
        $this->SetFillColor(250, 250, 250);
        $this->Cell($wMed, 7, 'Medication', 1, 0, 'L', true);
        $this->Cell($wDosage, 7, 'Dosage', 1, 0, 'C', true);
        $this->Cell($wFreq, 7, 'Frequency', 1, 0, 'C', true);
        $this->Cell($wDuration, 7, 'Duration', 1, 0, 'C', true);
        $this->Cell($wInstructions, 7, 'Instructions', 1, 1, 'L', true);

        $this->SetFont('arial', '', 9);
        foreach ($prescription->items as $item) {
            $this->Cell($wMed, 6, $item->medication_name, 1, 0, 'L');
            $this->Cell($wDosage, 6, $item->dosage ?? '—', 1, 0, 'C');
            $this->Cell($wFreq, 6, $item->frequency ?? '—', 1, 0, 'C');
            $this->Cell($wDuration, 6, $item->duration ?? '—', 1, 0, 'C');
            $this->Cell($wInstructions, 6, $item->instructions ?? '—', 1, 1, 'L');
        }

        if ($prescription->notes) {
            $this->SetFont('arial', 'I', 8);
            $this->SetTextColor(100, 100, 100);
            $this->MultiCell($this->pageUsableWidth, 5, 'Notes: '.$prescription->notes, 0, 'L');
        }

        $this->Ln(4);
    }
}
