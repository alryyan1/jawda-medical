<?php

namespace App\Services\Pdf;

use App\Models\DoctorVisit;
use App\Models\PatientMedicalHistory;
use App\Models\Setting;
use App\Models\VisitDose;
use App\Models\VisitIntakeOutput;
use TCPDF;

/**
 * VisitShortStayAdmissionPdf
 *
 * Printable "short stay admission" chart for one doctor visit, laid out to
 * match the hospital's paper form: an admission summary page, a medications
 * (MAR) grid, a vitals monitoring grid, and the fluid balance (intake/output)
 * chart — each pre-filled from the visit's recorded data where available,
 * with trailing blank rows left for continued handwriting after printing.
 */
class VisitShortStayAdmissionPdf extends TCPDF
{
    protected DoctorVisit $visit;

    protected float $pageUsableWidth;

    protected ?Setting $settings;

    public function __construct(DoctorVisit $visit)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->visit = $visit;
        $this->settings = Setting::first();

        $this->setCreator(config('app.name', 'Jawda Medical'));
        $this->setAuthor($this->settings?->hospital_name ?? config('app.name'));
        $this->setTitle('General Admission Report - Visit #'.$visit->id);

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
        $this->renderLetterhead('General Admission Report');
        $this->renderAdmissionPage();

        $this->AddPage();
        $this->renderLetterhead();
        $this->renderMedicationsPage();

        $this->AddPage();
        $this->renderLetterhead();
        $this->renderVitalsMonitoringPage();

        $this->AddPage();
        $this->renderIntakeOutputPage();

