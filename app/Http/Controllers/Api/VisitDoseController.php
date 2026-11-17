<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendVisitDoseWhatsAppRequest;
use App\Http\Requests\StoreVisitDoseRequest;
use App\Http\Requests\UpdateVisitDoseRequest;
use App\Http\Resources\VisitDoseResource;
use App\Jobs\SendDoseGivenWhatsAppNotice;
use App\Models\DoctorVisit;
use App\Models\VisitDose;
use App\Services\JawdaInpatientClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class VisitDoseController extends Controller
{
    /**
     * GET /doctor-visits/{doctorVisit}/doses
     */
    public function index(DoctorVisit $doctorVisit): AnonymousResourceCollection
    {
        return VisitDoseResource::collection(
            $doctorVisit->doses()->with('administeredByUser')->orderBy('administered_at')->get()
        );
    }

    /**
     * POST /doctor-visits/{doctorVisit}/doses
     */
    public function store(StoreVisitDoseRequest $request, DoctorVisit $doctorVisit): VisitDoseResource
    {
        $dose = VisitDose::create([
            ...$request->validated(),
            'doctor_visit_id' => $doctorVisit->id,
            'patient_id' => $doctorVisit->patient_id,
            'administered_by_user_id' => Auth::id(),
            'administered_at' => $request->validated('administered_at') ?? now(),
        ]);

        if ($dose->status === VisitDose::STATUS_GIVEN) {
            SendDoseGivenWhatsAppNotice::dispatch($dose->id)->onQueue('notifications');
        }

        return new VisitDoseResource($dose->load('administeredByUser'));
    }

    /**
     * PUT /doctor-visits/{doctorVisit}/doses/{dose}
     */
    public function update(UpdateVisitDoseRequest $request, DoctorVisit $doctorVisit, VisitDose $dose): VisitDoseResource
    {
        abort_if($dose->doctor_visit_id !== $doctorVisit->id, 404);

        $wasGiven = $dose->status === VisitDose::STATUS_GIVEN;

        $dose->update($request->validated());

        if (! $wasGiven && $dose->status === VisitDose::STATUS_GIVEN) {
            SendDoseGivenWhatsAppNotice::dispatch($dose->id)->onQueue('notifications');
        }

        return new VisitDoseResource($dose->load('administeredByUser'));
    }

    /**
     * DELETE /doctor-visits/{doctorVisit}/doses/{dose}
     */
    public function destroy(DoctorVisit $doctorVisit, VisitDose $dose): Response
    {
        abort_if($dose->doctor_visit_id !== $doctorVisit->id, 404);

        $dose->delete();

        return response()->noContent();
    }

    /**
     * POST /doctor-visits/{doctorVisit}/doses/{dose}/send-whatsapp
     */
    public function sendWhatsapp(
        SendVisitDoseWhatsAppRequest $request,
        DoctorVisit $doctorVisit,
        VisitDose $dose,
        JawdaInpatientClient $jawdaInpatientClient
    ): JsonResponse {
        abort_if($dose->doctor_visit_id !== $doctorVisit->id, 404);

        $result = (new SendDoseGivenWhatsAppNotice($dose->id, $request->validated('phone')))
            ->send($jawdaInpatientClient);

        return response()->json($result, $result['success'] ? 200 : 422);
    }

    /**
     * GET /doctor-visits/{doctorVisit}/companion-contact
     */
    public function companionContact(DoctorVisit $doctorVisit, JawdaInpatientClient $jawdaInpatientClient): JsonResponse
    {
        if (! $doctorVisit->admission_id) {
            return response()->json(['name' => null, 'phone' => null]);
        }

        try {
            $contact = $jawdaInpatientClient->getCompanionContact($doctorVisit->admission_id);
        } catch (\Throwable) {
            return response()->json(['name' => null, 'phone' => null]);
        }

        return response()->json([
            'name' => $contact['name'] ?? null,
            'phone' => $contact['phone'] ?? null,
        ]);
    }
}
