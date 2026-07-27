<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitPrescriptionRequest;
use App\Http\Requests\UpdateVisitPrescriptionRequest;
use App\Http\Resources\VisitPrescriptionResource;
use App\Models\DoctorVisit;
use App\Models\VisitPrescription;
use App\Services\Pdf\MyCustomTCPDF;
use App\Services\Pdf\VisitPrescriptionsPdf;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class VisitPrescriptionController extends Controller
{
    /**
     * GET /doctor-visits/{doctorVisit}/prescriptions
     */
    public function index(DoctorVisit $doctorVisit): AnonymousResourceCollection
    {
        $prescriptions = $doctorVisit->prescriptions()
            ->with(['user', 'items'])
            ->latest()
            ->get();

        return VisitPrescriptionResource::collection($prescriptions);
    }

    /**
     * POST /doctor-visits/{doctorVisit}/prescriptions
     */
    public function store(StoreVisitPrescriptionRequest $request, DoctorVisit $doctorVisit): VisitPrescriptionResource
    {
        $validated = $request->validated();

        $prescription = DB::transaction(function () use ($validated, $doctorVisit) {
            $prescription = VisitPrescription::create([
                'doctor_visit_id' => $doctorVisit->id,
                'user_id' => Auth::id(),
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $index => $item) {
                $prescription->items()->create([
                    'medication_name' => $item['medication_name'],
                    'dosage' => $item['dosage'] ?? null,
                    'frequency' => $item['frequency'] ?? null,
                    'duration' => $item['duration'] ?? null,
                    'route' => $item['route'] ?? null,
                    'instructions' => $item['instructions'] ?? null,
                    'sort_order' => $index,
                ]);
            }

            return $prescription;
        });

        return new VisitPrescriptionResource($prescription->load(['user', 'items']));
    }

    /**
     * PUT /visit-prescriptions/{visitPrescription}
     */
    public function update(UpdateVisitPrescriptionRequest $request, VisitPrescription $visitPrescription): VisitPrescriptionResource
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $visitPrescription) {
            if (array_key_exists('notes', $validated)) {
                $visitPrescription->update(['notes' => $validated['notes']]);
            }

            if (isset($validated['items'])) {
                $visitPrescription->items()->delete();
                foreach ($validated['items'] as $index => $item) {
                    $visitPrescription->items()->create([
                        'medication_name' => $item['medication_name'],
                        'dosage' => $item['dosage'] ?? null,
                        'frequency' => $item['frequency'] ?? null,
                        'duration' => $item['duration'] ?? null,
                        'route' => $item['route'] ?? null,
                        'instructions' => $item['instructions'] ?? null,
                        'sort_order' => $index,
                    ]);
                }
            }
        });

        return new VisitPrescriptionResource($visitPrescription->fresh()->load(['user', 'items']));
    }

    /**
     * DELETE /visit-prescriptions/{visitPrescription}
     */
    public function destroy(VisitPrescription $visitPrescription): \Illuminate\Http\JsonResponse
    {
        $visitPrescription->delete();

        return response()->json(['message' => 'deleted']);
    }

    /**
     * GET /visit-prescriptions/{visitPrescription}/pdf
     */
    public function generatePdf(VisitPrescription $visitPrescription): Response
    {
        $visitPrescription->load(['user', 'items', 'doctorVisit.patient', 'doctorVisit.doctor']);

        $visit = $visitPrescription->doctorVisit;
        $patient = $visit?->patient;
        $doctor = $visit?->doctor;

        $pdf = new MyCustomTCPDF('Prescription', null, 'P', 'mm', 'A4');
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

        $drawCell('Prescribed By', $visitPrescription->user?->name ?? '—');
        $drawCell('Date', $visitPrescription->created_at?->format('Y-m-d h:i A') ?? '—');
        $drawCell('', '');
        $pdf->Ln(16);

        $pdf->SetFont($font, 'B', 10);
        $pdf->SetFillColor(240, 244, 248);
        $wMed = $pw * 0.3;
        $wDosage = $pw * 0.15;
        $wFreq = $pw * 0.15;
        $wDuration = $pw * 0.15;
        $wInstructions = $pw - $wMed - $wDosage - $wFreq - $wDuration;

        $pdf->Cell($wMed, 8, 'Medication', 1, 0, 'L', true);
        $pdf->Cell($wDosage, 8, 'Dosage', 1, 0, 'C', true);
        $pdf->Cell($wFreq, 8, 'Frequency', 1, 0, 'C', true);
        $pdf->Cell($wDuration, 8, 'Duration', 1, 0, 'C', true);
        $pdf->Cell($wInstructions, 8, 'Instructions', 1, 1, 'L', true);

        $pdf->SetFont($font, '', 9);
        foreach ($visitPrescription->items as $item) {
            $pdf->Cell($wMed, 7, $item->medication_name, 1, 0, 'L');
            $pdf->Cell($wDosage, 7, $item->dosage ?? '—', 1, 0, 'C');
            $pdf->Cell($wFreq, 7, $item->frequency ?? '—', 1, 0, 'C');
            $pdf->Cell($wDuration, 7, $item->duration ?? '—', 1, 0, 'C');
            $pdf->Cell($wInstructions, 7, $item->instructions ?? '—', 1, 1, 'L');
        }

        if ($visitPrescription->notes) {
            $pdf->Ln(4);
            $pdf->SetFont($font, 'I', 9);
            $pdf->MultiCell($pw, 5, 'Notes: '.$visitPrescription->notes, 0, 'L');
        }

        $pdf->SetFont($font, 'I', 8);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Ln(6);
        $pdf->Cell($pw, 5, 'Printed: '.now()->format('Y-m-d h:i A').'   |   '.($visitPrescription->user?->name ?? ''), 0, 1, 'R');

        $pdfContent = $pdf->Output('Prescription.pdf', 'S');

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="Prescription.pdf"',
            'Content-Length' => strlen($pdfContent),
        ]);
    }

    /**
     * GET /doctor-visits/{doctorVisit}/prescriptions-pdf
     *
     * Every prescription order recorded during this visit, combined into one document.
     */
    public function generateAllPdf(DoctorVisit $doctorVisit): Response
    {
        $doctorVisit->load(['patient', 'doctor', 'prescriptions.items']);

        $pdfContent = (new VisitPrescriptionsPdf($doctorVisit))->generate();

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="VisitPrescriptions.pdf"',
            'Content-Length' => strlen($pdfContent),
        ]);
    }
}
