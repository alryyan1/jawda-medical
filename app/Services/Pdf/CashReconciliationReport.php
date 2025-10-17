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
        $this->renderTwoColumnLayout();
        $this->renderFooter();

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
        $this->pdf->SetFont('arial', 'B', 16);
        $this->pdf->Cell(0, 10, 'معلومات الوردية', 0, 1, 'C');
        $this->pdf->Ln(5);
        
        $this->pdf->SetFont('arial', '', 12);
        
        // Get page width and calculate column width
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 15; // Left and right margins
        $usableWidth = $pageWidth - (2 * $margin);
        $columnWidth = $usableWidth / 2;
        
        // Row 1: Shift Name and English Date
        $this->pdf->SetX($margin);
        $this->pdf->Cell($columnWidth, 8, 'اسم الوردية: ' . $this->data['shiftName'], 0, 0, 'R');
        $this->pdf->SetX($margin + $columnWidth);
        $this->pdf->Cell($columnWidth, 8, 'تاريخ التقرير (إنجليزي): ' . $this->data['englishDate'], 0, 1, 'R');
        
        // Row 2: Shift ID and User Name
        $this->pdf->SetX($margin);
        $this->pdf->Cell($columnWidth, 8, 'رقم الوردية: ' . $this->data['shiftId'], 0, 0, 'R');
        $this->pdf->SetX($margin + $columnWidth);
        $this->pdf->Cell($columnWidth, 8, 'اسم المستخدم: ' . $this->data['userName'], 0, 1, 'R');
        
        // Row 3: Report Date and Shift Type
        $this->pdf->SetX($margin);
        $this->pdf->Cell($columnWidth, 8, 'تاريخ التقرير: ' . $this->data['date'], 0, 0, 'R');
        $this->pdf->SetX($margin + $columnWidth);
        $this->pdf->Cell($columnWidth, 8, 'نوع الوردية: ' . $this->data['shiftType'], 0, 1, 'R');
        
        $this->pdf->Ln(10);
    }

    private function renderTwoColumnLayout(): void
    {
        // Get page dimensions
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 15;
        $usableWidth = $pageWidth - (2 * $margin);
        $columnWidth = $usableWidth / 2;
        $currentY = $this->pdf->GetY();
        
        // Right column: Financial Summary and Denominations
        $rightEndY = $this->renderRightColumn($margin, $columnWidth, $currentY);
        
        // Left column: Cost Details
        $this->renderLeftColumn($margin + $columnWidth, $columnWidth, $currentY);
        $leftEndY = $this->pdf->GetY();

        // Vertical separator between columns
        $this->pdf->SetDrawColor(220, 220, 220);
        $this->pdf->SetLineWidth(0.2);
        $separatorX = $margin + $columnWidth;
        $this->pdf->Line($separatorX, $currentY, $separatorX, max($rightEndY, $leftEndY));

        // Move cursor to end of the lower column
        $this->pdf->SetY(max($rightEndY, $leftEndY));
    }

    private function renderRightColumn($startX, $width, $startY): float
    {
        $this->pdf->SetXY($startX, $startY);
        $this->renderFinancialSummaryColumn($width);
        
        // Add some space between tables
        $this->pdf->Ln(10);
        
        $this->renderDenominationsColumn($width);

        return $this->pdf->GetY();
    }

    private function renderLeftColumn($startX, $width, $startY): void
    {
        $this->pdf->SetXY($startX, $startY);
        $this->renderCostsColumn($width);
    }

    private function renderFinancialSummaryColumn($width): void
    {
        $this->pdf->SetFont('arial', 'B', 16);
        $this->pdf->Cell($width, 10, 'الملخص المالي', 0, 1, 'C');
        $this->pdf->Ln(5);

        $incomeData = $this->data['incomeData'];
        
        // Create financial summary table (styled header)
        $this->pdf->SetFillColor(245, 245, 245);
        $this->pdf->SetDrawColor(200, 200, 200);
        $this->pdf->SetLineWidth(0.2);
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->Cell($width * 0.4, 10, '', 1, 0, 'C', true); // Empty cell for alignment
        $this->pdf->Cell($width * 0.3, 10, 'النقدي', 1, 0, 'C', true);
        $this->pdf->Cell($width * 0.3, 10, 'البنك', 1, 1, 'C', true);

        $this->pdf->SetFont('arial', '', 12);
        
        // Income row
        $this->pdf->Cell($width * 0.4, 10, 'المتحصل', 1, 0, 'R');
        $this->pdf->Cell($width * 0.3, 10, number_format($incomeData->total_cash, 0), 1, 0, 'C');
        $this->pdf->Cell($width * 0.3, 10, number_format($incomeData->total_bank, 0), 1, 1, 'C');
        
        // Expenses row
        $this->pdf->Cell($width * 0.4, 10, 'المصروف', 1, 0, 'R');
        $this->pdf->Cell($width * 0.3, 10, number_format($incomeData->total_cash_expenses, 0), 1, 0, 'C');
        $this->pdf->Cell($width * 0.3, 10, number_format($incomeData->total_bank_expenses, 0), 1, 1, 'C');
        
        // Net balance row
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->Cell($width * 0.4, 10, 'الصافي', 1, 0, 'R');
        $this->pdf->Cell($width * 0.3, 10, number_format($incomeData->net_cash, 0), 1, 0, 'C');
        $this->pdf->Cell($width * 0.3, 10, number_format($incomeData->net_bank, 0), 1, 1, 'C');
        
        // Total denominations row
        $this->pdf->Cell($width * 0.4, 10, 'إجمالي الفئات', 1, 0, 'R');
        $this->pdf->Cell($width * 0.3, 10, number_format($this->data['totalDenominations'], 0), 1, 0, 'C');
        $this->pdf->Cell($width * 0.3, 10, '-', 1, 1, 'C');
        
        // Difference row
        $cashDifference = $incomeData->net_cash - $this->data['totalDenominations'];
        $this->pdf->Cell($width * 0.4, 10, 'الفرق', 1, 0, 'R');
        $this->pdf->Cell($width * 0.3, 10, number_format($cashDifference, 0), 1, 0, 'C');
        $this->pdf->Cell($width * 0.3, 10, '-', 1, 1, 'C');
    }

    private function renderDenominationsColumn($width): void
    {
        $this->pdf->SetFont('arial', 'B', 16);
        $this->pdf->Cell($width, 10, 'تفاصيل الفئات النقدية', 0, 1, 'C');
        $this->pdf->Ln(5);

        // Header with fill
        $this->pdf->SetFillColor(245, 245, 245);
        $this->pdf->SetDrawColor(200, 200, 200);
        $this->pdf->SetLineWidth(0.2);
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->Cell($width * 0.33, 10, 'الفئة', 1, 0, 'C', true);
        $this->pdf->Cell($width * 0.33, 10, 'العدد', 1, 0, 'C', true);
        $this->pdf->Cell($width * 0.34, 10, 'المجموع', 1, 1, 'C', true);

        $this->pdf->SetFont('arial', '', 12);
        $this->pdf->SetFillColor(252, 252, 252);
        $fill = false;
        foreach ($this->data['denominations'] as $denomination) {
            if ($denomination['count'] > 0) {
                $this->pdf->Cell($width * 0.33, 10, number_format($denomination['name']), 1, 0, 'C', $fill);
                $this->pdf->Cell($width * 0.33, 10, number_format($denomination['count']), 1, 0, 'C', $fill);
                $this->pdf->Cell($width * 0.34, 10, number_format($denomination['name'] * $denomination['count']), 1, 1, 'C', $fill);
                $fill = !$fill;
            }
        }

        // Totals row
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->SetFillColor(245, 245, 245);
        $this->pdf->Cell($width * 0.66, 10, 'الإجمالي', 1, 0, 'R', true);
        $this->pdf->Cell($width * 0.34, 10, number_format($this->data['totalDenominations'], 0), 1, 1, 'C', true);
    }

    private function renderCostsColumn($width): void
    {
        $this->pdf->SetFont('arial', 'B', 16);
        $this->pdf->Cell($width, 10, 'تفاصيل المصروفات', 0, 1, 'C');
        $this->pdf->Ln(5);

        if (!empty($this->data['costs'])) {
            // Header with fill
            $this->pdf->SetFillColor(245, 245, 245);
            $this->pdf->SetDrawColor(200, 200, 200);
            $this->pdf->SetLineWidth(0.2);
            $this->pdf->SetFont('arial', 'B', 12);
            $this->pdf->Cell($width * 0.4, 10, 'الوصف', 1, 0, 'C', true);
            $this->pdf->Cell($width * 0.2, 10, 'النقدي', 1, 0, 'C', true);
            $this->pdf->Cell($width * 0.2, 10, 'البنكي', 1, 0, 'C', true);
            $this->pdf->Cell($width * 0.2, 10, 'المجموع', 1, 1, 'C', true);

            $this->pdf->SetFont('arial', '', 11);
            $this->pdf->SetFillColor(252, 252, 252);
            $fill = false;
            $sumCash = 0;
            $sumBank = 0;
            $sumTotal = 0;
            foreach ($this->data['costs'] as $cost) {
                $total = $cost['amount'] + $cost['amount_bankak'];
                $sumCash += $cost['amount'];
                $sumBank += $cost['amount_bankak'];
                $sumTotal += $total;
                $this->pdf->Cell($width * 0.4, 10, $cost['description'], 1, 0, 'R', $fill);
                $this->pdf->Cell($width * 0.2, 10, $cost['amount'] > 0 ? number_format($cost['amount']) : '-', 1, 0, 'C', $fill);
                $this->pdf->Cell($width * 0.2, 10, $cost['amount_bankak'] > 0 ? number_format($cost['amount_bankak']) : '-', 1, 0, 'C', $fill);
                $this->pdf->Cell($width * 0.2, 10, number_format($total), 1, 1, 'C', $fill);
                $fill = !$fill;
            }

            // Totals row
            $this->pdf->SetFont('arial', 'B', 12);
            $this->pdf->SetFillColor(245, 245, 245);
            $this->pdf->Cell($width * 0.4, 10, 'الإجمالي', 1, 0, 'R', true);
            $this->pdf->Cell($width * 0.2, 10, number_format($sumCash, 0), 1, 0, 'C', true);
            $this->pdf->Cell($width * 0.2, 10, number_format($sumBank, 0), 1, 0, 'C', true);
            $this->pdf->Cell($width * 0.2, 10, number_format($sumTotal, 0), 1, 1, 'C', true);
        } else {
            $this->pdf->SetFont('arial', '', 12);
            // $this->pdf->Ln(10);
            // $this->pdf->Cell($width, 10, 'لا توجد مصروفات مسجلة', 0, 1, 'C');
        }
    }

    private function renderFooter(): void
    {
        $this->pdf->SetY(-18);
        $this->pdf->SetFont('arial', 'I', 8);
        $this->pdf->SetTextColor(100, 100, 100);
        $footerText = 'صفحة ' . $this->pdf->getAliasNumPage() . ' من ' . $this->pdf->getAliasNbPages() . '  |  تم الإنشاء بواسطة نظام جوادة';
        $this->pdf->Cell(0, 8, $footerText, 0, 0, 'C');
        $this->pdf->SetTextColor(0, 0, 0);
    }

    // Old methods replaced by column-based layout
    // private function renderFinancialSummary(): void { ... }
    // private function renderDenominations(): void { ... }
    // private function renderCosts(): void { ... }

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
            $title =  '  تقرير موظف المعمل';
            $date = $validated['date'] ?? date('Y-m-d');

            Log::info('Cash Reconciliation PDF - Validated params', [
                'shiftId' => $shiftId,
                'title' => $title,
                'date' => $date
            ]);

            // Get shift information
            $shift = Shift::with('userOpened')->find($shiftId);
            if (!$shift) {
                Log::error("Shift not found: {$shiftId}");
                return ['error' => 'الوردية غير موجودة', 'status' => 404];
            }

            $shiftName = $shift->name ?? "وردية #{$shiftId}";
            
            // Get user name
            $userName = $shift->userOpened ? $shift->userOpened->name : 'غير محدد';
            
            // Get English date
            $englishDate = $shift->created_at ? $shift->created_at->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            
            // Determine shift type based on creation time
            $shiftType = 'الوردية المسائيه'; // Default to evening
            if ($shift->created_at) {
                $hour = $shift->created_at->format('H');
                if ($hour >= 6 && $hour < 18) { // 6 AM to 6 PM
                    $shiftType = 'الورديه الصباحيه';
                }
            }

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
                'userName' => $userName,
                'englishDate' => $englishDate,
                'shiftType' => $shiftType,
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

