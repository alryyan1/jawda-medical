<?php

namespace App\Services\Pdf;

use TCPDF;
use App\Models\Admission;
use App\Models\Setting;
use Carbon\Carbon;

class AdmissionLedgerReport
{
    private TCPDF $pdf;
    private Admission $admission;
    private array $ledgerData;
    private Setting $settings;

    public function __construct(Admission $admission, array $ledgerData)
    {
        $this->admission = $admission->load(['patient', 'ward', 'room', 'bed', 'shortStayBed']);
        $this->ledgerData = $ledgerData;
        $this->settings = Setting::instance();
        $this->initializePdf();
    }

    private function initializePdf(): void
    {
        $this->pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $this->pdf->setPrintHeader(false);
        $this->pdf->setPrintFooter(false);
        $this->pdf->SetMargins(15, 20, 15);
        $this->pdf->SetAutoPageBreak(TRUE, 25);
        $this->pdf->setRTL(true);
        $this->pdf->SetFont('arial', '', 12);
        $this->pdf->AddPage();
    }

    public function generate(): string
    {
        $this->renderHeader();
        $this->renderAdmissionInfo();
        $this->renderSummary();
        $this->renderTransactionsTable();
        $this->renderFooter();

        return $this->pdf->Output('', 'S');
    }

    private function renderHeader(): void
    {
        $y = $this->pdf->GetY();
        
        // Logo (if available)
        if (!empty($this->settings->logo_base64)) {
            try {
                $logoData = base64_decode($this->settings->logo_base64);
                $logoPath = sys_get_temp_dir() . '/logo_' . uniqid() . '.png';
                file_put_contents($logoPath, $logoData);
                $this->pdf->Image($logoPath, 15, $y, 30, 0, '', '', '', false, 300, '', false, false, 0);
                unlink($logoPath);
                $y += 15;
            } catch (\Exception $e) {
                // If logo fails, continue without it
            }
        }

        // Hospital Name
        $this->pdf->SetFont('arial', 'B', 18);
        $hospitalName = $this->settings->hospital_name ?? 'المستشفى';
        $this->pdf->SetY($y);
        $this->pdf->Cell(0, 10, $hospitalName, 0, 1, 'C');
        
        // Report Title
        $this->pdf->SetFont('arial', 'B', 16);
        $this->pdf->Cell(0, 8, 'كشف الحساب - تنويم', 0, 1, 'C');
        
        // Hospital Details
        $this->pdf->SetFont('arial', '', 10);
        $details = [];
        
        if (!empty($this->settings->address)) {
            $details[] = 'العنوان: ' . $this->settings->address;
        }
        if (!empty($this->settings->phone)) {
            $details[] = 'الهاتف: ' . $this->settings->phone;
        }
        if (!empty($this->settings->email)) {
            $details[] = 'البريد الإلكتروني: ' . $this->settings->email;
        }
        
        if (!empty($details)) {
            $this->pdf->Cell(0, 6, implode(' | ', $details), 0, 1, 'C');
        }
        
        // CR and VATIN
        $taxInfo = [];
        if (!empty($this->settings->cr)) {
            $taxInfo[] = 'السجل التجاري: ' . $this->settings->cr;
        }
        if (!empty($this->settings->vatin)) {
            $taxInfo[] = 'الرقم الضريبي: ' . $this->settings->vatin;
        }
        
        if (!empty($taxInfo)) {
            $this->pdf->Cell(0, 6, implode(' | ', $taxInfo), 0, 1, 'C');
        }
        
        $this->pdf->Ln(5);
    }

