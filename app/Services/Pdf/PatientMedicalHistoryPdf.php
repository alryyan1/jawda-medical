<?php

namespace App\Services\Pdf;

use App\Models\Patient;
use App\Models\PatientMedicalHistory;
use TCPDF;

/**
 * PatientMedicalHistoryPdf
 *
 * Printable medical history sheet for a patient: allergies, past/drug/family/
 * social history, chronic condition flags, and systems review summaries.
 * The history record is resolved across the patient's whole File (see
 * Patient::siblingPatientIds), matching how it's stored/edited in the
 * Doctor Portal.
 */
class PatientMedicalHistoryPdf extends TCPDF
{
    protected Patient $patient;

    protected PatientMedicalHistory $history;

    protected float $pageUsableWidth;

    public function __construct(Patient $patient, PatientMedicalHistory $history)
    {
        parent::__construct('P', 'mm', 'A4', true, 'UTF-8', false);

        $this->patient = $patient;
        $this->history = $history;

        $this->setCreator('Jawda Medical');
        $this->setAuthor('Jawda Medical System');
        $this->setTitle('Medical History - '.$patient->name);

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
        $this->renderAllergiesSection();
        $this->renderTextSection('Drug History', $this->history->drug_history);
        $this->renderTextSection('Past Medical History', $this->history->past_medical_history);
        $this->renderTextSection('Past Surgical History', $this->history->past_surgical_history);
        $this->renderTextSection('Family History', $this->history->family_history);
        $this->renderTextSection('Social History', $this->history->social_history);
        $this->renderChronicFlagsSection();
        $this->renderSystemsReviewSection();
        $this->renderTextSection('Overall Care Plan', $this->history->overall_care_plan_summary);

        return $this->Output('MedicalHistory_'.$this->patient->id.'.pdf', 'S');
    }

    protected function renderIdentityBlock(): void
    {
        $font = 'arial';
        $col = $this->pageUsableWidth / 3;

        $this->SetFont($font, 'B', 16);
        $this->SetTextColor(41, 98, 255);
        $this->Cell($this->pageUsableWidth, 10, 'Medical History', 0, 1, 'C');
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

        $drawCell('Patient', $this->patient->name ?? '—');
        $drawCell('Age/Gender', ($this->patient->full_age ?? '—').' / '.($this->patient->gender ?? '—'));
        $drawCell('Print Date', now()->format('Y-m-d'));
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

    protected function renderAllergiesSection(): void
    {
        $this->sectionTitle('Allergies');

        $hasAllergies = ! empty($this->history->allergies);
        $this->SetFont('arial', 'B', 10);
        $this->SetTextColor($hasAllergies ? 200 : 150, $hasAllergies ? 30 : 150, $hasAllergies ? 30 : 150);
        $this->MultiCell($this->pageUsableWidth, 6, $this->history->allergies ?: 'No allergies recorded.', 0, 'L');
        $this->Ln(2);
    }

    protected function renderTextSection(string $title, ?string $value): void
    {
        $this->sectionTitle($title);

        $this->SetFont('arial', '', 9);
        $this->SetTextColor($value ? 40 : 150, $value ? 40 : 150, $value ? 40 : 150);
        $this->MultiCell($this->pageUsableWidth, 6, $value ?: 'No data recorded.', 0, 'L');
        $this->Ln(2);
    }

    protected function renderChronicFlagsSection(): void
    {
        $this->sectionTitle('Chronic Conditions');

        $flags = [
            'chronic_juandice' => 'Chronic Jaundice',
            'chronic_pallor' => 'Chronic Pallor',
            'chronic_clubbing' => 'Clubbing',
            'chronic_cyanosis' => 'Chronic Cyanosis',
            'chronic_edema_feet' => 'Feet Edema',
            'chronic_dehydration_tendency' => 'Recurrent Dehydration',
            'chronic_lymphadenopathy' => 'Lymphadenopathy',
            'chronic_peripheral_pulses_issue' => 'Peripheral Pulses Issue',
            'chronic_feet_ulcer_history' => 'Feet Ulcer History',
            'chronic_hypertension' => 'Hypertension',
            'chronic_diabetes' => 'Diabetes',
            'chronic_heart_disease' => 'Heart Disease',
            'chronic_ibs' => 'IBS',
        ];

        $active = collect($flags)->filter(fn ($label, $key) => (bool) $this->history->{$key});

        $this->SetFont('arial', '', 9);
        if ($active->isEmpty()) {
            $this->SetTextColor(150, 150, 150);
            $this->Cell($this->pageUsableWidth, 6, 'No chronic conditions recorded.', 0, 1, 'L');
        } else {
            $this->SetTextColor(40, 40, 40);
            foreach ($active as $label) {
                $this->Cell($this->pageUsableWidth, 6, '• '.$label, 0, 1, 'L');
            }
        }
        $this->Ln(2);
    }

    protected function renderSystemsReviewSection(): void
    {
        $this->sectionTitle('Systems Review');

        $systems = [
            'general_appearance_summary' => 'General Appearance',
            'skin_summary' => 'Skin',
            'head_neck_summary' => 'Head & Neck',
            'cardiovascular_summary' => 'Cardiovascular',
            'respiratory_summary' => 'Respiratory',
            'gastrointestinal_summary' => 'Gastrointestinal',
            'genitourinary_summary' => 'Genitourinary',
            'neurological_summary' => 'Neurological',
            'musculoskeletal_summary' => 'Musculoskeletal',
            'endocrine_summary' => 'Endocrine',
            'peripheral_vascular_summary' => 'Peripheral Vascular',
        ];

        $hasAny = false;
        foreach ($systems as $key => $label) {
            $value = $this->history->{$key};
            if (empty($value)) {
                continue;
            }
            $hasAny = true;
            $this->SetFont('arial', 'B', 9);
            $this->SetTextColor(44, 62, 80);
            $this->Cell($this->pageUsableWidth, 5, $label, 0, 1, 'L');
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(40, 40, 40);
            $this->MultiCell($this->pageUsableWidth, 5, $value, 0, 'L');
            $this->Ln(1);
        }

        if (! $hasAny) {
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(150, 150, 150);
            $this->Cell($this->pageUsableWidth, 6, 'No data recorded.', 0, 1, 'L');
        }
        $this->Ln(2);
    }
}
