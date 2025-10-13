<?php

namespace App\Services\Pdf;

use TCPDF;
use App\Models\Shift;
use App\Models\Cost;
use App\Models\Deno;
use App\Models\DenoUser;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CashReconciliationReport
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
        
        // Set document information
        $this->pdf->SetCreator('Jawda Medical System');
        $this->pdf->SetAuthor('Jawda Medical System');
        $this->pdf->SetTitle($this->data['title']);
        $this->pdf->SetSubject('Cash Reconciliation Report');

        // Set margins
        $this->pdf->SetMargins(15, 30, 15);
        $this->pdf->SetHeaderMargin(5);
        $this->pdf->SetFooterMargin(10);

        // Set auto page breaks
        $this->pdf->SetAutoPageBreak(TRUE, 25);

        // Set RTL direction for Arabic text
        $this->pdf->setRTL(true);

        // Add a page
        $this->pdf->AddPage();
    }

    public function generate(): string
    {
        $this->renderTitle();
        $this->renderShiftInfo();
        $this->renderFinancialSummary();
        $this->renderDenominations();
        $this->renderCosts();

        return $this->pdf->Output('', 'S');
    }

    private function renderTitle(): void
    {
        $this->pdf->SetFont('arial', 'B', 16);
        $this->pdf->Cell(0, 10, $this->data['title'], 0, 1, 'C');
        $this->pdf->Ln(5);
    }

    private function renderShiftInfo(): void
    {
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->Cell(0, 8, 'معلومات الوردية', 0, 1, 'R');
        $this->pdf->SetFont('arial', '', 10);
        $this->pdf->Cell(0, 6, 'اسم الوردية: ' . $this->data['shiftName'], 0, 1, 'R');
        $this->pdf->Cell(0, 6, 'رقم الوردية: ' . $this->data['shiftId'], 0, 1, 'R');
        $this->pdf->Cell(0, 6, 'تاريخ التقرير: ' . $this->data['date'], 0, 1, 'R');
        $this->pdf->Ln(10);
    }

    private function renderFinancialSummary(): void
    {
        $this->pdf->SetFont('arial', 'B', 14);
        $this->pdf->Cell(0, 8, 'الملخص المالي', 0, 1, 'C');
        $this->pdf->Ln(5);

        $incomeData = $this->data['incomeData'];
        
        // Create financial summary table
        $this->pdf->SetFont('arial', 'B', 10);
        $this->pdf->Cell(60, 8, '', 0, 0, 'C'); // Empty cell for alignment
        $this->pdf->Cell(60, 8, 'النقدي', 1, 0, 'C');
        $this->pdf->Cell(60, 8, 'البنك', 1, 1, 'C');

        $this->pdf->SetFont('arial', '', 10);
        
        // Income row
        $this->pdf->Cell(60, 8, 'المتحصل', 1, 0, 'R');
        $this->pdf->Cell(60, 8, number_format($incomeData->total_cash, 0), 1, 0, 'C');
        $this->pdf->Cell(60, 8, number_format($incomeData->total_bank, 0), 1, 1, 'C');
        
        // Expenses row
        $this->pdf->Cell(60, 8, 'المصروف', 1, 0, 'R');
        $this->pdf->Cell(60, 8, number_format($incomeData->total_cash_expenses, 0), 1, 0, 'C');
        $this->pdf->Cell(60, 8, number_format($incomeData->total_bank_expenses, 0), 1, 1, 'C');
        
        // Net balance row
        $this->pdf->SetFont('arial', 'B', 10);
        $this->pdf->Cell(60, 8, 'الصافي', 1, 0, 'R');
        $this->pdf->Cell(60, 8, number_format($incomeData->net_cash, 0), 1, 0, 'C');
        $this->pdf->Cell(60, 8, number_format($incomeData->net_bank, 0), 1, 1, 'C');
        
        // Total denominations row
        $this->pdf->Cell(60, 8, 'إجمالي الفئات', 1, 0, 'R');
        $this->pdf->Cell(60, 8, number_format($this->data['totalDenominations'], 0), 1, 0, 'C');
        $this->pdf->Cell(60, 8, '-', 1, 1, 'C');
        
        // Difference row
        $cashDifference = $incomeData->net_cash - $this->data['totalDenominations'];
        $this->pdf->Cell(60, 8, 'الفرق', 1, 0, 'R');
        $this->pdf->Cell(60, 8, number_format($cashDifference, 0), 1, 0, 'C');
        $this->pdf->Cell(60, 8, '-', 1, 1, 'C');

        $this->pdf->Ln(15);
    }

    private function renderDenominations(): void
    {
        $this->pdf->SetFont('arial', 'B', 14);
        $this->pdf->Cell(0, 8, 'تفاصيل الفئات النقدية', 0, 1, 'C');
        $this->pdf->Ln(5);

        $this->pdf->SetFont('arial', 'B', 10);
        $this->pdf->Cell(60, 8, 'الفئة', 1, 0, 'C');
        $this->pdf->Cell(60, 8, 'العدد', 1, 0, 'C');
        $this->pdf->Cell(60, 8, 'المجموع', 1, 1, 'C');

        $this->pdf->SetFont('arial', '', 10);
        foreach ($this->data['denominations'] as $denomination) {
            if ($denomination['count'] > 0) {
                $this->pdf->Cell(60, 8, number_format($denomination['name']), 1, 0, 'C');
                $this->pdf->Cell(60, 8, number_format($denomination['count']), 1, 0, 'C');
                $this->pdf->Cell(60, 8, number_format($denomination['name'] * $denomination['count']), 1, 1, 'C');
            }
        }

        $this->pdf->Ln(15);
    }

    private function renderCosts(): void
    {
        $this->pdf->SetFont('arial', 'B', 14);
        $this->pdf->Cell(0, 8, 'تفاصيل المصروفات', 0, 1, 'C');
        $this->pdf->Ln(5);

        if (!empty($this->data['costs'])) {
            $this->pdf->SetFont('arial', 'B', 10);
            $this->pdf->Cell(60, 8, 'الوصف', 1, 0, 'C');
            $this->pdf->Cell(40, 8, 'النقدي', 1, 0, 'C');
            $this->pdf->Cell(40, 8, 'البنكي', 1, 0, 'C');
            $this->pdf->Cell(40, 8, 'المجموع', 1, 1, 'C');

            $this->pdf->SetFont('arial', '', 9);
            foreach ($this->data['costs'] as $cost) {
                $total = $cost['amount'] + $cost['amount_bankak'];
                $this->pdf->Cell(60, 8, $cost['description'], 1, 0, 'R');
                $this->pdf->Cell(40, 8, $cost['amount'] > 0 ? number_format($cost['amount']) : '-', 1, 0, 'C');
                $this->pdf->Cell(40, 8, $cost['amount_bankak'] > 0 ? number_format($cost['amount_bankak']) : '-', 1, 0, 'C');
                $this->pdf->Cell(40, 8, number_format($total), 1, 1, 'C');
            }
        } else {
            $this->pdf->SetFont('arial', '', 10);
            $this->pdf->Cell(0, 8, 'لا توجد مصروفات مسجلة', 0, 1, 'C');
        }
    }

    public static function generateFromRequest(Request $request): array
    {
        try {
            Log::info('Cash Reconciliation PDF Request', [
                'all_params' => $request->all(),
                'shift_id' => $request->get('shift_id'),
                'title' => $request->get('title'),
                'date' => $request->get('date')
            ]);

            $validated = $request->validate([
                'shift_id' => 'required|string',
                'title' => 'nullable|string|max:255',
                'date' => 'nullable|string',
            ]);

            $shiftId = $validated['shift_id'];
            $title = $validated['title'] ?? 'تقرير تسوية النقدية';
            $date = $validated['date'] ?? date('Y-m-d');

            Log::info('Cash Reconciliation PDF - Validated params', [
                'shiftId' => $shiftId,
                'title' => $title,
                'date' => $date
            ]);

            // Get shift information
            $shift = Shift::find($shiftId);
            if (!$shift) {
                Log::error("Shift not found: {$shiftId}");
                return ['error' => 'الوردية غير موجودة', 'status' => 404];
            }

            $shiftName = $shift->name ?? "وردية #{$shiftId}";

            // Fetch income summary data
            try {
                $incomeResponse = app(UserController::class)->getCurrentUserShiftIncomeSummary(new Request(['shift_id' => $shiftId]));
                $incomeData = $incomeResponse->getData()->data;
            } catch (\Exception $e) {
                Log::error("Error fetching income data: " . $e->getMessage());
                $incomeData = (object) [
                    'total_cash' => 0,
                    'total_bank' => 0,
                    'total_cash_expenses' => 0,
                    'total_bank_expenses' => 0,
                    'net_cash' => 0,
                    'net_bank' => 0
                ];
            }

            // Fetch denominations data
            try {
                $denos = Deno::orderBy('display_order')
                    ->whereNotIn('name', [10, 20, 50])
                    ->get();
                
                $denoUsers = DenoUser::where('shift_id', $shiftId)->get();
                
                $denominations = $denos->map(function ($deno) use ($denoUsers) {
                    $denoUser = $denoUsers->where('deno_id', $deno->id)->first();
                    return [
                        'id' => $deno->id,
                        'name' => $deno->name,
                        'count' => $denoUser ? $denoUser->count : 0,
                    ];
                });
                
                $totalDenominations = $denominations->sum(function($deno) {
                    return $deno['name'] * $deno['count'];
                });
            } catch (\Exception $e) {
                Log::error("Error fetching denominations data: " . $e->getMessage());
                $denominations = collect([]);
                $totalDenominations = 0;
            }

            // Fetch costs data
            try {
                $costs = Cost::where('shift_id', $shiftId)->get();
            } catch (\Exception $e) {
                Log::error("Error fetching costs data: " . $e->getMessage());
                $costs = collect([]);
            }

            // Create PDF content
            $pdfData = [
                'title' => $title,
                'date' => $date,
                'shiftId' => $shiftId,
                'shiftName' => $shiftName,
                'denominations' => $denominations->toArray(),
                'totalDenominations' => $totalDenominations,
                'costs' => $costs->toArray(),
                'incomeData' => $incomeData
            ];

            // Generate PDF
            $report = new self($pdfData);
            $pdfContent = $report->generate();
            
            $pdfFileName = 'cash-reconciliation-' . $shiftId . '-' . date('Y-m-d') . '.pdf';

            return [
                'content' => $pdfContent,
                'filename' => $pdfFileName,
                'status' => 200
            ];

        } catch (\Exception $e) {
            Log::error('Cash Reconciliation PDF Generation Error: ' . $e->getMessage());
            return ['error' => 'فشل في إنشاء التقرير', 'status' => 500];
        }
    }
}