    private function renderAdmissionInfo(): void
    {
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->Cell(0, 8, 'معلومات التنويم', 0, 1, 'R');
        $this->pdf->SetFont('arial', '', 11);
        
        $patientName = $this->admission->patient->name ?? 'غير محدد';
        $admissionId = $this->admission->id;
        
        // Patient Name
        $this->pdf->Cell(40, 7, 'اسم المريض:', 0, 0, 'R');
        $this->pdf->SetFont('arial', 'B', 11);
        $this->pdf->Cell(0, 7, $patientName, 'B', 1, 'R');
        $this->pdf->SetFont('arial', '', 11);
        
        // Admission Number
        $this->pdf->Cell(40, 7, 'رقم التنويم:', 0, 0, 'R');
        $this->pdf->Cell(50, 7, '#' . $admissionId, 0, 0, 'R');
        
        // Admission Date
        $admissionDate = $this->admission->admission_date 
            ? Carbon::parse($this->admission->admission_date)->format('Y/m/d')
            : 'غير محدد';
        $admissionTime = $this->admission->admission_time 
            ? (is_string($this->admission->admission_time) 
                ? substr($this->admission->admission_time, 0, 5)
                : Carbon::parse($this->admission->admission_time)->format('H:i'))
            : '';
        
        $this->pdf->Cell(30, 7, 'تاريخ الدخول:', 0, 0, 'R');
        $this->pdf->Cell(0, 7, $admissionDate . ($admissionTime ? ' ' . $admissionTime : ''), 0, 1, 'R');
        
        // Discharge Date (if exists)
        if ($this->admission->discharge_date) {
            $dischargeDate = Carbon::parse($this->admission->discharge_date)->format('Y/m/d');
            $dischargeTime = $this->admission->discharge_time 
                ? (is_string($this->admission->discharge_time) 
                    ? substr($this->admission->discharge_time, 0, 5)
                    : Carbon::parse($this->admission->discharge_time)->format('H:i'))
                : '';
            
            $this->pdf->Cell(40, 7, 'تاريخ الخروج:', 0, 0, 'R');
            $this->pdf->Cell(50, 7, $dischargeDate . ($dischargeTime ? ' ' . $dischargeTime : ''), 0, 0, 'R');
        }
        
        // Days Admitted
        $daysAdmitted = $this->admission->days_admitted ?? 0;
        $this->pdf->Cell(30, 7, 'أيام الإقامة:', 0, 0, 'R');
        $this->pdf->SetFont('arial', 'B', 11);
        $this->pdf->Cell(0, 7, $daysAdmitted . ' يوم', 0, 1, 'R');
        $this->pdf->SetFont('arial', '', 11);
        
        // Location Info (Ward, Room, Bed or Short Stay Bed)
        if ($this->admission->short_stay_bed_id) {
            $shortStayBed = $this->admission->shortStayBed;
            $this->pdf->Cell(40, 7, 'سرير الإقامة القصيرة:', 0, 0, 'R');
            $this->pdf->Cell(0, 7, ($shortStayBed->bed_number ?? 'غير محدد') . ' (' . ($this->admission->short_stay_duration ?? '') . ')', 0, 1, 'R');
        } else {
            if ($this->admission->ward) {
                $this->pdf->Cell(40, 7, 'القسم:', 0, 0, 'R');
                $this->pdf->Cell(50, 7, $this->admission->ward->name ?? 'غير محدد', 0, 0, 'R');
            }
            
            if ($this->admission->room) {
                $this->pdf->Cell(30, 7, 'الغرفة:', 0, 0, 'R');
                $this->pdf->Cell(0, 7, $this->admission->room->room_number ?? 'غير محدد', 0, 1, 'R');
            }
            
            if ($this->admission->bed) {
                $this->pdf->Cell(40, 7, 'السرير:', 0, 0, 'R');
                $this->pdf->Cell(0, 7, $this->admission->bed->bed_number ?? 'غير محدد', 0, 1, 'R');
            }
        }
        
        // Price Per Day
        $pricePerDay = 0;
        if ($this->admission->short_stay_bed_id && $this->admission->shortStayBed) {
            $pricePerDay = $this->admission->shortStayBed->getPriceForDuration($this->admission->short_stay_duration ?? '24h');
        } elseif ($this->admission->room) {
            $pricePerDay = $this->admission->room->price_per_day ?? 0;
        }
        
        if ($pricePerDay > 0) {
            $this->pdf->Cell(40, 7, 'سعر اليوم:', 0, 0, 'R');
            $this->pdf->SetFont('arial', 'B', 11);
            $this->pdf->Cell(0, 7, number_format($pricePerDay, 2), 0, 1, 'R');
            $this->pdf->SetFont('arial', '', 11);
        }
        
        $this->pdf->Ln(5);
    }

