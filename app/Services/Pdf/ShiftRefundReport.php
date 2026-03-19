<?php

namespace App\Services\Pdf;

use TCPDF;
use App\Models\Shift;
use App\Models\ReturnedLabRequest;
use App\Models\ReturnedRequestedService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ShiftRefundReport
{
    private TCPDF $pdf;
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
        $this->initializePdf();
    }

    private function initializePdf(): void
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->setFontSubsetting(true);
        $this->pdf->SetFont('arial', 'B', 10);
        
        $this->pdf->SetCreator('Jawda Medical System');
        $this->pdf->SetAuthor('Jawda Medical System');
        $this->pdf->SetTitle($this->data['title']);
        $this->pdf->SetSubject('Shift Refund Report');

        $this->pdf->SetMargins(15, 20, 15);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetFooterMargin(10);
        $this->pdf->SetAutoPageBreak(TRUE, 25);
        $this->pdf->setRTL(true);
        $this->pdf->AddPage();
    }

    public function generate(): string
    {
        $this->renderTitle();
        $this->renderShiftInfo();
        $this->renderRefundsTable();
        $this->renderFooter();

        return $this->pdf->Output('', 'S');
    }

    private function renderTitle(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 15;
        $usableWidth = $pageWidth - (2 * $margin);

        $this->pdf->SetFont('arial', 'B', 16);
        $this->pdf->SetTextColor(45, 55, 72);
        
        $this->pdf->Ln(4);
        $this->pdf->Cell(0, 10, $this->data['title'], 0, 1, 'C');
        
        $this->pdf->SetDrawColor(45, 55, 72);
        $this->pdf->SetLineWidth(0.5);
        $lineY = $this->pdf->GetY();
        $this->pdf->Line($pageWidth / 2 - 40, $lineY, $pageWidth / 2 + 40, $lineY);
        $this->pdf->Ln(10);
    }

    private function renderShiftInfo(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 15;
        $usableWidth = $pageWidth - (2 * $margin);

        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->SetTextColor(45, 55, 72);
        
        $this->pdf->Cell($usableWidth * 0.5, 8, 'الوردية: ' . $this->data['shiftName'], 0, 0, 'R');
        $this->pdf->Cell($usableWidth * 0.5, 8, 'تاريخ الإنشاء: ' . $this->data['printDate'], 0, 1, 'L');
        
        $this->pdf->Ln(10);
    }

    private function renderRefundsTable(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 15;
        $usableWidth = $pageWidth - (2 * $margin);

        $this->pdf->SetDrawColor(220, 223, 230);
        $this->pdf->SetLineWidth(0.3);
        $this->pdf->SetFont('arial', 'B', 10);
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->SetTextColor(0, 0, 0);

        $this->pdf->Cell($usableWidth * 0.25, 8, 'المريض', 1, 0, 'C', true);
        $this->pdf->Cell($usableWidth * 0.15, 8, 'النوع', 1, 0, 'C', true);
        $this->pdf->Cell($usableWidth * 0.15, 8, 'المبلغ', 1, 0, 'C', true);
        $this->pdf->Cell($usableWidth * 0.15, 8, 'الطريقة', 1, 0, 'C', true);
        $this->pdf->Cell($usableWidth * 0.3, 8, 'السبب', 1, 1, 'C', true);

        $this->pdf->SetFont('arial', 'B', 9);
        $rowFill = false;

        $totalAmount = 0;

        if (empty($this->data['refunds'])) {
            $this->pdf->SetFont('arial', 'B', 10);
            $this->pdf->SetTextColor(107, 114, 128);
            $this->pdf->Cell($usableWidth, 10, 'لا توجد استردادات في هذه الوردية', 1, 1, 'C');
            $this->pdf->SetTextColor(0, 0, 0);
        } else {
            foreach ($this->data['refunds'] as $refund) {
                if ($rowFill) {
                    $this->pdf->SetFillColor(252, 252, 253);
                } else {
                    $this->pdf->SetFillColor(255, 255, 255);
                }

                $totalAmount += $refund['amount'];

                $this->pdf->Cell($usableWidth * 0.25, 7, $refund['patient_name'], 1, 0, 'R', $rowFill);
                $this->pdf->Cell($usableWidth * 0.15, 7, $refund['type'], 1, 0, 'C', $rowFill);
                $this->pdf->Cell($usableWidth * 0.15, 7, number_format($refund['amount'], 2), 1, 0, 'C', $rowFill);
                $this->pdf->Cell($usableWidth * 0.15, 7, $refund['payment_method'], 1, 0, 'C', $rowFill);
                $this->pdf->Cell($usableWidth * 0.3, 7, $refund['reason'], 1, 1, 'R', $rowFill);
                
                $rowFill = !$rowFill;
            }

            // Totals Row
            $this->pdf->SetFont('arial', 'B', 11);
            $this->pdf->SetFillColor(240, 245, 250);
            $this->pdf->SetTextColor(45, 55, 72);
            $this->pdf->Cell($usableWidth * 0.4, 8, 'الإجمالي', 1, 0, 'C', true);
            $this->pdf->Cell($usableWidth * 0.15, 8, number_format($totalAmount, 2), 1, 0, 'C', true);
            $this->pdf->Cell($usableWidth * 0.45, 8, '', 1, 1, 'C', true);
            $this->pdf->SetTextColor(0, 0, 0);
        }
    }

    private function renderFooter(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $this->pdf->SetY(-18);
        
        $this->pdf->SetDrawColor(220, 223, 230);
        $this->pdf->SetLineWidth(0.3);
        $this->pdf->Line(15, $this->pdf->GetY() - 3, $pageWidth - 15, $this->pdf->GetY() - 3);
        
        $this->pdf->SetFont('arial', 'B', 8);
        $this->pdf->SetTextColor(107, 114, 128);
        $footerText = 'صفحة ' . $this->pdf->getAliasNumPage() . ' من ' . $this->pdf->getAliasNbPages() . '  |  تم الإنشاء بواسطة نظام جوادة الطبي';
        $this->pdf->Cell(0, 6, $footerText, 0, 0, 'C');
    }

    public static function generateFromRequest(Request $request): array
    {
        try {
            $validated = $request->validate([
                'shift_id' => 'required|integer',
            ]);

            $shiftId = $validated['shift_id'];
            $shift = Shift::find($shiftId);

            if (!$shift) {
                return ['error' => 'الوردية غير موجودة', 'status' => 404];
            }

            // Fetch Returned Lab Requests
            $returnedLabRequests = ReturnedLabRequest::with(['labRequest.patient'])
                ->where('shift_id', $shiftId)
                ->get();

            // Fetch Returned Requested Services
            $returnedServices = ReturnedRequestedService::with(['requestedService.doctorVisit.patient'])
                ->where('shift_id', $shiftId)
                ->get();

            $refundsData = [];

            foreach ($returnedLabRequests as $rlr) {
                $refundsData[] = [
                    'patient_name' => $rlr->labRequest->patient->name ?? 'مجهول',
                    'type' => 'مختبر',
                    'amount' => (float)$rlr->amount,
                    'payment_method' => $rlr->returned_payment_method === 'cash' ? 'نقدي' : ($rlr->returned_payment_method === 'bank' ? 'بنكي' : $rlr->returned_payment_method),
                    'reason' => $rlr->return_reason ?? '-',
                ];
            }

            foreach ($returnedServices as $rs) {
                $refundsData[] = [
                    'patient_name' => $rs->requestedService->doctorVisit->patient->name ?? 'مجهول',
                    'type' => 'خدمة',
                    'amount' => (float)$rs->amount,
                    'payment_method' => $rs->returned_payment_method === 'cash' ? 'نقدي' : ($rs->returned_payment_method === 'bank' ? 'بنكي' : $rs->returned_payment_method),
                    'reason' => $rs->return_reason ?? '-',
                ];
            }

            // Sort by patient name
            usort($refundsData, function ($a, $b) {
                return strcmp($a['patient_name'], $b['patient_name']);
            });

            $pdfData = [
                'title' => 'تقرير الاستردادات للوردية',
                'shiftName' => $shift->name ?? "وردية #{$shiftId}",
                'printDate' => date('Y-m-d H:i:s'),
                'refunds' => $refundsData
            ];

            $report = new self($pdfData);
            $pdfContent = $report->generate();
            
            $pdfFileName = 'shift-refunds-' . $shiftId . '-' . date('Y-m-d') . '.pdf';

            return [
                'content' => $pdfContent,
                'filename' => $pdfFileName,
                'status' => 200
            ];

        } catch (\Exception $e) {
            Log::error('Shift Refund PDF Generation Error: ' . $e->getMessage());
            return ['error' => $e->getMessage(), 'status' => 500];
        }
    }
}
