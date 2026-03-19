<?php

namespace App\Services\Pdf;

use TCPDF;
use App\Models\Shift;
use App\Models\DoctorVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ShiftPatientsDiscountReport
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
        $this->pdf->SetSubject('Shift Patients Discount Report');

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
        $this->renderPatientsTable();
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

    private function renderPatientsTable(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 15;
        $usableWidth = $pageWidth - (2 * $margin);

        $this->pdf->SetDrawColor(220, 223, 230);
        $this->pdf->SetLineWidth(0.3);
        $this->pdf->SetFont('arial', 'B', 11);
        $this->pdf->SetFillColor(240, 240, 240);
        $this->pdf->SetTextColor(0, 0, 0);

        $this->pdf->Cell($usableWidth * 0.4, 8, 'اسم المريض', 1, 0, 'C', true);
        $this->pdf->Cell($usableWidth * 0.2, 8, 'خصم المختبر', 1, 0, 'C', true);
        $this->pdf->Cell($usableWidth * 0.2, 8, 'خصم الخدمات', 1, 0, 'C', true);
        $this->pdf->Cell($usableWidth * 0.2, 8, 'المجموع', 1, 1, 'C', true);

        $this->pdf->SetFont('arial', 'B', 10);
        $rowFill = false;

        $totalLabDiscount = 0;
        $totalServiceDiscount = 0;
        $grandTotalDiscount = 0;

        if (empty($this->data['patients'])) {
            $this->pdf->SetFont('arial', 'B', 10);
            $this->pdf->SetTextColor(107, 114, 128);
            $this->pdf->Cell($usableWidth, 10, 'لا يوجد مرضى في هذه الوردية', 1, 1, 'C');
            $this->pdf->SetTextColor(0, 0, 0);
        } else {
            foreach ($this->data['patients'] as $patient) {
                if ($rowFill) {
                    $this->pdf->SetFillColor(252, 252, 253);
                } else {
                    $this->pdf->SetFillColor(255, 255, 255);
                }

                $labDiscount = $patient['labDiscount'];
                $serviceDiscount = $patient['serviceDiscount'];
                $totalDiscount = $labDiscount + $serviceDiscount;

                $totalLabDiscount += $labDiscount;
                $totalServiceDiscount += $serviceDiscount;
                $grandTotalDiscount += $totalDiscount;

                $this->pdf->Cell($usableWidth * 0.4, 7, $patient['name'], 1, 0, 'R', $rowFill);
                $this->pdf->Cell($usableWidth * 0.2, 7, number_format($labDiscount, 2), 1, 0, 'C', $rowFill);
                $this->pdf->Cell($usableWidth * 0.2, 7, number_format($serviceDiscount, 2), 1, 0, 'C', $rowFill);
                $this->pdf->SetFont('arial', 'B', 10);
                $this->pdf->Cell($usableWidth * 0.2, 7, number_format($totalDiscount, 2), 1, 1, 'C', $rowFill);
                
                $rowFill = !$rowFill;
            }

            // Totals Row
            $this->pdf->SetFont('arial', 'B', 11);
            $this->pdf->SetFillColor(240, 245, 250);
            $this->pdf->SetTextColor(45, 55, 72);
            $this->pdf->Cell($usableWidth * 0.4, 8, 'الإجمالي', 1, 0, 'C', true);
            $this->pdf->Cell($usableWidth * 0.2, 8, number_format($totalLabDiscount, 2), 1, 0, 'C', true);
            $this->pdf->Cell($usableWidth * 0.2, 8, number_format($totalServiceDiscount, 2), 1, 0, 'C', true);
            $this->pdf->Cell($usableWidth * 0.2, 8, number_format($grandTotalDiscount, 2), 1, 1, 'C', true);
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

            // Fetch doctor visits in this shift with patient, requested services and lab requests
            $doctorVisits = DoctorVisit::with([
                'patient:id,name',
                'requestedServices',
                'labRequests'
            ])->where('shift_id', $shiftId)->get();

            $patientsData = [];

            // Group by patient over all visits in this shift
            $patientGroups = $doctorVisits->groupBy('patient_id');

            foreach ($patientGroups as $patientId => $visits) {
                $patientName = $visits->first()->patient->name ?? 'مجهول';
                $labDiscount = 0;
                $serviceDiscount = 0;

                foreach ($visits as $visit) {
                    foreach ($visit->requestedServices as $rs) {
                        $discountFromPercentage = ($rs->price * $rs->count * (float)($rs->discount_per ?? 0)) / 100;
                        $fixedDiscount = (float)($rs->discount ?? 0);
                        $serviceDiscount += $discountFromPercentage + $fixedDiscount;
                    }

                    foreach ($visit->labRequests as $lr) {
                        $discountFromPercentage = ($lr->price * (float)($lr->discount_per ?? 0)) / 100;
                        $fixedDiscount = (float)($lr->discount ?? 0); // fallback if it exists
                        $labDiscount += $discountFromPercentage + $fixedDiscount;
                    }
                }

                $patientsData[] = [
                    'name' => $patientName,
                    'labDiscount' => $labDiscount,
                    'serviceDiscount' => $serviceDiscount,
                ];
            }

            // Sort by name
            usort($patientsData, function ($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            $pdfData = [
                'title' => 'تقرير خصومات المرضى للوردية',
                'shiftName' => $shift->name ?? "وردية #{$shiftId}",
                'printDate' => date('Y-m-d H:i:s'),
                'patients' => $patientsData
            ];

            $report = new self($pdfData);
            $pdfContent = $report->generate();
            
            $pdfFileName = 'shift-patients-discount-' . $shiftId . '-' . date('Y-m-d') . '.pdf';

            return [
                'content' => $pdfContent,
                'filename' => $pdfFileName,
                'status' => 200
            ];

        } catch (\Exception $e) {
            Log::error('Shift Patients Discount PDF Generation Error: ' . $e->getMessage());
            return ['error' => $e->getMessage(), 'status' => 500];
        }
    }
}
