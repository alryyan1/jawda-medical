<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateVisitMedicalReportRequest;
use App\Http\Resources\VisitMedicalReportResource;
use App\Models\DoctorVisit;
use App\Models\VisitMedicalReport;
use App\Services\Pdf\VisitMedicalReportPdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class VisitMedicalReportController extends Controller
{
    /**
     * GET /doctor-visits/{doctorVisit}/medical-report
     */
    public function show(DoctorVisit $doctorVisit): VisitMedicalReportResource|JsonResponse
    {
        $report = $doctorVisit->medicalReport()->with(['user', 'printedByUser'])->first();

        if (! $report) {
            return response()->json(['data' => null]);
        }

        return new VisitMedicalReportResource($report);
    }

    /**
     * POST /doctor-visits/{doctorVisit}/medical-report
     * Idempotent: returns the existing record if one already exists for this visit.
     */
    public function store(DoctorVisit $doctorVisit): VisitMedicalReportResource
    {
        $existing = $doctorVisit->medicalReport()->with(['user', 'printedByUser'])->first();

        if ($existing) {
            return new VisitMedicalReportResource($existing);
        }

        $report = VisitMedicalReport::create([
            'doctor_visit_id' => $doctorVisit->id,
            'user_id' => Auth::id(),
            'content' => null,
            'complete' => false,
            'is_printed' => false,
        ]);

        return new VisitMedicalReportResource($report->load(['user', 'printedByUser']));
    }

    /**
     * PUT /visit-medical-reports/{visitMedicalReport}
     */
    public function update(UpdateVisitMedicalReportRequest $request, VisitMedicalReport $visitMedicalReport): VisitMedicalReportResource
    {
        $validated = $request->validated();

        if (
            isset($validated['complete']) &&
            $validated['complete'] &&
            ! $visitMedicalReport->complete
        ) {
            $validated['completed_at'] = now();
        }

        $visitMedicalReport->update($validated);

        return new VisitMedicalReportResource($visitMedicalReport->fresh()->load(['user', 'printedByUser']));
    }

    /**
     * GET /visit-medical-reports/{visitMedicalReport}/pdf
     */
    public function generatePdf(VisitMedicalReport $visitMedicalReport): Response
    {
        $visitMedicalReport->load(['user', 'doctorVisit.patient', 'doctorVisit.doctor']);

        $pdfContent = (new VisitMedicalReportPdf($visitMedicalReport))->generate();

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="VisitMedicalReport.pdf"',
            'Content-Length' => strlen($pdfContent),
        ]);
    }
}
