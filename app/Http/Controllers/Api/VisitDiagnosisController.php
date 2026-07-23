<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVisitDiagnosisRequest;
use App\Http\Resources\VisitDiagnosisResource;
use App\Models\DoctorVisit;
use App\Models\VisitDiagnosis;
use App\Services\Pdf\MyCustomTCPDF;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class VisitDiagnosisController extends Controller
{
    /**
     * GET /doctor-visits/{doctorVisit}/diagnosis
     */
    public function show(DoctorVisit $doctorVisit): VisitDiagnosisResource|\Illuminate\Http\JsonResponse
    {
        $diagnosis = $doctorVisit->diagnosis()->with(['user', 'printedByUser'])->first();

        if (! $diagnosis) {
            return response()->json(['data' => null]);
        }

        return new VisitDiagnosisResource($diagnosis);
    }

    /**
     * POST /doctor-visits/{doctorVisit}/diagnosis
     * Idempotent: returns the existing record if one already exists for this visit.
     */
    public function store(DoctorVisit $doctorVisit): VisitDiagnosisResource
    {
        $existing = $doctorVisit->diagnosis()->with(['user', 'printedByUser'])->first();

        if ($existing) {
            return new VisitDiagnosisResource($existing);
        }

        $diagnosis = VisitDiagnosis::create([
            'doctor_visit_id' => $doctorVisit->id,
            'user_id' => Auth::id(),
            'diagnosis' => null,
            'complete' => false,
            'is_printed' => false,
        ]);

        return new VisitDiagnosisResource($diagnosis->load(['user', 'printedByUser']));
    }

    /**
     * PUT /visit-diagnoses/{visitDiagnosis}
     */
    public function update(UpdateVisitDiagnosisRequest $request, VisitDiagnosis $visitDiagnosis): VisitDiagnosisResource
    {
        $validated = $request->validated();

        if (
            isset($validated['complete']) &&
            $validated['complete'] &&
            ! $visitDiagnosis->complete
        ) {
            $validated['completed_at'] = now();
        }

        $visitDiagnosis->update($validated);

        return new VisitDiagnosisResource($visitDiagnosis->fresh()->load(['user', 'printedByUser']));
    }

    /**
     * GET /visit-diagnoses/{visitDiagnosis}/pdf
     */
    public function generatePdf(VisitDiagnosis $visitDiagnosis): Response
    {
        $visitDiagnosis->load([
            'user',
            'doctorVisit.patient',
            'doctorVisit.doctor',
        ]);

        $visit = $visitDiagnosis->doctorVisit;
        $patient = $visit?->patient;
        $doctor = $visit?->doctor;

        $pdf = new MyCustomTCPDF('Visit Diagnosis Report', null, 'P', 'mm', 'A4');
        $pdf->setRTL(false);
        $pdf->SetMargins(15, 48, 15);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->AddPage();

        $pw = $pdf->getPageWidth() - 30;
        $col = $pw / 3;
        $font = 'arial';

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

        $drawCell('Patient', $patient?->name ?? '—');
        $drawCell('Visit', '#'.($visit?->id ?? '—'));
        $drawCell('Doctor', $doctor?->name ?? '—');
        $pdf->Ln(12);

        $drawCell('Diagnosed By', $visitDiagnosis->user?->name ?? '—');
        $drawCell('Visit Date', $visit?->visit_date?->format('Y-m-d') ?? '—');
        $drawCell(
            $visitDiagnosis->completed_at ? 'Completed At' : 'Status',
            $visitDiagnosis->completed_at
                ? $visitDiagnosis->completed_at->format('Y-m-d h:i A')
                : 'In Progress'
        );
        $pdf->Ln(14);

        $pdf->SetDrawColor(180, 180, 180);
        $pdf->Line(15, $pdf->GetY(), 15 + $pw, $pdf->GetY());
        $pdf->Ln(4);

        $pdf->SetFont($font, '', 10);
        $pdf->SetTextColor(0, 0, 0);

        $html = $visitDiagnosis->diagnosis ?? '<p>No diagnosis recorded.</p>';
        $html = '<div style="direction:ltr; text-align:left;">'.$html.'</div>';

        $pdf->writeHTML($html, true, false, true, false, '');

        $pdf->SetFont($font, 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Ln(6);
        $pdf->Cell($pw, 5, 'Printed: '.now()->format('Y-m-d h:i A').'   |   '.($visitDiagnosis->user?->name ?? ''), 0, 1, 'R');

        $pdfContent = $pdf->Output('VisitDiagnosisReport.pdf', 'S');

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="VisitDiagnosisReport.pdf"',
            'Content-Length' => strlen($pdfContent),
        ]);
    }
}