    private function renderSummary(): void
    {
        $summary = $this->ledgerData['summary'] ?? [];
        $totalDebits = $summary['total_debits'] ?? 0;
        $totalCredits = $summary['total_credits'] ?? 0;
        $totalDiscounts = $summary['total_discounts'] ?? 0;
        $balance = $summary['balance'] ?? 0;
        
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->Cell(0, 8, 'ملخص الحساب', 0, 1, 'R');
        
        // Summary Table
        $this->pdf->SetFont('arial', '', 11);
        $this->pdf->SetFillColor(240, 240, 240);
        
        // Header
        $this->pdf->SetFont('arial', 'B', 11);
        $this->pdf->Cell(90, 8, 'البند', 1, 0, 'C', true);
        $this->pdf->Cell(90, 8, 'المبلغ', 1, 1, 'C', true);
        
        $this->pdf->SetFont('arial', '', 11);
        
        // Total Debits (Red)
        $this->pdf->SetTextColor(220, 53, 69);
        $this->pdf->Cell(90, 7, 'إجمالي المستحقات', 1, 0, 'R');
        $this->pdf->Cell(90, 7, number_format($totalDebits, 2), 1, 1, 'R');
        
        // Total Credits (Green)
        $this->pdf->SetTextColor(40, 167, 69);
        $this->pdf->Cell(90, 7, 'إجمالي المدفوعات', 1, 0, 'R');
        $this->pdf->Cell(90, 7, number_format($totalCredits, 2), 1, 1, 'R');
        
        // Total Discounts (Orange)
        $this->pdf->SetTextColor(255, 193, 7);
        $this->pdf->Cell(90, 7, 'إجمالي التخفيضات', 1, 0, 'R');
        $this->pdf->Cell(90, 7, number_format($totalDiscounts, 2), 1, 1, 'R');
        
        // Balance
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('arial', 'B', 12);
        if ($balance > 0) {
            $this->pdf->SetTextColor(220, 53, 69); // Red for positive balance (owed)
        } elseif ($balance < 0) {
            $this->pdf->SetTextColor(40, 167, 69); // Green for negative balance (credit)
        }
        $this->pdf->Cell(90, 8, 'الرصيد المطلوب', 1, 0, 'R', true);
        $this->pdf->Cell(90, 8, number_format(abs($balance), 2), 1, 1, 'R', true);
        
        // Reset text color
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->SetFont('arial', '', 11);
        
        // Balance Status
        $this->pdf->Ln(3);
        if ($balance > 0) {
            $this->pdf->SetTextColor(220, 53, 69);
            $this->pdf->Cell(0, 7, 'حالة الرصيد: مطلوب من المريض', 0, 1, 'R');
        } elseif ($balance < 0) {
            $this->pdf->SetTextColor(40, 167, 69);
            $this->pdf->Cell(0, 7, 'حالة الرصيد: رصيد دائن', 0, 1, 'R');
        } else {
            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->Cell(0, 7, 'حالة الرصيد: متعادل', 0, 1, 'R');
        }
        
        $this->pdf->SetTextColor(0, 0, 0);
        $this->pdf->Ln(5);
    }

