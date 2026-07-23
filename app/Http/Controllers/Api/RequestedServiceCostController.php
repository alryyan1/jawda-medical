<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RequestedServiceCostResource;
use App\Models\Party;
use App\Models\PartyServiceCost;
use App\Models\RequestedService;
use App\Models\RequestedServiceCost;
use App\Services\Pdf\MyCustomTCPDF;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class RequestedServiceCostController extends Controller
{
    /**
     * Display a listing of the costs for a specific requested service.
     */
    public function indexForRequestedService(RequestedService $requestedService): AnonymousResourceCollection
    {
        $costs = $requestedService->costs()
            ->with(['party', 'user:id,name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return RequestedServiceCostResource::collection($costs);
    }

    /**
     * Store a new cost row for the requested service against a party.
     */
    public function store(Request $request, RequestedService $requestedService): RequestedServiceCostResource
    {
        $validated = $request->validate([
            'party_id' => 'required|exists:parties,id',
        ]);

        $partyServiceCost = PartyServiceCost::where('party_id', $validated['party_id'])
            ->where('service_id', $requestedService->service_id)
            ->first();

        $cost = $requestedService->costs()->create([
            'party_id' => $validated['party_id'],
            'amount' => $partyServiceCost?->price,
            'user_id' => Auth::id(),
        ]);

        return new RequestedServiceCostResource($cost->load(['party', 'user']));
    }

    /**
     * Update an existing cost row (amount and/or party).
     */
    public function update(Request $request, RequestedServiceCost $requestedServiceCost): RequestedServiceCostResource
    {
        $validated = $request->validate([
            'amount' => 'nullable|numeric|min:0',
            'party_id' => 'sometimes|required|exists:parties,id',
        ]);

        $requestedServiceCost->update($validated);

        return new RequestedServiceCostResource($requestedServiceCost->load(['party', 'user']));
    }

    /**
     * Delete a cost row.
     */
    public function destroy(RequestedServiceCost $requestedServiceCost): JsonResponse
    {
        $requestedServiceCost->delete();

        return response()->json(null, 204);
    }

    /**
     * Report of patients whose requested services carry a recorded cost,
     * filterable by visit date range and party.
     */
    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d|after_or_equal:date_from',
            'party_id' => 'nullable|integer|exists:parties,id',
        ]);

        $startDate = Carbon::parse($validated['date_from'])->startOfDay();
        $endDate = Carbon::parse($validated['date_to'])->endOfDay();
        $partyId = $validated['party_id'] ?? null;

        $costFilter = function ($query) use ($partyId): void {
            if ($partyId) {
                $query->where('party_id', $partyId);
            }
        };

        $requestedServices = RequestedService::query()
            ->whereHas('costs', $costFilter)
            ->whereHas('doctorVisit', function ($query) use ($startDate, $endDate): void {
                $query->whereBetween('visit_date', [$startDate, $endDate]);
            })
            ->with([
                'service:id,name',
                'doctorVisit:id,patient_id,visit_date',
                'doctorVisit.patient:id,name',
            ])
            ->withSum(['costs as total_cost' => $costFilter], 'amount')
            ->orderBy('doctorvisits_id')
            ->get();

        $items = $requestedServices->map(fn (RequestedService $rs): array => [
            'requested_service_id' => $rs->id,
            'patient_id' => $rs->doctorVisit?->patient?->id,
            'patient_name' => $rs->doctorVisit?->patient?->name,
            'visit_date' => $rs->doctorVisit?->visit_date?->toDateString(),
            'service_id' => $rs->service_id,
            'service_name' => $rs->service?->name,
            'total_cost' => round((float) $rs->total_cost, 2),
        ]);

        return response()->json([
            'data' => $items->values(),
            'total_cost' => round((float) $items->sum('total_cost'), 2),
            'report_period' => [
                'from' => $startDate->toDateString(),
                'to' => $endDate->toDateString(),
            ],
        ]);
    }

    /**
     * PDF export of the patient requested-service costs report.
     */
    public function reportPdf(Request $request): Response
    {
        $jsonResponse = $this->report($request);
        $responseData = json_decode($jsonResponse->getContent(), true);

        $reportData = $responseData['data'] ?? [];
        $reportPeriod = $responseData['report_period'] ?? [
            'from' => $request->date_from,
            'to' => $request->date_to,
        ];
        $grandTotal = (float) ($responseData['total_cost'] ?? 0);

        $reportTitle = 'تقرير تكاليف الخدمات المطلوبة للمرضى';
        $filterCriteria = 'الفترة من: '.$reportPeriod['from'].' إلى: '.$reportPeriod['to'];
        if ($request->filled('party_id')) {
            $party = Party::find($request->party_id);
            if ($party) {
                $filterCriteria .= ' | الجهة: '.$party->name;
            }
        }

        $pdf = new MyCustomTCPDF(reportTitle: $reportTitle, orientation: 'P', unit: 'mm', format: 'A4', filterCriteria: $filterCriteria);
        $pdf->AddPage();
        $fontname = 'helvetica';

        $headers = ['اسم المريض', 'تاريخ الزيارة', 'الخدمة', 'إجمالي التكلفة'];
        $pageWidth = $pdf->getPageWidth() - $pdf->getMargins()['left'] - $pdf->getMargins()['right'];
        $colWidths = [$pageWidth * 0.35, $pageWidth * 0.2, $pageWidth * 0.3, 0];
        $colWidths[count($colWidths) - 1] = $pageWidth - array_sum(array_slice($colWidths, 0, -1));
        $alignments = ['R', 'C', 'R', 'C'];

        $pdf->DrawTableHeader($headers, $colWidths, $alignments);

        $pdf->SetFont($fontname, '', 8);
        $fill = false;

        if (empty($reportData)) {
            $pdf->Cell(array_sum($colWidths), 10, 'لا توجد بيانات.', 1, 1, 'C', $fill);
        } else {
            foreach ($reportData as $row) {
                $rowData = [
                    $row['patient_name'] ?? '-',
                    $row['visit_date'] ?? '-',
                    $row['service_name'] ?? '-',
                    number_format((float) $row['total_cost'], 2),
                ];
                $pdf->DrawTableRow($rowData, $colWidths, $alignments, $fill, 7);
                $fill = ! $fill;
            }
        }

        $pdf->Line($pdf->getMargins()['left'], $pdf->GetY(), $pdf->getPageWidth() - $pdf->getMargins()['right'], $pdf->GetY());

        if (! empty($reportData)) {
            $pdf->SetFont($fontname, 'B', 8.5);
            $summaryRowPdf = ['الإجمالي العام:', '', '', number_format($grandTotal, 2)];
            $pdf->DrawTableRow($summaryRowPdf, $colWidths, $alignments, true, 8);
        }

        $pdfFileName = 'patient_service_costs_report_'.$reportPeriod['from'].'_to_'.$reportPeriod['to'].'.pdf';
        $pdfContent = $pdf->Output($pdfFileName, 'S');

        return response($pdfContent, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', "inline; filename=\"{$pdfFileName}\"");
    }
}
