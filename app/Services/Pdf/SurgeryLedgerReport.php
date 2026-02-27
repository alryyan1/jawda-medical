<?php

namespace App\Services\Pdf;

use App\Models\RequestedSurgery;
use Carbon\Carbon;
use App\Models\Setting;

class SurgeryLedgerReport extends MyCustomTCPDF
{
    protected RequestedSurgery $requestedSurgery;
    protected $ledgerData;

    public function __construct(RequestedSurgery $requestedSurgery, $ledgerData)
    {
        parent::__construct(
            'كشف حساب عملية جراحية',
            null,
            'P',
            'mm',
            'A4',
            true,
            'UTF-8',
            false
        );

        $this->requestedSurgery = $requestedSurgery->load(['surgery', 'doctor', 'admission.patient']);
        $this->ledgerData = $ledgerData;

        $this->SetMargins(15, 10, 15);
        $this->SetHeaderMargin(5);
        $this->SetFooterMargin(10);
        $this->SetAutoPageBreak(true, 15);
    }

    public function Header()
    {
        /** @var Setting $settings */
        $settings = Setting::first();
        $logo_name = $settings?->header_base64;
        $logo_path = public_path();
        $contentWidth = $this->getPageWidth() - $this->getMargins()['left'] - $this->getMargins()['right'];

        if ($logo_name) {
            $this->Image($logo_path . '/' . $logo_name, $this->getMargins()['left'], 5, $contentWidth, 30, '', '', 'T', false, 300, '', false, false, 0, false, false, false);
        }

        $this->SetY(40);
        $this->SetFont('arial', 'B', 16);
        $this->Cell(0, 10, 'كشف حساب عملية جراحية (Ledger)', 0, 1, 'C');
    }

    public function generate()
    {
        $this->AddPage();

        $lMargin = $this->getMargins()['left'];
        $contentWidth = $this->getPageWidth() - $lMargin - $this->getMargins()['right'];

        $patient = $this->requestedSurgery->admission->patient;
        $surgery = $this->requestedSurgery->surgery;

        $this->SetY(55);
        $this->SetFont('arial', 'B', 11);

        // --- Info Box ---
        $this->SetFillColor(248, 249, 250);
        $this->SetDrawColor(220, 224, 228);
        $this->RoundedRect($lMargin, $this->GetY(), $contentWidth, 25, 2, '1111', 'DF');

        $this->SetY($this->GetY() + 3);
        $labelWidth = 25;
        $colWidth = $contentWidth / 2;

        $this->Cell($labelWidth, 7, 'المريض:', 0, 0, 'R');
        $this->SetFont('arial', 'B', 12);
        $this->Cell($colWidth - $labelWidth, 7, $patient->name, 0, 0, 'R');

        $this->SetFont('arial', 'B', 11);
        $this->Cell($labelWidth, 7, 'التاريخ:', 0, 0, 'R');
        $this->SetFont('arial', '', 11);
        $this->Cell(0, 7, Carbon::now()->format('Y/m/d'), 0, 1, 'R');

        $this->SetFont('arial', 'B', 11);
        $this->Cell($labelWidth, 7, 'العملية:', 0, 0, 'R');
        $this->SetFont('arial', 'B', 12);
        $this->Cell($colWidth - $labelWidth, 7, $surgery->name, 0, 0, 'R');

        $this->SetFont('arial', 'B', 11);
        $this->Cell($labelWidth, 7, 'الطبيب:', 0, 0, 'R');
        $this->SetFont('arial', '', 11);
        $this->Cell(0, 7, $this->requestedSurgery->doctor?->name ?? '—', 0, 1, 'R');

        $this->Ln(10);

        // --- Transactions Table ---
        $headers = ['التاريخ', 'البيان (الوصف)', 'مدين (+)', 'دائن (-)', 'الرصيد'];
        $widths = [$contentWidth * 0.15, $contentWidth * 0.45, $contentWidth * 0.13, $contentWidth * 0.13, $contentWidth * 0.14];
        $alignments = ['C', 'R', 'C', 'C', 'C'];

        $this->SetTableDefinition($headers, $widths, $alignments);
        $this->DrawTableHeader();

        $runningBalance = 0;
        $fill = false;

        foreach ($this->ledgerData['transactions'] as $tx) {
            $amount = (float)$tx->amount;
            if ($tx->type === 'debit') {
                $runningBalance += $amount;
                $debitStr = number_format($amount, 0);
                $creditStr = '';
            } else {
                $runningBalance -= $amount;
                $debitStr = '';
                $creditStr = number_format($amount, 0);
            }

            $rowData = [
                Carbon::parse($tx->created_at)->format('Y/m/d'),
                $tx->description,
                $debitStr,
                $creditStr,
                number_format($runningBalance, 0)
            ];

            $this->DrawTableRow($rowData, null, null, $fill, 8, 10);
            $fill = !$fill;
        }

        // --- Summary Table ---
        $this->Ln(5);
        $this->SetFont('arial', 'B', 12);
        $summary = $this->ledgerData['summary'];

        $this->SetFillColor(240, 240, 240);
        $this->Cell($widths[0] + $widths[1], 10, 'الإجمالي المتبقي (Balance):', 1, 0, 'R', true);
        $this->SetTextColor(200, 0, 0);
        $this->Cell($widths[2] + $widths[3] + $widths[4], 10, number_format($summary['balance'], 0) . ' SDG', 1, 1, 'C', true);

        $this->SetTextColor(0, 0, 0);
        $this->Ln(15);
        $this->SetFont('arial', 'B', 11);
        $this->Cell($contentWidth / 2, 5, 'توقيع المحاسب', 0, 0, 'C');
        $this->Cell($contentWidth / 2, 5, 'توقيع المدير', 0, 1, 'C');

        return $this->Output('surgery_ledger_' . $this->requestedSurgery->id . '.pdf', 'S');
    }
}
