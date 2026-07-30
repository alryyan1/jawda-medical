<?php

namespace App\Services\Pdf;

use App\Models\DoctorVisit;
use App\Models\PatientMedicalHistory;
use TCPDF;

/**
 * VisitVitalsPdf
 *
 * Printable vital signs sheet for one doctor visit: the patient's baseline
 * readings plus every reading recorded during this specific visit.
 */
class VisitVitalsPdf extends TCPDF
{
    protected DoctorVisit $visit;

    protected float $pageUsableWidth;

    public function __construct(DoctorVisit $visit)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->visit = $visit;

        $this->setCreator('Jawda Medical');
        $this->setAuthor('Jawda Medical System');
        $this->setTitle('Vital Signs - Visit #'.$visit->id);

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
        $this->renderBaselineSection();
        $this->renderReadingsSection();

        return $this->Output('VisitVitals_'.$this->visit->id.'.pdf', 'S');
    }

    protected function renderIdentityBlock(): void
    {
        $patient = $this->visit->patient;
        $doctor = $this->visit->doctor;
        $font = 'arial';
        $col = $this->pageUsableWidth / 3;

        $this->SetFont($font, 'B', 16);
        $this->SetTextColor(41, 98, 255);
        $this->Cell($this->pageUsableWidth, 10, 'Vital Signs', 0, 1, 'C');
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
        $this->Ln(12);

        $drawCell('Age/Gender', ($patient?->full_age ?? '—').' / '.($patient?->gender ?? '—'));
        $drawCell('Visit Date', optional($this->visit->visit_date)->format('Y-m-d') ?? '—');
        $drawCell('', '');
        $this->Ln(16);
    }

    protected function sectionTitle(string $title): void
    {
        $this->SetFont('arial', 'B', 11);
        $this->SetFillColor(240, 244, 248);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(189, 195, 199);
        $this->Cell($this->pageUsableWidth, 8, $title, 1, 1, 'L', true);
        $this->Ln(1);
    }

    protected function renderBaselineSection(): void
    {
        $this->sectionTitle('Baseline Values');

        $patient = $this->visit->patient;
        $history = $patient
            ? PatientMedicalHistory::whereIn('patient_id', $patient->siblingPatientIds())
                ->latest('updated_at')
                ->first()
            : null;

        $rows = [
            'Blood Pressure' => $history?->baseline_bp,
            'Heart Rate' => $history?->baseline_heart_rate,
            'Temperature' => $history?->baseline_temp,
            'SpO2' => $history?->baseline_spo2,
            'Weight' => $history?->baseline_weight,
            'Height' => $history?->baseline_height,
            'Random Blood Sugar' => $history?->baseline_rbs,
        ];

        $hasAny = collect($rows)->filter(fn ($v) => ! empty($v))->isNotEmpty();

        if (! $hasAny) {
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(150, 150, 150);
            $this->Cell($this->pageUsableWidth, 6, 'No baseline values recorded for this patient.', 0, 1, 'L');
            $this->Ln(2);

            return;
        }

        $wLabel = $this->pageUsableWidth * 0.3;
        $wValue = $this->pageUsableWidth - $wLabel;

        $this->SetFont('arial', '', 9);
        $this->SetTextColor(40, 40, 40);
        foreach ($rows as $label => $value) {
            if (empty($value)) {
                continue;
            }
            $this->Cell($wLabel, 6, $label, 0, 0, 'L');
            $this->Cell($wValue, 6, (string) $value, 0, 1, 'L');
        }
        $this->Ln(2);
    }

    protected function renderReadingsSection(): void
    {
        $this->sectionTitle('Readings for This Visit');

        $readings = $this->visit->vitals()->orderBy('recorded_at')->get();

        if ($readings->isEmpty()) {
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(150, 150, 150);
            $this->Cell($this->pageUsableWidth, 6, 'No readings recorded for this visit.', 0, 1, 'L');
            $this->Ln(2);

            return;
        }

        $headers = ['Time', 'BP', 'Pulse', 'Resp.', 'Pain', 'Temp.', 'SpO2', 'Weight', 'Height', 'RBS'];
        $widths = array_fill(0, count($headers), $this->pageUsableWidth / count($headers));

        $this->SetFont('arial', 'B', 8);
        $this->SetFillColor(240, 244, 248);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(189, 195, 199);
        foreach ($headers as $i => $header) {
            $this->Cell($widths[$i], 7, $header, 1, 0, 'C', true);
        }
        $this->Ln(7);

        $this->SetFont('arial', '', 8);
        $this->SetTextColor(30, 30, 30);
        foreach ($readings as $reading) {
            $bp = $reading->blood_pressure_systolic
                ? $reading->blood_pressure_systolic.'/'.$reading->blood_pressure_diastolic
                : '—';
            $cells = [
                optional($reading->recorded_at)->format('H:i') ?? '—',
                $bp,
                $reading->heart_rate ?? '—',
                $reading->respiratory_rate ?? '—',
                $reading->pain_scale ?? '—',
                $reading->temperature ?? '—',
                $reading->spo2 ? $reading->spo2.'%' : '—',
                $reading->weight ?? '—',
                $reading->height ?? '—',
                $reading->rbs ?? '—',
            ];
            foreach ($cells as $i => $cell) {
                $this->Cell($widths[$i], 6, (string) $cell, 1, 0, 'C');
            }
            $this->Ln(6);
        }
        $this->Ln(2);
    }
}
