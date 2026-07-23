<?php

namespace App\Services\Pdf;

use App\Models\DoctorVisit;
use TCPDF;

/**
 * VisitSummaryPdf
 *
 * Generates a single printable "medical file" summary for one doctor visit:
 * identity block, diagnosis/clinical note, attachments list, and visit notes.
 * Prescriptions and vitals sections are appended once those relations exist
 * (Phase 2 / Phase 3 of the Doctor Portal EMR rollout).
 */
class VisitSummaryPdf extends TCPDF
{
    protected DoctorVisit $visit;

    protected float $pageUsableWidth;

    public function __construct(DoctorVisit $visit)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->visit = $visit;

        $this->setCreator('Jawda Medical');
        $this->setAuthor('Jawda Medical System');
        $this->setTitle('ملخص الزيارة #'.$visit->id);

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
        $this->Cell(0, 5, 'صفحة '.$this->getAliasNumPage().' من '.$this->getAliasNbPages(), 0, 0, 'C');
    }

    public function generate(): string
    {
        $this->setRTL(true);
        $this->AddPage();
        $this->renderIdentityBlock();
        $this->renderDiagnosisSection();
        $this->renderVitalsSection();
        $this->renderPrescriptionsSection();
        $this->renderAttachmentsSection();
        $this->renderNotesSection();

        return $this->Output('VisitSummary_'.$this->visit->id.'.pdf', 'S');
    }

    protected function renderIdentityBlock(): void
    {
        $patient = $this->visit->patient;
        $doctor = $this->visit->doctor;
        $font = 'arial';
        $col = $this->pageUsableWidth / 3;

        $this->SetFont($font, 'B', 16);
        $this->SetTextColor(41, 98, 255);
        $this->Cell($this->pageUsableWidth, 10, 'ملخص الزيارة الطبية', 0, 1, 'C');
        $this->Ln(2);

        $drawCell = function (string $label, string $value) use ($col, $font) {
            $x = $this->GetX();
            $y = $this->GetY();
            $this->Rect($x, $y, $col, 12, 'D');
            $this->SetFont($font, '', 7);
            $this->SetTextColor(120, 120, 120);
            $this->SetXY($x, $y + 1.5);
            $this->Cell($col - 2, 4, strtoupper($label), 0, 0, 'R');
            $this->SetFont($font, 'B', 9);
            $this->SetTextColor(30, 30, 30);
            $this->SetXY($x, $y + 6);
            $this->Cell($col - 2, 5, $value, 0, 0, 'R');
            $this->SetXY($x + $col, $y);
        };

        $drawCell('المريض', $patient?->name ?? '—');
        $drawCell('رقم الزيارة', '#'.$this->visit->id);
        $drawCell('الطبيب', $doctor?->name ?? '—');
        $this->Ln(12);

        $drawCell('العمر/الجنس', ($patient?->full_age ?? '—').' / '.($patient?->gender ?? '—'));
        $drawCell('تاريخ الزيارة', optional($this->visit->visit_date)->format('Y-m-d') ?? '—');
        $drawCell('الحالة', $this->visit->status ?? '—');
        $this->Ln(16);
    }

    protected function sectionTitle(string $title): void
    {
        $this->SetFont('arial', 'B', 11);
        $this->SetFillColor(240, 244, 248);
        $this->SetTextColor(44, 62, 80);
        $this->SetDrawColor(189, 195, 199);
        $this->Cell($this->pageUsableWidth, 8, $title, 1, 1, 'R', true);
        $this->Ln(1);
    }

    protected function renderDiagnosisSection(): void
    {
        $this->sectionTitle('التشخيص / الملاحظة الطبية');

        $this->SetFont('arial', '', 10);
        $this->SetTextColor(0, 0, 0);

        $diagnosis = $this->visit->diagnosis;
        $html = $diagnosis?->diagnosis ?: '<p>لا يوجد تشخيص مسجل.</p>';
        $html = '<div style="direction:rtl; text-align:right;">'.$html.'</div>';
        $this->writeHTML($html, true, false, true, false, '');
        $this->Ln(4);
    }

    protected function renderVitalsSection(): void
    {
        $this->sectionTitle('العلامات الحيوية');

        $vitals = $this->visit->vitals;

        if ($vitals->isEmpty()) {
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(150, 150, 150);
            $this->Cell($this->pageUsableWidth, 6, 'لم يتم تسجيل علامات حيوية لهذه الزيارة.', 0, 1, 'R');
            $this->Ln(2);

            return;
        }

        $wLabel = $this->pageUsableWidth * 0.3;
        $wValue = $this->pageUsableWidth - $wLabel;

        $rows = [
            'ضغط الدم' => $vitals->first()->blood_pressure_systolic
                ? $vitals->first()->blood_pressure_systolic.'/'.$vitals->first()->blood_pressure_diastolic.' mmHg'
                : null,
            'معدل ضربات القلب' => $vitals->first()->heart_rate ? $vitals->first()->heart_rate.' bpm' : null,
            'درجة الحرارة' => $vitals->first()->temperature ? $vitals->first()->temperature.' °C' : null,
            'تشبع الأكسجين' => $vitals->first()->spo2 ? $vitals->first()->spo2.'%' : null,
            'الوزن' => $vitals->first()->weight ? $vitals->first()->weight.' kg' : null,
        ];

        $this->SetFont('arial', '', 9);
        $this->SetTextColor(40, 40, 40);
        foreach ($rows as $label => $value) {
            if ($value === null) {
                continue;
            }
            $this->Cell($wLabel, 6, $label, 0, 0, 'R');
            $this->Cell($wValue, 6, $value, 0, 1, 'R');
        }
        $this->Ln(2);
    }

    protected function renderPrescriptionsSection(): void
    {
        $this->sectionTitle('الوصفات الطبية');

        $prescriptions = $this->visit->prescriptions;

        if ($prescriptions->isEmpty()) {
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(150, 150, 150);
            $this->Cell($this->pageUsableWidth, 6, 'لا توجد وصفات طبية لهذه الزيارة.', 0, 1, 'R');
            $this->Ln(2);

            return;
        }

        $this->SetFont('arial', '', 9);
        $this->SetTextColor(40, 40, 40);
        foreach ($prescriptions as $prescription) {
            foreach ($prescription->items as $item) {
                $label = $item->medication_name;
                $details = collect([$item->dosage, $item->frequency, $item->duration])
                    ->filter()
                    ->implode(' — ');
                $this->Cell($this->pageUsableWidth, 6, trim($label.($details ? ' ('.$details.')' : '')), 0, 1, 'R');
            }
        }
        $this->Ln(2);
    }

    protected function renderAttachmentsSection(): void
    {
        $this->sectionTitle('المرفقات');

        $attachments = $this->visit->attachments;

        if ($attachments->isEmpty()) {
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(150, 150, 150);
            $this->Cell($this->pageUsableWidth, 6, 'لا توجد مرفقات لهذه الزيارة.', 0, 1, 'R');
            $this->Ln(2);

            return;
        }

        $this->SetFont('arial', '', 9);
        $this->SetTextColor(40, 40, 40);
        foreach ($attachments as $attachment) {
            $label = ($attachment->title ?: $attachment->original_filename).' — '.$attachment->category;
            $this->Cell($this->pageUsableWidth, 6, $label, 0, 1, 'R');
        }
        $this->Ln(2);
    }

    protected function renderNotesSection(): void
    {
        $this->sectionTitle('ملاحظات الزيارة');

        $this->SetFont('arial', '', 9);
        $this->SetTextColor(40, 40, 40);
        $notes = $this->visit->visit_notes ?: $this->visit->reason_for_visit ?: 'لا توجد ملاحظات.';
        $this->MultiCell($this->pageUsableWidth, 6, $notes, 0, 'R');
    }
}
