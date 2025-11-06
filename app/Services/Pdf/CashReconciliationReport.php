<?php

namespace App\Services\Pdf;

use TCPDF;
use App\Models\Shift;
use App\Models\Cost;
use App\Models\Deno;
use App\Models\DenoUser;
use App\Http\Controllers\Api\UserController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
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

        // Set margins - more generous for professional look
        $this->pdf->SetMargins(20, 40, 20);
        $this->pdf->SetHeaderMargin(10);
        $this->pdf->SetFooterMargin(15);

        // Set auto page breaks
        $this->pdf->SetAutoPageBreak(TRUE, 30);

        // Set RTL direction for Arabic text
        $this->pdf->setRTL(true);

        // Add a page
        $this->pdf->AddPage();
    }

    public function generate(): string
    {
        $this->renderHeader();
        $this->renderTitle();
        $this->renderShiftInfo();
        $this->renderFinancialSummary();
        // $this->renderDenominations();
        $this->renderCosts();
        $this->renderFooter();

        return $this->pdf->Output('', 'S');
    }

    private function renderHeader(): void
    {
        // Monochrome professional header
        $this->pdf->SetY(12);
        $this->pdf->SetFont('arial', 'B', 16);
        $this->pdf->SetTextColor(0, 0, 0);

        $this->pdf->SetFont('arial', '', 11);

        // Separator line
    }

    private function renderTitle(): void
    {
        $this->pdf->SetFont('arial', '', 12);
        $this->pdf->SetTextColor(0, 0, 0);

        //extract the date only $this->data['shiftDate']
        $shiftDate = Carbon::parse($this->data['shiftDate'])->format('Y-m-d');
        // $this->pdf->Cell(0, 10, $this->data['title'], 0, 0, 'C');
        $this->pdf->Cell(0, 10, $this->data['title'], 0, 1, 'C');
        $this->pdf->Cell(20, 10, 'اسم المستخدم: ', 0, 0, 'C');
        $this->pdf->Cell(40, 10, $this->data['user'], 0, 0, 'C');
        $this->pdf->Cell(  40, 10, '', 0, 0, 'C');
        $this->pdf->Cell(25, 10, 'تاريخ الوردية: ', 0, 0, 'C');
        $this->pdf->Cell(45, 10, $shiftDate, 0, 1, 'C');


        // Underline
        $this->pdf->SetDrawColor(150, 150, 150);
        $this->pdf->SetLineWidth(0.3);
        $this->pdf->Ln(6);
    }

    private function renderShiftInfo(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 20;
        $usableWidth = $pageWidth - (2 * $margin);

        // Section header (monochrome)
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetDrawColor(180, 180, 180);
        $this->pdf->SetLineWidth(0.2);

        // Info rows
        $this->pdf->SetDrawColor(200, 200, 200);
        $this->pdf->SetLineWidth(0.2);
        $this->pdf->SetFont('arial', '', 11);

   
        //each row has 3 columns
        $colWidth = $usableWidth / 5;
        //current date
        $currentDate = Carbon::now()->format('Y-m-d');
        // $this->pdf->SetEqualColumns(2);
        $this->pdf->Cell($colWidth,5,' الوردية: ',1,0,'R');
        $this->pdf->Cell($colWidth,5,$this->data['shiftType'],1,0,'R');
        $this->pdf->Cell($colWidth,5,'',1,0,'R');
        $this->pdf->Cell($colWidth,5,'رقم الوردية: ',1,0,'R');
        $this->pdf->Cell($colWidth,5,$this->data['shiftId'],1,1,'R');
        // $this->pdf->Cell($colWidth,5,'',1,0,'R');

        $this->pdf->Cell($colWidth,5,'اسم المستخدم: ',1,0,'R');
        
        $this->pdf->Cell($colWidth,5,$this->data['user'],1,0,'R');
        $this->pdf->Cell($colWidth,5,'',1,0,'R');

        $this->pdf->Cell($colWidth,5,'تاريخ الطباعه: ',1,0,'R');
        $this->pdf->Cell($colWidth,5,$currentDate,1,1,'R');
        // $this->pdf->Cell($colWidth,5,'نوع الوردية: ',1,0,'R');
        // $this->pdf->Cell($colWidth,5,$this->data['shiftType'],1,1,'R');


        $this->pdf->Ln(10);
    }

    private function renderFinancialSummary(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 20;
        $usableWidth = $pageWidth - (2 * $margin);
        
        // Section header (monochrome)
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Cell($usableWidth, 8, 'الملخص المالي', 0, 1, 'C');
        $this->pdf->SetDrawColor(180, 180, 180);
        $this->pdf->SetLineWidth(0.2);
        $this->pdf->Line(20, $this->pdf->GetY(), $this->pdf->getPageWidth() - 20, $this->pdf->GetY());
        $this->pdf->Ln(4);

        $incomeData = $this->data['incomeData'];
        
        // Table header (monochrome)
        $this->pdf->SetDrawColor(200, 200, 200);
        $this->pdf->SetLineWidth(0.2);
        $this->pdf->SetFont('arial', 'B', 11);
        $this->pdf->Cell($usableWidth * 0.4, 9, 'البند', 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, 'النقدي', 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, 'البنك', 1, 1, 'C', false);

        $this->pdf->SetFont('arial', '', 11);

        // Income row
        $this->pdf->Cell($usableWidth * 0.4, 9, 'المتحصل', 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, number_format($incomeData->total_cash, 0), 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, number_format($incomeData->total_bank, 0), 1, 1, 'C', false);

        // Expenses row
        $this->pdf->Cell($usableWidth * 0.4, 9, 'المصروف', 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, number_format($incomeData->total_cash_expenses, 0), 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, number_format($incomeData->total_bank_expenses, 0), 1, 1, 'C', false);

        // Net balance row
        $this->pdf->SetFont('arial', 'B', 11);
        $this->pdf->Cell($usableWidth * 0.4, 9, 'الصافي', 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, number_format($incomeData->net_cash, 0), 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, '-', 1, 1, 'C', false);

        // Total denominations row
        $this->pdf->SetFont('arial', '', 11);
        $this->pdf->Cell($usableWidth * 0.4, 9, 'إجمالي الفئات', 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, number_format($this->data['totalDenominations'], 0), 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, '-', 1, 1, 'C', false);

        // Difference row (monochrome)
        $cashDifference = $incomeData->net_cash - $this->data['totalDenominations'];
        $this->pdf->SetFont('arial', 'B', 11);
        $this->pdf->Cell($usableWidth * 0.4, 9, 'الفرق', 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, number_format($cashDifference, 0), 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.3, 9, '-', 1, 1, 'C', false);
        
        $this->pdf->Ln(12);
    }

    private function renderDenominations(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 20;
        $usableWidth = $pageWidth - (2 * $margin);
        
        // Section header (monochrome)
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Cell($usableWidth, 8, 'تفاصيل الفئات النقدية', 0, 1, 'C');
        $this->pdf->SetDrawColor(180, 180, 180);
        $this->pdf->SetLineWidth(0.2);
        $this->pdf->Line(20, $this->pdf->GetY(), $this->pdf->getPageWidth() - 20, $this->pdf->GetY());
        $this->pdf->Ln(4);

        // Table header (monochrome)
        $this->pdf->SetDrawColor(200, 200, 200);
        $this->pdf->SetLineWidth(0.2);
        $this->pdf->SetFont('arial', 'B', 11);
        $this->pdf->Cell($usableWidth * 0.33, 9, 'الفئة', 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.33, 9, 'العدد', 1, 0, 'C', false);
        $this->pdf->Cell($usableWidth * 0.34, 9, 'المجموع', 1, 1, 'C', false);

        $this->pdf->SetFont('arial', '', 11);
        $hasDenominations = false;
        
        foreach ($this->data['denominations'] as $denomination) {
            if ($denomination['count'] > 0) {
                $hasDenominations = true;
                $this->pdf->Cell($usableWidth * 0.33, 9, number_format($denomination['name']), 1, 0, 'C', false);
                $this->pdf->Cell($usableWidth * 0.33, 9, number_format($denomination['count']), 1, 0, 'C', false);
                $this->pdf->Cell($usableWidth * 0.34, 9, number_format($denomination['name'] * $denomination['count']), 1, 1, 'C', false);
            }
        }

        if (!$hasDenominations) {
            $this->pdf->SetFont('arial', '', 11);
            $this->pdf->Cell($usableWidth, 9, 'لا توجد فئات نقدية مسجلة', 1, 1, 'C', false);
        }

        // Totals row
        $this->pdf->SetFont('arial', 'B', 11);
        $this->pdf->Cell($usableWidth * 0.66, 9, 'الإجمالي', 1, 0, 'R', false);
        $this->pdf->Cell($usableWidth * 0.34, 9, number_format($this->data['totalDenominations'], 0), 1, 1, 'C', false);
        
        $this->pdf->Ln(12);
    }

    private function renderCosts(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $margin = 20;
        $usableWidth = $pageWidth - (2 * $margin);
        
        // Section header (monochrome)
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Cell($usableWidth, 8, 'تفاصيل المصروفات', 0, 1, 'C');
        $this->pdf->SetDrawColor(180, 180, 180);
        $this->pdf->SetLineWidth(0.2);
        $this->pdf->Line(20, $this->pdf->GetY(), $this->pdf->getPageWidth() - 20, $this->pdf->GetY());
        $this->pdf->Ln(4);

        if (!empty($this->data['costs'])) {
            // Table header (monochrome)
            $this->pdf->SetDrawColor(200, 200, 200);
            $this->pdf->SetLineWidth(0.2);
            $this->pdf->SetFont('arial', 'B', 11);
            $this->pdf->Cell($usableWidth * 0.4, 9, 'الوصف', 1, 0, 'C', false);
            $this->pdf->Cell($usableWidth * 0.2, 9, 'النقدي', 1, 0, 'C', false);
            $this->pdf->Cell($usableWidth * 0.2, 9, 'البنكي', 1, 0, 'C', false);
            $this->pdf->Cell($usableWidth * 0.2, 9, 'المجموع', 1, 1, 'C', false);

            $this->pdf->SetFont('arial', '', 11);
            $sumCash = 0;
            $sumBank = 0;
            $sumTotal = 0;
            
            foreach ($this->data['costs'] as $cost) {
                $total = $cost['amount'] + $cost['amount_bankak'];
                $sumCash += $cost['amount'];
                $sumBank += $cost['amount_bankak'];
                $sumTotal += $total;
                $this->pdf->Cell($usableWidth * 0.4, 9, $cost['description'], 1, 0, 'R', false);
                $this->pdf->Cell($usableWidth * 0.2, 9, $cost['amount'] > 0 ? number_format($cost['amount']) : '-', 1, 0, 'C', false);
                $this->pdf->Cell($usableWidth * 0.2, 9, $cost['amount_bankak'] > 0 ? number_format($cost['amount_bankak']) : '-', 1, 0, 'C', false);
                $this->pdf->Cell($usableWidth * 0.2, 9, number_format($total), 1, 1, 'C', false);
            }

            // Totals row
            $this->pdf->SetFont('arial', 'B', 11);
            $this->pdf->Cell($usableWidth * 0.4, 9, 'الإجمالي', 1, 0, 'R', false);
            $this->pdf->Cell($usableWidth * 0.2, 9, number_format($sumCash, 0), 1, 0, 'C', false);
            $this->pdf->Cell($usableWidth * 0.2, 9, number_format($sumBank, 0), 1, 0, 'C', false);
            $this->pdf->Cell($usableWidth * 0.2, 9, number_format($sumTotal, 0), 1, 1, 'C', false);
        } else {
            $this->pdf->SetFont('arial', '', 11);
            $this->pdf->Cell($usableWidth, 9, 'لا توجد مصروفات مسجلة', 1, 1, 'C', false);
        }
        
        $this->pdf->Ln(12);
    }

    private function renderFooter(): void
    {
        $pageWidth = $this->pdf->getPageWidth();
        $this->pdf->SetY(-20);
        
        // Draw footer line
        $this->pdf->SetDrawColor(200, 200, 200);
        $this->pdf->SetLineWidth(0.3);
        $this->pdf->Line(20, $this->pdf->GetY() - 2, $pageWidth - 20, $this->pdf->GetY() - 2);
        
        $this->pdf->SetFont('arial', 'I', 9);
        $this->pdf->SetTextColor(120, 120, 120);
        $footerText = 'صفحة ' . $this->pdf->getAliasNumPage() . ' من ' . $this->pdf->getAliasNbPages() . '  |  تم الإنشاء بواسطة نظام جوادة الطبي';
        $this->pdf->Cell(0, 8, $footerText, 0, 0, 'C');
        $this->pdf->SetTextColor(0, 0, 0);
    }

    public static function generateFromRequest(Request $request): array
    {
        try {
      
            $validated = $request->validate([
                'shift_id' => 'required|string',
                'title' => 'nullable|string|max:255',
                'date' => 'nullable|string',
                'user_id' => 'required|string',
            ]);

            $shiftId = $validated['shift_id'];
            $shift = Shift::find($shiftId);
            $title =  'تقرير ملخص دخل المستخدم';
            $date = $validated['date'] ?? date('Y-m-d');
            $userId = $validated['user_id'];
            $user = User::find($userId);
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
                $incomeResponse = app(UserController::class)->getCurrentUserShiftIncomeSummary(new Request(['shift_id' => $shiftId, 'user_id' => $userId]));
                $incomeData = $incomeResponse->getData()->data;
            } catch (\Exception $e) {
                // dd($incomeData);
                dd($e->getMessage(),$e->getLine(),$e->getFile());
                Log::error("Error fetching income data: " . $e->getMessage());
                // $incomeData = (object) [
                //     'total_cash' => 0,
                //     'total_bank' => 0,
                //     'total_cash_expenses' => 0,
                //     'total_bank_expenses' => 0,
                //     'net_cash' => 0,
                //     'net_bank' => 0
                // ];
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
                'user' => $user->name,
                'englishDate' => $englishDate,
                'shiftDate' => $shift->created_at->format('Y-m-d H:i:s A'),
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
            return ['error' => $e->getMessage(), 'status' => 500];
        }
    }
}

