<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RequestedServiceDiagnosisResource;
use App\Models\RequestedService;
use App\Models\RequestedServiceDiagnosis;
use App\Services\Pdf\MyCustomTCPDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RequestedServiceDiagnosisController extends Controller
{
    /**
     * GET /requested-services/{requestedService}/diagnosis
     * Returns the diagnosis record for a requested service, including full context
     * needed by the diagnosis page (patient info, service name, etc.).
     */
    public function show(RequestedService $requestedService)
    {
        $requestedService->load([
            'service',
            'doctorVisit.patient',
            'doctorVisit.doctor',
        ]);

        $diagnosis = $requestedService->diagnosis()
            ->with(['user', 'printedByUser'])
            ->first();

        if (!$diagnosis) {
            return response()->json([
                'data' => null,
                'requested_service' => [
                    'id'            => $requestedService->id,
                    'service_name'  => $requestedService->service?->name ?? "خدمة #{$requestedService->service_id}",
                    'patient_name'  => $requestedService->doctorVisit?->patient?->name ?? '—',
                    'patient_phone' => $requestedService->doctorVisit?->patient?->phone ?? null,
                    'visit_id'      => $requestedService->doctorvisits_id,
                    'doctor_name'   => $requestedService->doctorVisit?->doctor?->name ?? '—',
                    'done'          => (bool) $requestedService->done,
                    'created_at'    => $requestedService->created_at?->toIso8601String(),
                ],
            ]);
        }

        $diagnosis->setRelation('requestedService', $requestedService);

        return (new RequestedServiceDiagnosisResource($diagnosis))
            ->additional([
                'requested_service' => [
                    'id'            => $requestedService->id,
                    'service_name'  => $requestedService->service?->name ?? "خدمة #{$requestedService->service_id}",
                    'patient_name'  => $requestedService->doctorVisit?->patient?->name ?? '—',
                    'patient_phone' => $requestedService->doctorVisit?->patient?->phone ?? null,
                    'visit_id'      => $requestedService->doctorvisits_id,
                    'doctor_name'   => $requestedService->doctorVisit?->doctor?->name ?? '—',
                    'done'          => (bool) $requestedService->done,
                    'created_at'    => $requestedService->created_at?->toIso8601String(),
                ],
            ]);
    }

    /**
     * POST /requested-services/{requestedService}/diagnosis
     * "Receive / start" the case — creates the diagnosis record owned by the
     * authenticated user. Idempotent: returns existing record if already created.
     */
    public function store(RequestedService $requestedService)
    {
        $existing = $requestedService->diagnosis()->with(['user', 'printedByUser'])->first();

        if ($existing) {
            return new RequestedServiceDiagnosisResource($existing);
        }

        $diagnosis = RequestedServiceDiagnosis::create([
            'requested_service_id' => $requestedService->id,
            'user_id'              => Auth::id(),
            'diagnosis'            => null,
            'complete'             => false,
            'is_printed'           => false,
        ]);

        return new RequestedServiceDiagnosisResource(
            $diagnosis->load(['user', 'printedByUser'])
        );
    }

    /**
     * PUT /requested-service-diagnoses/{diagnosis}
     * Update diagnosis text, complete flag, or print status.
     */
    public function update(Request $request, RequestedServiceDiagnosis $diagnosis)
    {
        $validated = $request->validate([
            'diagnosis'            => 'sometimes|nullable|string',
            'complete'             => 'sometimes|boolean',
            'is_printed'           => 'sometimes|boolean',
            'printed_by_user_id'   => 'sometimes|nullable|exists:users,id',
        ]);

        if (
            isset($validated['complete']) &&
            $validated['complete'] &&
            !$diagnosis->complete
        ) {
            $validated['completed_at'] = now();
        }

        $diagnosis->update($validated);

        return new RequestedServiceDiagnosisResource(
            $diagnosis->fresh()->load(['user', 'printedByUser'])
        );
    }

    /**
     * GET /requested-service-diagnoses/{diagnosis}/pdf
     * Generate and stream a TCPDF report for this diagnosis.
     */
    public function generatePdf(RequestedServiceDiagnosis $diagnosis)
    {
        $diagnosis->load([
            'user',
            'requestedService.service',
            'requestedService.doctorVisit.patient',
            'requestedService.doctorVisit.doctor',
        ]);

        $rs          = $diagnosis->requestedService;
        $patient     = $rs?->doctorVisit?->patient;
        $doctor      = $rs?->doctorVisit?->doctor;
        $serviceName = $rs?->service?->name ?? 'N/A';

        // ── Build PDF ─────────────────────────────────────────────────────────
        $pdf = new MyCustomTCPDF('Diagnosis Report', null, 'P', 'mm', 'A4');
        $pdf->setRTL(false);
        $pdf->SetMargins(15, 48, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $pw = $pdf->getPageWidth() - 30; // usable width (margins 15+15)
        $col = $pw / 3;
        $font = 'arial';

        // ── Helper: draw one info cell ────────────────────────────────────────
        $drawCell = function (string $label, string $value) use ($pdf, $col, $font) {
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->Rect($x, $y, $col, 12, 'D');
            $pdf->SetFont($font, '', 7);
            $pdf->SetTextColor(120, 120, 120);
            $pdf->SetXY($x + 2, $y + 1.5);
            $pdf->Cell($col - 4, 4, strtoupper($label), 0, 0, 'L');
            $pdf->SetFont($font, 'B', 9);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetXY($x + 2, $y + 6);
            $pdf->Cell($col - 4, 5, $value, 0, 0, 'L');
            $pdf->SetXY($x + $col, $y);
        };

        // ── Row 1: Patient | Service | Doctor ────────────────────────────────
        $drawCell('Patient',  $patient?->name  ?? '—');
        $drawCell('Service',  $serviceName);
        $drawCell('Doctor',   $doctor?->name   ?? '—');
        $pdf->Ln(12);

        // ── Row 2: Diagnosed By | Requested At | Completed At ────────────────
        $drawCell('Diagnosed By',  $diagnosis->user?->name ?? '—');
        $drawCell('Requested At',  $rs?->created_at?->format('Y-m-d h:i A') ?? '—');
        $drawCell(
            $diagnosis->completed_at ? 'Completed At' : 'Status',
            $diagnosis->completed_at
                ? $diagnosis->completed_at->format('Y-m-d h:i A')
                : 'In Progress'
        );
        $pdf->Ln(14);

        // ── Divider ───────────────────────────────────────────────────────────
        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(15, $pdf->GetY(), 15 + $pw, $pdf->GetY());
        $pdf->Ln(4);

        // ── Diagnosis HTML ────────────────────────────────────────────────────
        $pdf->SetFont($font, '', 10);
        $pdf->SetTextColor(0, 0, 0);

        $html = $diagnosis->diagnosis ?? '<p>No diagnosis recorded.</p>';

        // Wrap in LTR div so TCPDF respects left-to-right flow
        $html = '<div style="direction:ltr; text-align:left;">' . $html . '</div>';

        $pdf->writeHTML($html, true, false, true, false, '');

        // ── Footer note ───────────────────────────────────────────────────────
        $pdf->SetFont($font, 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Ln(6);
        $pdf->Cell($pw, 5, 'Printed: ' . now()->format('Y-m-d h:i A') . '   |   ' . ($diagnosis->user?->name ?? ''), 0, 1, 'R');

        // ── Stream ────────────────────────────────────────────────────────────
        $pdfContent = $pdf->Output('DiagnosisReport.pdf', 'S');

        return response($pdfContent, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="DiagnosisReport.pdf"',
            'Content-Length'      => strlen($pdfContent),
        ]);
    }
}
