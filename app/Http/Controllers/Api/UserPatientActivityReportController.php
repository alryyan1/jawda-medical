<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Patient;
use App\Models\User;
use App\Services\Pdf\MyCustomTCPDF;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

class UserPatientActivityReportController extends Controller
{
    /**
     * Discount percentage, relative to the gross total of a patient's tests
     * and services, at or above which a row is flagged as high-risk for the
     * reviewing manager.
     */
    private const HIGH_RISK_DISCOUNT_PERCENT = 50;

    /**
     * Audit report of a single user's patient registrations: every patient
     * they registered in a date range, optionally restricted to one company
     * (or cash patients), with the discounts applied on that patient's lab
     * tests and services surfaced so a manager can review the user's conduct.
     */
    public function report(Request $request): JsonResponse
    {
        // Query strings carry "true"/"false" literally, which the `boolean`
        // rule below doesn't accept; normalize to an actual boolean first.
        if ($request->has('discounted_only')) {
            $request->merge(['discounted_only' => $request->boolean('discounted_only')]);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'company_id' => 'nullable|integer|exists:companies,id',
            'discounted_only' => 'nullable|boolean',
        ]);

        $user = User::findOrFail($validated['user_id']);
        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();
        $companyId = $validated['company_id'] ?? null;
        $discountedOnly = (bool) ($validated['discounted_only'] ?? false);

