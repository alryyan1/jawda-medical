<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use App\Models\Service;
use App\Models\DoctorService; // The pivot model
use Illuminate\Http\Request;
use App\Http\Resources\DoctorServiceResource; // Create this resource
use App\Http\Resources\ServiceResource; // For listing available services
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;

class DoctorServiceController extends Controller
{
    public function __construct()
    {
        // Permissions: e.g., 'manage doctor_service_contracts'
        // $this->middleware('can:manage doctor_service_contracts');
    }

    /**
     * List all services configured for a specific doctor.
     */
    public function index(Request $request, Doctor $doctor)
    {
        // $this->authorize('view', $doctor); // Or a specific permission to view their service configs
        
        $query = $doctor->specificServices()->with('serviceGroup'); // Eager load service group via Service model

        if ($request->filled('search_service_name')) {
            $query->where('services.name', 'LIKE', '%' . $request->search_service_name . '%');
        }
        
        $doctorServices = $query->orderBy('services.name')->paginate($request->get('per_page', 10));
        
        // We need to transform this to include the pivot data correctly.
        // DoctorServiceResource will handle this transformation if 'pivot' is accessed.
        return DoctorServiceResource::collection($doctorServices);
    }

    /**
     * List services NOT yet configured for this doctor.
     */
    public function availableServices(Doctor $doctor)
    {
        // $this->authorize('update', $doctor); // If only those who can edit can see available
        $configuredServiceIds = $doctor->specificServices()->pluck('services.id');
        $availableServices = Service::where('activate', true)
            ->whereNotIn('id', $configuredServiceIds)
            ->with('serviceGroup')
            ->orderBy('name')
            ->get();
        return ServiceResource::collection($availableServices);
    }

    /**
     * Add a service with specific terms for a doctor.
     */
    public function store(Request $request, Doctor $doctor)
    {
        // $this->authorize('update', $doctor); // Permission to modify doctor's service configs
        $validated = $request->validate([
            'service_id' => [
                'required', 'integer', 'exists:services,id',
                Rule::unique('doctor_services')->where(fn ($query) => $query->where('doctor_id', $doctor->id))
            ],
            'percentage' => 'nullable|numeric|min:0|max:100|required_without:fixed',
            'fixed' => 'nullable|numeric|min:0|required_without:percentage',
        ]);

        if (empty($validated['percentage']) && empty($validated['fixed'])) {
             return response()->json(['message' => 'يجب توفير نسبة مئوية أو مبلغ ثابت للطبيب.'], 422);
        }
        // if (!empty($validated['percentage']) && !empty($validated['fixed'])) {
        //      return response()->json(['message' => 'يرجى توفير إما نسبة مئوية أو مبلغ ثابت، وليس كلاهما.'], 422);
        // }


        // Attach the service with pivot data
        $doctor->specificServices()->attach($validated['service_id'], [
            'percentage' => $validated['percentage'] ?? null,
            'fixed' => $validated['fixed'] ?? null,
            // created_at, updated_at will be handled if $timestamps = true on pivot model
        ]);

        // Retrieve the newly created pivot record for the response
        $doctorServiceEntry = $doctor->specificServices()->where('services.id', $validated['service_id'])->first();
        
        return new DoctorServiceResource($doctorServiceEntry);
    }

    /**
     * Update the terms for a specific service for a doctor.
     * The $service model here is bound from the {service} route parameter, representing the service being updated FOR this doctor.
     */
    public function update(Request $request, Doctor $doctor, Service $service)
    {
        // $this->authorize('update', $doctor);
        
        // Ensure the doctor-service link exists
        if (!$doctor->specificServices()->where('services.id', $service->id)->exists()) {
            return response()->json(['message' => 'هذه الخدمة غير مضافة لهذا الطبيب.'], 404);
        }

        $validated = $request->validate([
            'percentage' => 'nullable|numeric|min:0|max:100|required_without:fixed',
            'fixed' => 'nullable|numeric|min:0|required_without:percentage',
        ]);

        if (empty($validated['percentage']) && empty($validated['fixed'])) {
             return response()->json(['message' => 'يجب توفير نسبة مئوية أو مبلغ ثابت.'], 422);
        }
        //  if (!empty($validated['percentage']) && !empty($validated['fixed'])) {
        //      return response()->json(['message' => 'يرجى توفير إما نسبة مئوية أو مبلغ ثابت، وليس كلاهما.'], 422);
        // }

        $doctor->specificServices()->updateExistingPivot($service->id, [
            'percentage' => $validated['percentage'] ?? null,
            'fixed' => $validated['fixed'] ?? null,
        ]);

        $updatedEntry = $doctor->specificServices()->where('services.id', $service->id)->first();
        return new DoctorServiceResource($updatedEntry);
    }

    /**
     * Remove a service configuration from a doctor.
     */
    public function destroy(Doctor $doctor, Service $service)
    {
        // $this->authorize('update', $doctor);
        if (!$doctor->specificServices()->where('services.id', $service->id)->exists()) {
            return response()->json(['message' => 'Service not configured for this doctor.'], 404);
        }
        $doctor->specificServices()->detach($service->id);
        return response()->json(null, 204);
    }
}