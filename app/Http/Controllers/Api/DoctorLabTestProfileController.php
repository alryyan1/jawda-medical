<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDoctorLabTestProfileRequest;
use App\Http\Requests\UpdateDoctorLabTestProfileRequest;
use App\Http\Resources\DoctorLabTestProfileResource;
use App\Models\DoctorLabTestProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Auth;

class DoctorLabTestProfileController extends Controller
{
    /**
     * List the authenticated doctor's saved lab test profiles.
     */
    public function index(): AnonymousResourceCollection
    {
        $profiles = DoctorLabTestProfile::with('mainTests')
            ->where('doctor_id', Auth::user()->doctor_id)
            ->orderBy('name')
            ->get();

        return DoctorLabTestProfileResource::collection($profiles);
    }

    public function store(StoreDoctorLabTestProfileRequest $request): JsonResponse
    {
        $profile = DoctorLabTestProfile::create([
            'doctor_id' => Auth::user()->doctor_id,
            'name' => $request->validated('name'),
        ]);

        $profile->mainTests()->sync($request->validated('main_test_ids'));

        return (new DoctorLabTestProfileResource($profile->load('mainTests')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateDoctorLabTestProfileRequest $request, DoctorLabTestProfile $profile): DoctorLabTestProfileResource
    {
        abort_unless($profile->doctor_id === Auth::user()->doctor_id, 403);

        $profile->update(['name' => $request->validated('name')]);
        $profile->mainTests()->sync($request->validated('main_test_ids'));

        return new DoctorLabTestProfileResource($profile->load('mainTests'));
    }

    public function destroy(DoctorLabTestProfile $profile): JsonResponse
    {
        abort_unless($profile->doctor_id === Auth::user()->doctor_id, 403);

        $profile->delete();

        return response()->json(['message' => 'تم حذف المجموعة بنجاح.']);
    }
}
