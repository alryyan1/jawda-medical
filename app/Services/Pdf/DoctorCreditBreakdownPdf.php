<?php

namespace App\Services\Pdf;

use App\Models\DoctorShift;
use App\Models\Setting;
use TCPDF;

/**
 * DoctorCreditBreakdownPdf
 *
 * Generates a detailed breakdown of how the doctor's credit (حصة الطبيب)
 * was calculated for every visit in a given DoctorShift.
 */
class DoctorCreditBreakdownPdf extends TCPDF
{
    protected DoctorShift $doctorShift;
    protected ?int $filterVisitId;
    protected float $pageUsableWidth;

    public function __construct(DoctorShift $doctorShift, ?int $filterVisitId = null)
    {
        parent::__construct('L', 'mm', 'A4', true, 'UTF-8', false);

        $this->doctorShift   = $doctorShift;
        $this->filterVisitId = $filterVisitId;

        $this->setCreator('Jawda Medical');
        $this->setAuthor('Jawda Medical System');
        $title = $filterVisitId
            ? 'تفاصيل احتساب حصة الطبيب - زيارة #' . $filterVisitId
            : 'تفاصيل احتساب حصة الطبيب - مناوبة #' . $doctorShift->id;
        $this->setTitle($title);

        $this->setMargins(10, 35, 10);
        $this->setHeaderMargin(5);
        $this->setFooterMargin(10);
        $this->setAutoPageBreak(true, 15);

        $this->setLanguageArray([
            'a_meta_charset' => 'UTF-8',
            'a_meta_dir'     => 'rtl',
            'a_meta_language'=> 'ar',
            'w_page'         => 'صفحة',
        ]);
        $this->setRTL(true);

        $this->pageUsableWidth = $this->getPageWidth()
            - $this->getMargins()['left']
            - $this->getMargins()['right'];
    }

    public function Header()
    {
        $settings = Setting::first();
        $logoName = $settings?->header_base64;
        $logoPath = public_path();

        if ($logoName && file_exists($logoPath . '/' . $logoName)) {
            $this->Image($logoPath . '/' . $logoName, $this->getPageWidth() - 40, 5, 30);
        }

        $this->SetY(10);
        $this->setFont('arial', 'B', 16);
        $this->SetTextColor(41, 98, 255);
        $this->Cell($this->pageUsableWidth, 10, 'تفاصيل احتساب حصة الطبيب', 0, 1, 'C');

        $this->setFont('arial', '', 10);
        $this->SetTextColor(50, 50, 50);
        $colW = $this->pageUsableWidth / 3;
        $this->SetY(22);
        $this->SetFillColor(248, 249, 250);
        $this->SetDrawColor(220, 220, 220);
        $this->Cell($colW, 8, 'التاريخ: ' . $this->doctorShift->created_at->format('Y-m-d'), 'B', 0, 'R', true);
        $this->Cell($colW, 8, 'الطبيب: ' . ($this->doctorShift->doctor->name ?? '-'), 'B', 0, 'R', true);
        $this->Cell($colW, 8, 'رقم المناوبة: #' . $this->doctorShift->id, 'B', 1, 'R', true);
        $this->Ln(2);
    }

    public function Footer()
    {
        $this->SetY(-15);
        $this->SetDrawColor(220, 220, 220);
        $this->Line(10, $this->GetY(), $this->getPageWidth() - 10, $this->GetY());
        $this->Ln(2);
        $this->setFont('arial', 'I', 8);
        $this->SetTextColor(127, 140, 141);
        $this->Cell(0, 10, 'صفحة ' . $this->getAliasNumPage() . ' من ' . $this->getAliasNbPages(), 0, 0, 'C');
    }

    public function generate(): string
    {
        $this->AddPage();
        $this->renderLegend();
        $this->renderBreakdown();
        return $this->Output('doctor_credit_breakdown_' . $this->doctorShift->id . '.pdf', 'S');
    }

