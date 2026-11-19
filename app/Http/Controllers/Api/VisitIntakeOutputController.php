<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitIntakeOutputRequest;
use App\Http\Requests\UpdateVisitIntakeOutputRequest;
use App\Http\Resources\VisitIntakeOutputResource;
use App\Models\DoctorVisit;
use App\Models\VisitIntakeOutput;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class VisitIntakeOutputController extends Controller
{
    /**
     * GET /doctor-visits/{doctorVisit}/intake-outputs
     */
    public function index(DoctorVisit $doctorVisit): AnonymousResourceCollection
    {
        return VisitIntakeOutputResource::collection(
            $doctorVisit->intakeOutputs()->with('recordedByUser')->orderBy('recorded_at')->get()
        );
    }

    /**
     * POST /doctor-visits/{doctorVisit}/intake-outputs
     */
    public function store(StoreVisitIntakeOutputRequest $request, DoctorVisit $doctorVisit): VisitIntakeOutputResource
    {
        $entry = VisitIntakeOutput::create([
            ...$request->validated(),
            'doctor_visit_id' => $doctorVisit->id,
            'patient_id' => $doctorVisit->patient_id,
            'recorded_by_user_id' => Auth::id(),
            'recorded_at' => $request->validated('recorded_at') ?? now(),
        ]);

        return new VisitIntakeOutputResource($entry->load('recordedByUser'));
    }

    /**
     * PUT /doctor-visits/{doctorVisit}/intake-outputs/{intakeOutput}
     */
    public function update(
        UpdateVisitIntakeOutputRequest $request,
        DoctorVisit $doctorVisit,
        VisitIntakeOutput $intakeOutput
    ): VisitIntakeOutputResource {
        abort_if($intakeOutput->doctor_visit_id !== $doctorVisit->id, 404);

        $intakeOutput->update($request->validated());

        return new VisitIntakeOutputResource($intakeOutput->load('recordedByUser'));
    }

    /**
     * DELETE /doctor-visits/{doctorVisit}/intake-outputs/{intakeOutput}
     */
    public function destroy(DoctorVisit $doctorVisit, VisitIntakeOutput $intakeOutput): Response
    {
        abort_if($intakeOutput->doctor_visit_id !== $doctorVisit->id, 404);

        $intakeOutput->delete();

        return response()->noContent();
    }
}