        return $this->Output('ShortStayAdmission_'.$this->visit->id.'.pdf', 'S');
    }

    protected function renderLetterhead(?string $subtitle = null): void
    {
        $logoPath = $this->settings?->header_base64 ? public_path($this->settings->header_base64) : null;
        $hospitalName = $this->settings?->hospital_name ?? config('app.name', 'Jawda Medical');

        if ($logoPath && file_exists($logoPath)) {
            $this->Image($logoPath, ($this->getPageWidth() - 18) / 2, $this->GetY(), 18, 18, '', '', '', true, 150, '', false, false, 0, 'C');
            $this->Ln(20);
        }

        $this->SetFont('arial', 'B', 13);
        $this->SetTextColor(30, 30, 30);
        $this->Cell($this->pageUsableWidth, 7, $hospitalName, 0, 1, 'C');

        if ($subtitle) {
            $this->SetFont('arial', '', 10);
            $this->SetTextColor(90, 90, 90);
            $this->Cell($this->pageUsableWidth, 6, $subtitle, 0, 1, 'C');
        }

        $this->SetDrawColor(189, 195, 199);
        $this->Line(15, $this->GetY() + 1, $this->getPageWidth() - 15, $this->GetY() + 1);
        $this->Ln(6);
    }

    protected function renderAdmissionPage(): void
    {
        $patient = $this->visit->patient;
        $doctor = $this->visit->doctor;
        $font = 'arial';

        $history = $patient
            ? PatientMedicalHistory::whereIn('patient_id', $patient->siblingPatientIds())
                ->latest('updated_at')
                ->first()
            : null;

        $latestVital = $this->visit->vitals()->latest('recorded_at')->first();

        // Name / Age / Gender
        $this->SetFont($font, '', 10);
        $this->SetTextColor(30, 30, 30);
        $this->Cell(20, 7, 'Name:', 0, 0, 'L');
        $this->SetFont($font, 'B', 10);
        $this->Cell(90, 7, $patient?->name ?? '—', 'B', 0, 'L');
        $this->SetFont($font, '', 10);
        $this->Cell(15, 7, 'Age:', 0, 0, 'L');
        $this->SetFont($font, 'B', 10);
        $this->Cell(20, 7, $patient?->full_age ?? '—', 'B', 0, 'L');
        $this->SetFont($font, '', 10);
        $this->Cell(18, 7, 'Gender', 0, 0, 'L');
        $this->SetFont($font, 'B', 10);
        $this->Cell(0, 7, $patient?->gender ?? '—', 'B', 1, 'L');
        $this->Ln(2);

        // Address / Date of Admission
        $this->SetFont($font, '', 10);
        $this->Cell(25, 7, 'Address:', 0, 0, 'L');
        $this->SetFont($font, 'B', 10);
        $this->Cell(100, 7, $patient?->address ?? '—', 'B', 0, 'L');
        $this->SetFont($font, '', 10);
        $this->Cell(16, 7, 'Do A:', 0, 0, 'L');
        $this->SetFont($font, 'B', 10);
        $this->Cell(0, 7, optional($this->visit->visit_date)->format('Y-m-d') ?? '—', 'B', 1, 'L');
        $this->Ln(4);

        // Vital signs line
        $this->SetFont($font, '', 10);
        $this->Cell(24, 7, 'Vital signs:', 0, 0, 'L');
        $this->Cell(12, 7, 'Bp', 0, 0, 'L');
        $this->SetFont($font, 'B', 10);
        $bp = $latestVital?->blood_pressure_systolic
            ? $latestVital->blood_pressure_systolic.'/'.$latestVital->blood_pressure_diastolic
            : ($history?->baseline_bp ?? '—');
        $this->Cell(24, 7, (string) $bp, 'B', 0, 'L');
        $this->SetFont($font, '', 10);
        $this->Cell(16, 7, 'SPo2', 0, 0, 'L');
        $this->SetFont($font, 'B', 10);
        $this->Cell(20, 7, (string) ($latestVital?->spo2 ?? $history?->baseline_spo2 ?? '—'), 'B', 0, 'L');
        $this->SetFont($font, '', 10);
        $this->Cell(12, 7, 'PR', 0, 0, 'L');
        $this->SetFont($font, 'B', 10);
        $this->Cell(20, 7, (string) ($latestVital?->heart_rate ?? $history?->baseline_heart_rate ?? '—'), 'B', 0, 'L');
        $this->SetFont($font, '', 10);
        $this->Cell(12, 7, 'RR', 0, 0, 'L');
        $this->SetFont($font, 'B', 10);
        $this->Cell(0, 7, (string) ($latestVital?->respiratory_rate ?? '—'), 'B', 1, 'L');
        $this->Ln(4);

        $this->renderLabeledTextBlock('PMH :', $history?->past_medical_history, $font);
        $this->renderLabeledTextBlock('C / O :', $history?->present_complains_summary, $font);
        $this->renderLabeledTextBlock('Plan :', $history?->overall_care_plan_summary, $font);

        $this->Ln(6);
        $this->SetFont($font, '', 10);
        $this->Cell(0, 7, 'Doctor signature: '.($doctor?->name ?? '—').'  ...........................', 0, 1, 'R');
    }

    /**
     * A labeled block with a few ruled lines beneath it — pre-filled with the
     * given text (wrapped) when present, otherwise left blank for handwriting.
     */
    protected function renderLabeledTextBlock(string $label, ?string $text, string $font, int $lines = 5): void
    {
        $this->SetFont($font, 'B', 10);
        $this->SetTextColor(30, 30, 30);
        $this->Cell(0, 7, $label, 0, 1, 'L');

        $lineHeight = 6;
        $startY = $this->GetY();

        $this->SetFont($font, '', 9);
        $this->SetTextColor(40, 40, 40);
        if (! empty($text)) {
            $this->MultiCell($this->pageUsableWidth, $lineHeight, $text, 0, 'L');
            $usedLines = max(1, (int) ceil($this->GetY() - $startY) / $lineHeight);
            $remaining = max(0, $lines - $usedLines);
        } else {
            $remaining = $lines;
        }

        $this->SetDrawColor(220, 220, 220);
        for ($i = 0; $i < $remaining; $i++) {
            $y = $this->GetY() + $lineHeight;
            $this->Line(15, $y, $this->getPageWidth() - 15, $y);
            $this->SetY($y);
        }
        $this->Ln(2);
    }

    protected function renderMedicationsPage(): void
    {
        $this->SetFont('arial', 'B', 13);
        $this->SetTextColor(30, 30, 30);
        $this->Cell($this->pageUsableWidth, 8, 'Medications', 0, 1, 'C');
        $this->Ln(1);

        $headers = ['Medications', 'Frequency', 'Time', 'Time', 'Time', 'Time', 'Time'];
        $widths = [
            $this->pageUsableWidth * 0.28,
            $this->pageUsableWidth * 0.18,
            $this->pageUsableWidth * 0.108,
            $this->pageUsableWidth * 0.108,
            $this->pageUsableWidth * 0.108,
            $this->pageUsableWidth * 0.108,
            $this->pageUsableWidth * 0.108,
        ];

        $this->SetFont('arial', 'B', 8);
        $this->SetFillColor(240, 244, 248);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(120, 120, 120);
        foreach ($headers as $i => $header) {
            $this->Cell($widths[$i], 7, $header, 1, 0, 'C', true);
        }
        $this->Ln(7);

        $doses = $this->visit->doses()->orderBy('administered_at')->get();
        $grouped = $doses->groupBy(fn (VisitDose $d) => $d->medication_name.'|'.$d->dosage);

        $this->SetFont('arial', '', 8);
        $this->SetTextColor(30, 30, 30);
        $rowsRendered = 0;
        foreach ($grouped as $group) {
            $medName = $group->first()->medication_name;
            $dosage = $group->first()->dosage;
            $times = $group->pluck('administered_at')->filter()->map(fn ($t) => $t->format('H:i'));

            foreach ($times->chunk(5) as $chunkIndex => $chunk) {
                $cells = array_merge(
                    [$chunkIndex === 0 ? $medName : '', $chunkIndex === 0 ? $dosage : ''],
                    $chunk->values()->all()
                );
                $cells = array_pad($cells, 7, '');
                foreach ($cells as $i => $cell) {
                    $this->Cell($widths[$i], 6.5, (string) $cell, 1, 0, 'C');
                }
                $this->Ln(6.5);
                $rowsRendered++;
            }
        }

        // Pad with blank rows so the printed sheet still has room to continue by hand.
        $minRows = 20;
        for ($i = $rowsRendered; $i < $minRows; $i++) {
            foreach ($widths as $width) {
                $this->Cell($width, 6.5, '', 1, 0, 'C');
            }
            $this->Ln(6.5);
        }
    }

    protected function renderVitalsMonitoringPage(): void
    {
        $this->SetFont('arial', 'B', 13);
        $this->SetTextColor(30, 30, 30);
        $this->Cell($this->pageUsableWidth, 8, 'Vital Signs Monitoring', 0, 1, 'C');
        $this->Ln(1);

        $headers = ['Time', 'HR', 'SPo2', 'RR', 'Temp', 'BP', 'MOP', 'RBG'];
        $widths = array_fill(0, count($headers), $this->pageUsableWidth / count($headers));

        $this->SetFont('arial', 'B', 8);
        $this->SetFillColor(240, 244, 248);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(120, 120, 120);
        foreach ($headers as $i => $header) {
            $this->Cell($widths[$i], 7, $header, 1, 0, 'C', true);
        }
        $this->Ln(7);

        $readings = $this->visit->vitals()->orderBy('recorded_at')->get();

        $this->SetFont('arial', '', 8);
        $this->SetTextColor(30, 30, 30);
        foreach ($readings as $reading) {
            $bp = $reading->blood_pressure_systolic
                ? $reading->blood_pressure_systolic.'/'.$reading->blood_pressure_diastolic
                : '';
            $cells = [
                optional($reading->recorded_at)->format('H:i') ?? '',
                $reading->heart_rate ?? '',
                $reading->spo2 ?? '',
                $reading->respiratory_rate ?? '',
                $reading->temperature ?? '',
                $bp,
                // MOP has no matching field recorded in the system — left blank for manual entry.
                '',
                $reading->rbs ?? '',
            ];
            foreach ($cells as $i => $cell) {
                $this->Cell($widths[$i], 6.5, (string) $cell, 1, 0, 'C');
            }
            $this->Ln(6.5);
        }

        $minRows = 22;
        for ($i = $readings->count(); $i < $minRows; $i++) {
            foreach ($widths as $width) {
                $this->Cell($width, 6.5, '', 1, 0, 'C');
            }
            $this->Ln(6.5);
        }
    }

    protected function renderIntakeOutputPage(): void
    {
        $this->SetFont('arial', 'B', 13);
        $this->SetTextColor(30, 30, 30);
        $this->Cell($this->pageUsableWidth, 8, 'Input and Output chart', 0, 1, 'C');
        $this->Ln(1);

        $headers = ['Time', 'Feeding', 'IV fluid', 'Maintance', 'Urine pure', 'Essnspulless', 'Total intake', 'Total output', 'Balance'];
        $widths = array_fill(0, count($headers), $this->pageUsableWidth / count($headers));

        $this->SetFont('arial', 'B', 8);
        $this->SetFillColor(240, 244, 248);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(120, 120, 120);
        foreach ($headers as $i => $header) {
            $this->Cell($widths[$i], 11, $header, 1, 0, 'C', true);
        }
        $this->Ln(11);

        $entries = $this->visit->intakeOutputs()->orderBy('recorded_at')->get();

        $this->SetFont('arial', '', 8);
        $this->SetTextColor(30, 30, 30);
        foreach ($entries as $entry) {
            /** @var VisitIntakeOutput $entry */
            $cells = [
                optional($entry->recorded_at)->format('H:i') ?? '',
                (string) $entry->feeding,
                (string) $entry->iv_fluid,
                (string) $entry->maintenance,
                (string) $entry->urine_output,
                (string) $entry->insensible_loss,
                (string) $entry->totalIntake(),
                (string) $entry->totalOutput(),
                (string) $entry->balance(),
            ];
            foreach ($cells as $i => $cell) {
                $this->Cell($widths[$i], 6.5, $cell, 1, 0, 'C');
            }
            $this->Ln(6.5);
        }

        $minRows = 13;
        for ($i = $entries->count(); $i < $minRows; $i++) {
            foreach ($widths as $width) {
                $this->Cell($width, 6.5, '', 1, 0, 'C');
            }
            $this->Ln(6.5);
        }
    }
}
