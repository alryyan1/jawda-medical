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
        $this->setTitle('التاريخ المرضي - '.$patient->name);

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
        $this->renderAllergiesSection();
        $this->renderTextSection('التاريخ الدوائي', $this->history->drug_history);
        $this->renderTextSection('التاريخ المرضي السابق', $this->history->past_medical_history);
        $this->renderTextSection('التاريخ الجراحي السابق', $this->history->past_surgical_history);
        $this->renderTextSection('التاريخ العائلي', $this->history->family_history);
        $this->renderTextSection('التاريخ الاجتماعي', $this->history->social_history);
        $this->renderChronicFlagsSection();
        $this->renderSystemsReviewSection();
        $this->renderTextSection('خطة الرعاية الشاملة', $this->history->overall_care_plan_summary);

        return $this->Output('MedicalHistory_'.$this->patient->id.'.pdf', 'S');
    }

    protected function renderIdentityBlock(): void
    {
        $font = 'arial';
        $col = $this->pageUsableWidth / 3;

        $this->SetFont($font, 'B', 16);
        $this->SetTextColor(41, 98, 255);
        $this->Cell($this->pageUsableWidth, 10, 'التاريخ المرضي', 0, 1, 'C');
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

        $drawCell('المريض', $this->patient->name ?? '—');
        $drawCell('العمر/الجنس', ($this->patient->full_age ?? '—').' / '.($this->patient->gender ?? '—'));
        $drawCell('تاريخ الطباعة', now()->format('Y-m-d'));
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

    protected function renderAllergiesSection(): void
    {
        $this->sectionTitle('الحساسية');

        $hasAllergies = ! empty($this->history->allergies);
        $this->SetFont('arial', 'B', 10);
        $this->SetTextColor($hasAllergies ? 200 : 150, $hasAllergies ? 30 : 150, $hasAllergies ? 30 : 150);
        $this->MultiCell($this->pageUsableWidth, 6, $this->history->allergies ?: 'لا توجد حساسية مسجلة.', 0, 'R');
        $this->Ln(2);
    }

    protected function renderTextSection(string $title, ?string $value): void
    {
        $this->sectionTitle($title);

        $this->SetFont('arial', '', 9);
        $this->SetTextColor($value ? 40 : 150, $value ? 40 : 150, $value ? 40 : 150);
        $this->MultiCell($this->pageUsableWidth, 6, $value ?: 'لا توجد بيانات مسجلة.', 0, 'R');
        $this->Ln(2);
    }

    protected function renderChronicFlagsSection(): void
    {
        $this->sectionTitle('الحالات المزمنة');

        $flags = [
            'chronic_juandice' => 'يرقان مزمن',
            'chronic_pallor' => 'شحوب مزمن',
            'chronic_clubbing' => 'تضخم أصابع',
            'chronic_cyanosis' => 'زرقة مزمنة',
            'chronic_edema_feet' => 'وذمة القدمين',
            'chronic_dehydration_tendency' => 'جفاف متكرر',
            'chronic_lymphadenopathy' => 'اعتلال الغدد الليمفاوية',
            'chronic_peripheral_pulses_issue' => 'اضطراب النبض المحيطي',
            'chronic_feet_ulcer_history' => 'تاريخ قرحة القدم',
        ];

        $active = collect($flags)->filter(fn ($label, $key) => (bool) $this->history->{$key});

        $this->SetFont('arial', '', 9);
        if ($active->isEmpty()) {
            $this->SetTextColor(150, 150, 150);
            $this->Cell($this->pageUsableWidth, 6, 'لا توجد حالات مزمنة مسجلة.', 0, 1, 'R');
        } else {
            $this->SetTextColor(40, 40, 40);
            foreach ($active as $label) {
                $this->Cell($this->pageUsableWidth, 6, '• '.$label, 0, 1, 'R');
            }
        }
        $this->Ln(2);
    }

    protected function renderSystemsReviewSection(): void
    {
        $this->sectionTitle('مراجعة الأجهزة');

        $systems = [
            'general_appearance_summary' => 'الحالة العامة',
            'skin_summary' => 'الجلد',
            'head_neck_summary' => 'الرأس والرقبة',
            'cardiovascular_summary' => 'القلب والأوعية الدموية',
            'respiratory_summary' => 'الجهاز التنفسي',
            'gastrointestinal_summary' => 'الجهاز الهضمي',
            'genitourinary_summary' => 'الجهاز البولي التناسلي',
            'neurological_summary' => 'الجهاز العصبي',
            'musculoskeletal_summary' => 'الجهاز العضلي الهيكلي',
            'endocrine_summary' => 'الغدد الصماء',
            'peripheral_vascular_summary' => 'الأوعية الدموية الطرفية',
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
            $this->Cell($this->pageUsableWidth, 5, $label, 0, 1, 'R');
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(40, 40, 40);
            $this->MultiCell($this->pageUsableWidth, 5, $value, 0, 'R');
            $this->Ln(1);
        }

        if (! $hasAny) {
            $this->SetFont('arial', '', 9);
            $this->SetTextColor(150, 150, 150);
            $this->Cell($this->pageUsableWidth, 6, 'لا توجد بيانات مسجلة.', 0, 1, 'R');
        }
        $this->Ln(2);
    }
}