    private function renderTransactionsTable(): void
    {
        $entries = $this->ledgerData['entries'] ?? [];
        
        if (empty($entries)) {
            $this->pdf->SetFont('arial', '', 11);
            $this->pdf->Cell(0, 10, 'لا توجد معاملات', 0, 1, 'C');
            return;
        }
        
        $this->pdf->SetFont('arial', 'B', 12);
        $this->pdf->Cell(0, 8, 'تفاصيل المعاملات', 0, 1, 'R');
        
        // Table Header
        $this->pdf->SetFont('arial', 'B', 9);
        $this->pdf->SetFillColor(240, 240, 240);
        
        // Column widths (total 180mm usable width)
        $w = [25, 15, 20, 35, 25, 20, 25, 15];
        
        $this->pdf->Cell($w[0], 8, 'التاريخ', 1, 0, 'C', true);
        $this->pdf->Cell($w[1], 8, 'الوقت', 1, 0, 'C', true);
        $this->pdf->Cell($w[2], 8, 'النوع', 1, 0, 'C', true);
        $this->pdf->Cell($w[3], 8, 'الوصف', 1, 0, 'C', true);
        $this->pdf->Cell($w[4], 8, 'المبلغ', 1, 0, 'C', true);
        $this->pdf->Cell($w[5], 8, 'طريقة الدفع', 1, 0, 'C', true);
        $this->pdf->Cell($w[6], 8, 'الرصيد بعد', 1, 0, 'C', true);
        $this->pdf->Cell($w[7], 8, 'المستخدم', 1, 1, 'C', true);
        
        // Data Rows
        $this->pdf->SetFont('arial', '', 8);
        
        foreach ($entries as $entry) {
            // Check if we need a new page
            if ($this->pdf->GetY() > 250) {
                $this->pdf->AddPage();
                // Redraw header
                $this->pdf->SetFont('arial', 'B', 9);
                $this->pdf->SetFillColor(240, 240, 240);
                $this->pdf->Cell($w[0], 8, 'التاريخ', 1, 0, 'C', true);
                $this->pdf->Cell($w[1], 8, 'الوقت', 1, 0, 'C', true);
                $this->pdf->Cell($w[2], 8, 'النوع', 1, 0, 'C', true);
                $this->pdf->Cell($w[3], 8, 'الوصف', 1, 0, 'C', true);
                $this->pdf->Cell($w[4], 8, 'المبلغ', 1, 0, 'C', true);
                $this->pdf->Cell($w[5], 8, 'طريقة الدفع', 1, 0, 'C', true);
                $this->pdf->Cell($w[6], 8, 'الرصيد بعد', 1, 0, 'C', true);
                $this->pdf->Cell($w[7], 8, 'المستخدم', 1, 1, 'C', true);
                $this->pdf->SetFont('arial', '', 8);
            }
            
            $date = $entry['date'] ?? '';
            $time = $entry['time'] ?? '';
            $referenceType = $entry['reference_type'] ?? '';
            $entryType = $entry['type'] ?? '';
            $description = $entry['description'] ?? '';
            $amount = $entry['amount'] ?? 0;
            $isBank = $entry['is_bank'] ?? false;
            $balanceAfter = $entry['balance_after'] ?? 0;
            $user = $entry['user'] ?? '';
            
            // Determine transaction type label
            $typeLabel = $this->getTransactionTypeLabel($referenceType, $entryType);
            
            // Set color based on transaction type
            if ($referenceType === 'discount') {
                $this->pdf->SetTextColor(255, 193, 7); // Orange for discounts
            } elseif ($entryType === 'debit') {
                $this->pdf->SetTextColor(220, 53, 69); // Red for debits
            } else {
                $this->pdf->SetTextColor(40, 167, 69); // Green for credits
            }
            
            // Format amount
            $amountFormatted = ($amount >= 0 ? '+' : '') . number_format(abs($amount), 2);
            
            // Date
            $this->pdf->SetTextColor(0, 0, 0);
            $this->pdf->Cell($w[0], 7, $date, 1, 0, 'C');
            
            // Time
            $this->pdf->Cell($w[1], 7, substr($time, 0, 5), 1, 0, 'C');
            
            // Type
            $this->pdf->Cell($w[2], 7, $typeLabel, 1, 0, 'C');
            
            // Description
            $this->pdf->Cell($w[3], 7, mb_substr($description, 0, 20), 1, 0, 'R');
            
            // Amount (colored)
            $this->pdf->Cell($w[4], 7, $amountFormatted, 1, 0, 'C');
            
            // Payment Method - Only show for deposits (credit type with deposit reference_type)
            $this->pdf->SetTextColor(0, 0, 0);
            if ($entryType === 'credit' && $referenceType === 'deposit') {
                $paymentMethod = $isBank ? 'بنك' : 'نقد';
                $this->pdf->Cell($w[5], 7, $paymentMethod, 1, 0, 'C');
            } else {
                $this->pdf->Cell($w[5], 7, '-', 1, 0, 'C');
            }
            
            // Balance After
            $this->pdf->Cell($w[6], 7, number_format($balanceAfter, 2), 1, 0, 'C');
            
            // User
            $this->pdf->Cell($w[7], 7, mb_substr($user, 0, 10), 1, 1, 'C');
            
            // Reset text color
            $this->pdf->SetTextColor(0, 0, 0);
        }
        
        $this->pdf->Ln(3);
    }

    private function getTransactionTypeLabel(string $referenceType, string $type): string
    {
        $labels = [
            'service' => 'خدمة',
            'deposit' => 'دفعة',
            'manual' => 'يدوي',
            'lab_test' => 'تحليل',
            'room_charges' => 'رسوم إقامة',
            'charge' => 'رسوم',
            'discount' => 'خصم',
            'short_stay' => 'إقامة قصيرة',
        ];
        
        return $labels[$referenceType] ?? ($type === 'debit' ? 'مدين' : 'دائن');
    }

    private function renderFooter(): void
    {
        $this->pdf->SetY(-20);
        $this->pdf->SetFont('arial', '', 9);
        
        // Page number
        $pageNum = $this->pdf->getPage();
        $totalPages = $this->pdf->getNumPages();
        $this->pdf->Cell(0, 8, 'صفحة ' . $pageNum . ' من ' . $totalPages, 0, 0, 'C');
        
        $this->pdf->Ln(5);
        
        // Print date and time
        $printDateTime = Carbon::now()->format('Y/m/d H:i:s');
        $this->pdf->Cell(0, 8, 'تاريخ الطباعة: ' . $printDateTime, 0, 0, 'C');
    }
}