        $patients = Patient::query()
            ->where('user_id', $user->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->when($companyId, fn ($query, $id) => $query->where('company_id', $id))
            ->when($discountedOnly, function ($query): void {
                $query->where(function ($query): void {
                    $query->whereHas('doctorVisit.labRequests', function ($query): void {
                        $query->where('discount_per', '>', 0);
                    })->orWhereHas('doctorVisit.requestedServices', function ($query): void {
                        $query->where(function ($query): void {
                            $query->where('discount', '>', 0)->orWhere('discount_per', '>', 0);
                        });
                    });
                });
            })
            ->with([
                'company:id,name',
                'doctorVisit:id,patient_id,visit_date,doctor_id',
                'doctorVisit.doctor:id,name',
                'doctorVisit.labRequests:id,doctor_visit_id,price,discount_per,amount_paid',
                'doctorVisit.requestedServices' => function ($query): void {
                    // RequestedService::amount_paid is computed from its deposits
                    // relation, not a raw column, so it must be eager-summed here
                    // to avoid an N+1 query per row (see RequestedService::totalDeposits()).
                    $query->select('id', 'doctorvisits_id', 'price', 'count', 'discount', 'discount_per')
                        ->withSum('deposits', 'amount');
                },
            ])
            ->orderByDesc('created_at')
            ->get();

        $items = $patients->map(fn (Patient $patient): array => $this->mapPatient($patient));

        return response()->json([
            'data' => $items->values(),
            'summary' => $this->buildSummary($items),
            'by_company' => $this->buildCompanyBreakdown($items),
            'repeat_patients' => $this->buildRepeatPatients($items),
            'user' => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username],
            'report_period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
            'filters' => [
                'company_name' => $companyId ? Company::find($companyId)?->name : null,
                'discounted_only' => $discountedOnly,
            ],
        ]);
    }

    /**
     * PDF export of the user patient-activity audit report.
     */
    public function reportPdf(Request $request): Response
    {
        $jsonResponse = $this->report($request);
        $responseData = json_decode($jsonResponse->getContent(), true);

        $reportData = $responseData['data'] ?? [];
        $summary = $responseData['summary'] ?? [];
        $byCompany = $responseData['by_company'] ?? [];
        $repeatPatients = $responseData['repeat_patients'] ?? [];
        $reportUser = $responseData['user'] ?? ['name' => '-'];
        $reportFilters = $responseData['filters'] ?? [];
        $reportPeriod = $responseData['report_period'] ?? [
            'from' => $request->date_from,
            'to' => $request->date_to,
        ];

        $reportTitle = 'تقرير مراقبة نشاط تسجيل المرضى - المستخدم: '.$reportUser['name'];
        $filterCriteria = 'الفترة من: '.$reportPeriod['from'].' إلى: '.$reportPeriod['to'];
        if (! empty($reportFilters['company_name'])) {
            $filterCriteria .= ' | الشركة: '.$reportFilters['company_name'];
        }
        if (! empty($reportFilters['discounted_only'])) {
            $filterCriteria .= ' | عرض الخصومات فقط';
        }

        $pdf = new MyCustomTCPDF(reportTitle: $reportTitle, orientation: 'L', unit: 'mm', format: 'A4', filterCriteria: $filterCriteria);
        $pdf->AddPage();
        // DrawTableHeader()/DrawTableRow() always render with the PDF's own
        // Arabic-capable font regardless of what's passed to them, but Cell()/
        // MultiCell() calls made directly (as below) do not — 'helvetica' has
        // no Arabic glyphs and silently prints "?????" for Arabic text.
        $fontname = $pdf->getDefaultFontFamily();

        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $this->drawSummaryTiles($pdf, $fontname, $pageWidth, $summary);
        $pdf->Ln(1.5);

        $headers = ['اسم المريض', 'رقم الهاتف', 'الشركة', 'تاريخ التسجيل', 'عدد العناصر', 'الإجمالي', 'الخصم', 'الصافي', 'نسبة الخصم'];
        // The first 8 fractions must leave enough of the remaining width for
        // the last (dynamic-remainder) column — they previously summed to
        // 0.99, crushing "نسبة الخصم" down to ~1% of the page and forcing
        // TCPDF to wrap "10%" across 4 lines, ballooning every row's height.
        $colWidths = [$pageWidth * 0.16, $pageWidth * 0.1, $pageWidth * 0.12, $pageWidth * 0.1, $pageWidth * 0.07, $pageWidth * 0.1, $pageWidth * 0.1, $pageWidth * 0.1, 0];
        $colWidths[count($colWidths) - 1] = $pageWidth - array_sum(array_slice($colWidths, 0, -1));
        $alignments = ['R', 'C', 'R', 'C', 'C', 'C', 'C', 'C', 'C'];

        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        $fill = false;
        if (empty($reportData)) {
            $pdf->SetFont($fontname, '', 8);
            $pdf->Cell(array_sum($colWidths), 10, 'لا توجد بيانات.', 1, 1, 'C', $fill);
        } else {
            foreach ($reportData as $row) {
                $rowData = [
                    $row['patient_name'] ?? '-',
                    $row['phone'] ?? '-',
                    $row['company_name'] ?? 'نقد',
                    $row['registered_at'] ?? '-',
                    $row['items_count'] ?? 0,
                    number_format((float) ($row['gross_total'] ?? 0), 2),
                    number_format((float) ($row['discount_total'] ?? 0), 2),
                    number_format((float) ($row['net_total'] ?? 0), 2),
                    ($row['discount_percent'] ?? 0).'%',
                ];

                if (! empty($row['is_high_risk'])) {
                    $pdf->SetTextColor(200, 0, 0);
                }
                $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill, 5, 8);
                $pdf->SetTextColor(0);
                $fill = ! $fill;
            }
        }

        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        if (! empty($reportData)) {
            $summaryRowPdf = ['الإجمالي العام:', '', '', '', '', '', number_format((float) ($summary['total_discount'] ?? 0), 2), '', ''];
            $pdf->DrawTableRow($summaryRowPdf, $colWidths, $alignments, true, 5, 8.5);
        }

        if (! empty($byCompany)) {
            $pdf->Ln(2);
            $pdf->SetFont($fontname, 'B', 9);
            $pdf->Cell(0, 5, 'توزيع الخصومات حسب الشركة', 0, 1, 'R');

            $companyHeaders = ['الشركة', 'عدد المرضى', 'إجمالي الخصم'];
            $companyWidths = [$pageWidth * 0.5, $pageWidth * 0.25, 0];
            $companyWidths[2] = $pageWidth - $companyWidths[0] - $companyWidths[1];
            $companyAlignments = ['R', 'C', 'C'];

            $pdf->DrawTableHeader($companyHeaders, $companyWidths, $companyAlignments, 5);
            $fill = false;
            foreach ($byCompany as $companyRow) {
                $pdf->DrawTableRow([
                    $companyRow['company_name'] ?? 'نقد',
                    $companyRow['patients_count'] ?? 0,
                    number_format((float) ($companyRow['discount_total'] ?? 0), 2),
                ], $companyWidths, $companyAlignments, $fill, 5, 8);
                $fill = ! $fill;
            }
        }

        if (! empty($repeatPatients)) {
            $pdf->Ln(2);
            $pdf->SetFont($fontname, 'B', 9);
            $pdf->SetTextColor(200, 0, 0);
            $pdf->Cell(0, 5, 'مرضى تكرر تخفيضهم (قد تستدعي المراجعة)', 0, 1, 'R');
            $pdf->SetTextColor(0);

            $repeatHeaders = ['اسم المريض', 'الهاتف', 'عدد مرات التكرار', 'إجمالي الخصم'];
            $repeatWidths = [$pageWidth * 0.35, $pageWidth * 0.2, $pageWidth * 0.2, 0];
            $repeatWidths[3] = $pageWidth - array_sum(array_slice($repeatWidths, 0, 3));
            $repeatAlignments = ['R', 'C', 'C', 'C'];

            $pdf->DrawTableHeader($repeatHeaders, $repeatWidths, $repeatAlignments, 5, [255, 225, 225]);
            $fill = false;
            foreach ($repeatPatients as $repeatRow) {
                $pdf->SetTextColor(200, 0, 0);
                $pdf->DrawTableRow([
                    $repeatRow['patient_name'] ?? '-',
                    $repeatRow['phone'] ?? '-',
                    $repeatRow['times'] ?? 0,
                    number_format((float) ($repeatRow['total_discount'] ?? 0), 2),
                ], $repeatWidths, $repeatAlignments, $fill, 5, 8);
                $pdf->SetTextColor(0);
                $fill = ! $fill;
            }
        }

        $pdfFileName = 'user_patient_activity_report_'.$reportPeriod['from'].'_to_'.$reportPeriod['to'].'.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }

    /**
     * Draws the summary stats as a row of bordered tiles (label row + value
     * row) instead of a single run-on line of pipe-separated text.
     *
     * @param  array<string, mixed>  $summary
     */
    private function drawSummaryTiles(MyCustomTCPDF $pdf, string $fontname, float $pageWidth, array $summary): void
    {
        $tiles = [
            ['label' => 'إجمالي المرضى', 'value' => (string) ($summary['total_patients'] ?? 0)],
            ['label' => 'مرضى بتخفيض', 'value' => (string) ($summary['discounted_patients_count'] ?? 0)],
            ['label' => 'نسبة الخصم', 'value' => ($summary['discount_rate_percent'] ?? 0).'%'],
            ['label' => 'إجمالي الخصومات', 'value' => number_format((float) ($summary['total_discount'] ?? 0), 2)],
            ['label' => 'خصم كامل (100%)', 'value' => (string) ($summary['full_discount_count'] ?? 0)],
        ];
        $tileWidth = $pageWidth / count($tiles);

        $pdf->SetDrawColor(180, 180, 180);
        $pdf->SetLineWidth(0.15);
        $pdf->SetTextColor(90, 90, 90);
        $pdf->SetFillColor(240, 240, 245);
        $pdf->SetFont($fontname, 'B', 7);
        foreach ($tiles as $tile) {
            $pdf->Cell($tileWidth, 4.5, $tile['label'], 1, 0, 'C', true);
        }
        $pdf->Ln(4.5);

        $pdf->SetTextColor(20, 20, 20);
        $pdf->SetFillColor(255, 255, 255);
        $pdf->SetFont($fontname, 'B', 9.5);
        foreach ($tiles as $tile) {
            $pdf->Cell($tileWidth, 6, $tile['value'], 1, 0, 'C', true);
        }
        $pdf->Ln(6);
        $pdf->SetTextColor(0);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPatient(Patient $patient): array
    {
        $visit = $patient->doctorVisit;
        $labRequests = $visit?->labRequests ?? collect();
        $requestedServices = $visit?->requestedServices ?? collect();

        // labrequests has no `count` column; each row is a single test (quantity 1).
        $labGross = $labRequests->sum(fn ($lr) => (float) $lr->price);
        $labDiscount = $labRequests->sum(function ($lr): float {
            return round((float) $lr->price * ((int) $lr->discount_per / 100), 2);
        });

        $serviceGross = $requestedServices->sum(fn ($rs) => (float) $rs->price * max(1, (int) $rs->count));
        $serviceDiscount = $requestedServices->sum(function ($rs): float {
            $subtotal = (float) $rs->price * max(1, (int) $rs->count);

            return round((float) $rs->discount + $subtotal * ((int) $rs->discount_per / 100), 2);
        });

        $labPaid = $labRequests->sum(fn ($lr) => (float) $lr->amount_paid);
        $servicePaid = $requestedServices->sum(fn ($rs) => (float) $rs->amount_paid);

        $grossTotal = round($labGross + $serviceGross, 2);
        $discountTotal = round($labDiscount + $serviceDiscount, 2);
        $netTotal = round($grossTotal - $discountTotal, 2);
        $amountPaid = round($labPaid + $servicePaid, 2);
        $discountPercent = $grossTotal > 0 ? round(($discountTotal / $grossTotal) * 100, 1) : 0.0;

        $hasFullDiscount = $labRequests->contains(fn ($lr) => (int) $lr->discount_per >= 100)
            || $requestedServices->contains(function ($rs): bool {
                $subtotal = (float) $rs->price * max(1, (int) $rs->count);
                $itemDiscount = (float) $rs->discount + $subtotal * ((int) $rs->discount_per / 100);

                return (int) $rs->discount_per >= 100 || ($subtotal > 0 && $itemDiscount >= $subtotal);
            });

        return [
            'patient_id' => $patient->id,
            'patient_name' => $patient->name,
            'phone' => $patient->phone,
            'company_name' => $patient->company?->name,
            'registered_at' => $patient->created_at?->toDateString(),
            'registered_time' => $patient->created_at?->format('h:i A'),
            'visit_id' => $visit?->id,
            'visit_date' => $visit?->visit_date?->toDateString(),
            'doctor_name' => $visit?->doctor?->name,
            'items_count' => $labRequests->count() + $requestedServices->count(),
            'gross_total' => $grossTotal,
            'amount_paid' => $amountPaid,
            'discount_total' => $discountTotal,
            'net_total' => $netTotal,
            'discount_percent' => $discountPercent,
            'has_full_discount' => $hasFullDiscount,
            'is_high_risk' => $discountPercent >= self::HIGH_RISK_DISCOUNT_PERCENT,
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    private function buildSummary(Collection $items): array
    {
        $totalPatients = $items->count();
        $discounted = $items->filter(fn (array $item) => $item['discount_total'] > 0);
        $totalGross = round((float) $items->sum('gross_total'), 2);
        $totalDiscount = round((float) $items->sum('discount_total'), 2);

        return [
            'total_patients' => $totalPatients,
            'discounted_patients_count' => $discounted->count(),
            'discount_rate_percent' => $totalPatients > 0 ? round(($discounted->count() / $totalPatients) * 100, 1) : 0.0,
            'total_gross' => $totalGross,
            'total_discount' => $totalDiscount,
            'total_net' => round($totalGross - $totalDiscount, 2),
            'full_discount_count' => $items->filter(fn (array $item) => $item['has_full_discount'])->count(),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildCompanyBreakdown(Collection $items): array
    {
        return $items
            ->groupBy(fn (array $item) => $item['company_name'] ?: 'نقد')
            ->map(function (Collection $group, string $companyName): array {
                return [
                    'company_name' => $companyName,
                    'patients_count' => $group->count(),
                    'discount_total' => round((float) $group->sum('discount_total'), 2),
                ];
            })
            ->sortByDesc('discount_total')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function buildRepeatPatients(Collection $items): array
    {
        return $items
            ->filter(fn (array $item) => $item['discount_total'] > 0)
            ->groupBy(fn (array $item) => $item['patient_name'].'|'.$item['phone'])
            ->filter(fn (Collection $group) => $group->count() > 1)
            ->map(function (Collection $group): array {
                $first = $group->first();

                return [
                    'patient_name' => $first['patient_name'],
                    'phone' => $first['phone'],
                    'times' => $group->count(),
                    'total_discount' => round((float) $group->sum('discount_total'), 2),
                ];
            })
            ->sortByDesc('total_discount')
            ->values()
            ->all();
    }
}
