<?php

namespace App\Services\Pdf;

use TCPDF;
use App\Models\Admission;
use App\Models\Setting;
use Illuminate\Support\Collection;

/**
 * TCPDF report for the admissions list (same columns as AdmissionsListPage table).
 */
class AdmissionsListReport
{
    private TCPDF $pdf;

    /** @var Collection<int, Admission> */
    private Collection $admissions;

    /** @var array<int, array{total_debits: float, total_credits: float, balance: float}> */
    private array $balances;

    private Setting $settings;

    /**
     * @param  Collection<int, Admission>  $admissions  Admissions with patient, ward, room, bed, specialistDoctor loaded
     * @param  array<int, array{total_debits: float, total_credits: float, balance: float}>  $balances  Balance data keyed by admission id
     */
    public function __construct(Collection $admissions, array $balances)
    {
        $this->admissions = $admissions;
        $this->balances = $balances;
        $this->settings = Setting::instance();
        $this->initializePdf();
    }

    private function initializePdf(): void
    {
        $this->pdf = new TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(10, 15, 10);
        $this->pdf->SetAutoPageBreak(true, 12);
        $this->pdf->setRTL(true);
        $this->pdf->SetFont('dejavusans', '', 8);
        $this->pdf->AddPage();
    }

    public function generate(): string
    {
        $this->renderTitle();
        $this->renderTable();
        return $this->pdf->Output('', 'S');
    }

    private function renderTitle(): void
    {
        $hospitalName = $this->settings->hospital_name ?? 'المستشفى';
        $this->pdf->SetFont('dejavusans', 'B', 14);
        $this->pdf->Cell(0, 8, $hospitalName, 0, 1, 'C');
        $this->pdf->SetFont('dejavusans', 'B', 12);
        $this->pdf->Cell(0, 6, 'قائمة التنويمات', 0, 1, 'C');
        $this->pdf->SetFont('dejavusans', '', 8);
        $this->pdf->Cell(0, 5, 'تاريخ التقرير: ' . now()->format('Y-m-d H:i'), 0, 1, 'C');
        $this->pdf->Ln(3);
    }

    private function renderTable(): void
    {
        $w = [12, 30, 26, 20, 16, 16, 24, 14, 24, 24, 24, 20];
        $headers = [
            'رقم التنويم',
            'المريض',
            'الطبيب الأخصائي',
            'القسم',
            'الغرفة',
            'السرير',
            'تاريخ التنويم',
            'أيام الإقامة',
            'إجمالي المدين',
            'إجمالي الدائن',
            'الرصيد',
            'الحالة',
        ];

        $this->pdf->SetFont('dejavusans', 'B', 7);
        $this->pdf->SetFillColor(240, 240, 240);

        foreach ($headers as $i => $h) {
            $this->pdf->Cell($w[$i], 7, $h, 1, 0, 'C', true);
        }
        $this->pdf->Ln();

        $this->pdf->SetFont('dejavusans', '', 7);
        $fill = false;
        $statusLabels = [
            'admitted' => 'مقيم',
            'discharged' => 'مخرج',
            'transferred' => 'منقول',
        ];

        foreach ($this->admissions as $admission) {
            if ($this->pdf->GetY() > 185) {
                $this->pdf->AddPage('L');
                foreach ($headers as $i => $h) {
                    $this->pdf->Cell($w[$i], 7, $h, 1, 0, 'C', true);
                }
                $this->pdf->Ln();
                $this->pdf->SetFont('dejavusans', '', 7);
            }

            $bal = $this->balances[$admission->id] ?? ['total_debits' => 0, 'total_credits' => 0, 'balance' => 0];
            $totalDebits = $bal['total_debits'];
            $totalCredits = $bal['total_credits'];
            $balance = $bal['balance'];

            $this->pdf->SetFillColor($fill ? 248 : 255, $fill ? 248 : 255, $fill ? 248 : 255);

            $this->pdf->Cell($w[0], 6, (string) $admission->id, 1, 0, 'C', true);
            $this->pdf->Cell($w[1], 6, $this->text(optional($admission->patient)->name ?? '-'), 1, 0, 'R', true);
            $this->pdf->Cell($w[2], 6, $this->text(optional($admission->specialistDoctor)->name ?? '-'), 1, 0, 'R', true);
            $this->pdf->Cell($w[3], 6, $this->text(optional($admission->ward)->name ?? '-'), 1, 0, 'R', true);
            $this->pdf->Cell($w[4], 6, $this->text(optional($admission->room)->room_number ?? '-'), 1, 0, 'C', true);
            $this->pdf->Cell($w[5], 6, $this->text(optional($admission->bed)->bed_number ?? '-'), 1, 0, 'C', true);
            $this->pdf->Cell($w[6], 6, $admission->admission_date ? \Carbon\Carbon::parse($admission->admission_date)->format('Y-m-d') : '-', 1, 0, 'C', true);
            $this->pdf->Cell($w[7], 6, $admission->days_admitted !== null ? (string) $admission->days_admitted : '-', 1, 0, 'C', true);
            $this->pdf->Cell($w[8], 6, number_format($totalDebits, 2), 1, 0, 'R', true);
            $this->pdf->Cell($w[9], 6, number_format($totalCredits, 2), 1, 0, 'R', true);
            $this->pdf->Cell($w[10], 6, number_format($balance, 2), 1, 0, 'R', true);
            $this->pdf->Cell($w[11], 6, $statusLabels[$admission->status] ?? $admission->status, 1, 1, 'C', true);

            $fill = ! $fill;
        }

        if ($this->admissions->isEmpty()) {
            $this->pdf->SetFillColor(255, 255, 255);
            $this->pdf->Cell(array_sum($w), 10, 'لا توجد تنويمات', 1, 1, 'C', true);
        }
    }

    private function text(?string $s): string
    {
        return $s !== null && $s !== '' ? $s : '-';
    }
}