    protected function renderLegend()
    {
        $doctor = $this->doctorShift->doctor;

        $this->setFont('arial', 'B', 11);
        $this->SetFillColor(240, 244, 248);
        $this->SetDrawColor(189, 195, 199);
        $this->SetTextColor(44, 62, 80);

        $w = $this->pageUsableWidth / 4;

        $this->Cell($w, 9, 'نسبة نقدي: ' . $doctor->cash_percentage . '%', 1, 0, 'C', true);
        $this->Cell($w, 9, 'نسبة تأمين: ' . $doctor->company_percentage . '%', 1, 0, 'C', true);
        $this->Cell($w, 9, 'يبدأ الاحتساب من الزيارة: #' . $doctor->start, 1, 0, 'C', true);

        $totalCredit = $this->doctorShift->doctor_credit_cash() + $this->doctorShift->doctor_credit_company();
        $this->SetFillColor(39, 174, 96);
        $this->SetTextColor(255, 255, 255);
        $this->Cell($w, 9, 'إجمالي الحصة: ' . number_format($totalCredit, 2), 1, 1, 'C', true);

        $this->SetTextColor(44, 62, 80);
        $this->Ln(5);
    }

    protected function renderBreakdown()
    {
        $doctor = $this->doctorShift->doctor;
        $visits = $this->doctorShift->visits
            ->reverse()
            ->filter(fn($v) => $v->only_lab == 0)
            ->when($this->filterVisitId, fn($col) => $col->where('id', $this->filterVisitId));

        $disableServiceCheck  = (bool) optional(Setting::first())->disable_doctor_service_check;
        $individualServiceIds = $doctor->specificServices()->pluck('service_id')->toArray();

        // Column widths (landscape A4 usable ≈ 257 mm)
        $wService = 55;
        $wType    = 22;
        $wBase    = 22;
        $wCost    = 22;
        $wRate    = 28;
        $wCredit  = 21;

        $visitIndex = 0;
        $grandTotal = 0.0;

        foreach ($visits as $visit) {
            $visitIndex++;
            $isInsurance = (bool) $visit->patient->company_id;

            // ── Visit header bar ──────────────────────────────────────
            $this->setFont('arial', 'B', 10);
            $this->SetFillColor(230, 235, 240);
            $this->SetTextColor(44, 62, 80);
            $this->SetDrawColor(200, 205, 210);

            $label = '#' . $visitIndex . ' - ' . ($visit->patient->name ?? '-');
            if ($isInsurance) {
                $label .= '  [تأمين: ' . ($visit->patient->company->name ?? '') . ']';
            }
            $this->Cell($this->pageUsableWidth, 8, $label, 1, 1, 'R', true);

            // ── Column headers ────────────────────────────────────────
            $this->setFont('arial', 'B', 8);
            $this->SetFillColor(245, 247, 250);

            $this->Cell($wService, 7, 'اسم الخدمة',     1, 0, 'C', true);
            $this->Cell($wType,    7, 'نوع المريض',     1, 0, 'C', true);
            $this->Cell($wBase,    7, 'الأساس',         1, 0, 'C', true);
            $this->Cell($wCost,    7, 'مصروفات',        1, 0, 'C', true);
            $this->Cell($wRate,    7, 'النسبة / المبلغ الثابت', 1, 0, 'C', true);
            $this->Cell($wCredit,  7, 'الحصة',          1, 1, 'C', true);

            // ── Service rows ──────────────────────────────────────────
            $this->setFont('arial', '', 8);
            $visitCredit = 0.0;
            $hasServices = false;

            foreach ($visit->requestedServices as $rs) {
                if ($rs->doctor_id !== $doctor->id) continue;
                if (!$disableServiceCheck && !in_array($rs->service_id, $individualServiceIds)) continue;
                if ($rs->returnedRefunds->isNotEmpty()) continue; // skip returned

                $hasServices = true;

                $serviceName = $rs->service?->name ?? 'خدمة غير معروفة';
                $totalCost   = $rs->getTotalCostsForDoctor($doctor);

                if ($isInsurance) {
                    // Company formula: (price × count - costs) × company_percentage / 100
                    $gross    = (float)$rs->price * (int)$rs->count;
                    $base     = $gross - $totalCost;
                    $rateLabel = $doctor->company_percentage . '% (تأمين)';
                    $credit   = $base * $doctor->company_percentage / 100;
                    $typeLabel = 'تأمين';
                } else {
                    // Cash – check specific service settings first
                    $doctorService = $doctor->specificServices
                        ->first(fn($s) => $s->pivot->service_id === $rs->service_id);

                    $pivot = $doctorService?->pivot;

                    if ($pivot && ($pivot->fixed ?? 0) > 0 && ($pivot->percentage ?? 0) == 0) {
                        // Fixed rate
                        $gross     = (float)$rs->amount_paid;
                        $base      = $gross;
                        $rateLabel = ' (ثابت × ' . $rs->count . ')'.number_format($pivot->fixed, 2) ;
                        $credit    = $pivot->fixed * $rs->count;
                        $totalCost = 0; // cost not deducted for fixed
                    } elseif ($pivot && ($pivot->percentage ?? 0) > 0) {
                        // Specific percentage
                        $gross     = (float)$rs->amount_paid;
                        $base      = $gross;
                        $rateLabel =  '% (خاص)'. $pivot->percentage ;
                        $credit    = $gross * $pivot->percentage / 100;
                        $totalCost = 0; // cost not deducted in this branch
                    } else {
                        // Default cash percentage
                        $gross     = (float)$rs->amount_paid;
                        $base      = $gross - $totalCost;
                        $rateLabel =  '% (افتراضي)'. $doctor->cash_percentage ;
                        $credit    = $base * $doctor->cash_percentage / 100;
                    }
                    $typeLabel = 'نقدي';
                }

                $visitCredit += $credit;

                $this->SetFillColor(255, 255, 255);
                $this->SetTextColor(40, 40, 40);
                $this->Cell($wService, 6, $serviceName,                    'LRB', 0, 'R', true);
                $this->Cell($wType,    6, $typeLabel,                       'LRB', 0, 'C', true);
                $this->Cell($wBase,    6, number_format($gross, 2),         'LRB', 0, 'C', true);
                $this->Cell($wCost,    6, number_format($totalCost, 2),     'LRB', 0, 'C', true);
                $this->Cell($wRate,    6, $rateLabel,                       'LRB', 0, 'C', true);
                $this->Cell($wCredit,  6, number_format($credit, 2),        'LRB', 1, 'C', true);
            }

            if (!$hasServices) {
                $this->SetTextColor(150, 150, 150);
                $this->Cell($this->pageUsableWidth, 6, 'لا توجد خدمات مؤهلة لاحتساب حصة الطبيب', 1, 1, 'C', false);
            }

            // ── Visit subtotal ────────────────────────────────────────
            $this->setFont('arial', 'B', 9);
            $this->SetFillColor(230, 235, 240);
            $this->SetTextColor(44, 62, 80);
            $usedWidth = $wService + $wType + $wBase + $wCost + $wRate;
            $this->Cell($usedWidth, 7, 'مجموع حصة الطبيب لهذه الزيارة', 1, 0, 'R', true);
            $this->Cell($wCredit,   7, number_format($visitCredit, 2),    1, 1, 'C', true);

            $grandTotal += $visitCredit;

            $this->Ln(3);

            // page break guard
            if ($this->GetY() + 40 > $this->getPageHeight() - 15) {
                $this->AddPage();
            }
        }

        // ── Grand total ───────────────────────────────────────────────
        $this->Ln(2);
        $this->setFont('arial', 'B', 12);
        $this->SetFillColor(39, 174, 96);
        $this->SetTextColor(255, 255, 255);
        $usedWidth = $wService + $wType + $wBase + $wCost + $wRate;
        // $this->Cell($usedWidth, 10, 'إجمالي حصة الطبيب للمناوبة كاملة', 1, 0, 'R', true);
        // $this->Cell($wCredit,   10, number_format($grandTotal, 2),       1, 1, 'C', true);
    }
}
