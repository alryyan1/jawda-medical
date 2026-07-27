<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitVitalRequest;
use App\Http\Requests\UpdateVisitVitalRequest;
use App\Http\Resources\VisitVitalResource;
use App\Models\DoctorVisit;
use App\Models\Patient;
use App\Models\VisitVital;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class VisitVitalController extends Controller
{
    /**
     * GET /doctor-visits/{doctorVisit}/vitals
     */
    public function index(DoctorVisit $doctorVisit): AnonymousResourceCollection
    {
        return VisitVitalResource::collection(
            $doctorVisit->vitals()->orderBy('recorded_at')->get()
        );
    }

    /**
     * POST /doctor-visits/{doctorVisit}/vitals
     */
    public function store(StoreVisitVitalRequest $request, DoctorVisit $doctorVisit): VisitVitalResource
    {
        $vital = VisitVital::create([
            ...$request->validated(),
            'doctor_visit_id' => $doctorVisit->id,
            'patient_id' => $doctorVisit->patient_id,
            'recorded_by_user_id' => Auth::id(),
            'recorded_at' => now(),
        ]);

        return new VisitVitalResource($vital);
    }

    /**
     * PUT /doctor-visits/{doctorVisit}/vitals/{vital}
     *
     * Used by the auto-save form in the doctor portal to keep amending the
     * same reading as the doctor fills in fields one by one, instead of
     * creating a new row per field.
     */
    public function update(UpdateVisitVitalRequest $request, DoctorVisit $doctorVisit, VisitVital $vital): VisitVitalResource
    {
        abort_if($vital->doctor_visit_id !== $doctorVisit->id, 404);

        $vital->update($request->validated());

        return new VisitVitalResource($vital);
    }

    /**
     * DELETE /doctor-visits/{doctorVisit}/vitals/{vital}
     */
    public function destroy(DoctorVisit $doctorVisit, VisitVital $vital): Response
    {
        abort_if($vital->doctor_visit_id !== $doctorVisit->id, 404);

        $vital->delete();

        return response()->noContent();
    }

    /**
     * GET /patients/{patient}/vitals-trend
     *
     * Spans every visit of this patient's File, not just this one visit's
     * Patient row — each visit creates a new Patient row, so a trend scoped
     * to patient_id alone would only ever show a single visit's readings.
     */
    public function trend(Patient $patient): AnonymousResourceCollection
    {
        $vitals = VisitVital::whereIn('patient_id', $patient->siblingPatientIds())
            ->orderBy('recorded_at')
            ->get();

        return VisitVitalResource::collection($vitals);
    }
}
