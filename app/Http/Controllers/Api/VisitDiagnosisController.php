<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVisitDiagnosisRequest;
use App\Http\Resources\VisitDiagnosisResource;
use App\Models\DoctorVisit;
use App\Models\VisitDiagnosis;
use App\Services\Pdf\VisitDiagnosisPdf;
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
        $pdfContent = (new VisitDiagnosisPdf($visitDiagnosis))->generate();

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="VisitDiagnosisReport.pdf"',
            'Content-Length' => strlen($pdfContent),
        ]);
    }
}
