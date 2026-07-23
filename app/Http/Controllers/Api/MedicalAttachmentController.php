<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreMedicalAttachmentRequest;
use App\Http\Resources\MedicalAttachmentResource;
use App\Models\DoctorVisit;
use App\Models\MedicalAttachment;
use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MedicalAttachmentController extends Controller
{
    /**
     * GET /patients/{patient}/attachments
     */
    public function index(Patient $patient): AnonymousResourceCollection
    {
        $attachments = $patient->attachments()
            ->with(['uploadedByUser'])
            ->latest()
            ->get();

        return MedicalAttachmentResource::collection($attachments);
    }

    /**
     * POST /doctor-visits/{doctorVisit}/attachments
     */
    public function store(StoreMedicalAttachmentRequest $request, DoctorVisit $doctorVisit): MedicalAttachmentResource
    {
        $validated = $request->validated();
        $file = $request->file('file');

        $ext = $file->getClientOriginalExtension();
        $filename = Str::uuid().'.'.$ext;
        $file->storeAs('public/medical-attachments', $filename);

        $attachment = MedicalAttachment::create([
            'patient_id' => $doctorVisit->patient_id,
            'doctor_visit_id' => $doctorVisit->id,
            'uploaded_by_user_id' => Auth::id(),
            'category' => $validated['category'] ?? 'other',
            'title' => $validated['title'] ?? null,
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => 'medical-attachments/'.$filename,
            'mime_type' => $file->getClientMimeType(),
            'file_size' => $file->getSize(),
            'note' => $validated['note'] ?? null,
        ]);

        return new MedicalAttachmentResource($attachment->load('uploadedByUser'));
    }

    /**
     * DELETE /attachments/{attachment}
     */
    public function destroy(MedicalAttachment $attachment): JsonResponse
    {
        Storage::delete('public/'.$attachment->file_path);
        $attachment->delete();

        return response()->json(['message' => 'deleted']);
    }
}
