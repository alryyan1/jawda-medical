<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DoctorCollection;
use App\Http\Resources\DoctorResource;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // If handling image uploads
use Illuminate\Support\Facades\Storage;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $query = Doctor::query();

        // Apply search filter if search parameter is present
        if ($request->has('search')) {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('phone', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Eager load relationships for efficiency
        $doctors = $query->with(['specialist', 'user'])->orderBy('id', 'desc')
            ->paginate(15);

        return new DoctorCollection($doctors);
    }

    public function indexList()
    {
        // Returns a simple list of doctors (id and name) for dropdowns
        // Eager load specialist to include specialist name if needed for display in dropdown
        $doctors = Doctor::with('specialist:id,name') // Only select id and name from specialist
            ->orderBy('name')
            ->get(['id', 'name', 'specialist_id', 'is_default']);

        return DoctorResource::collection($doctors);
        // If DoctorResource is too heavy for just a list, you could return directly:
        // return response()->json($doctors->map(function ($doctor) {
        //     return [
        //         'id' => $doctor->id,
        //         'name' => $doctor->name . ($doctor->specialist ? ' (' . $doctor->specialist->name . ')' : ''),
        //     ];
        // }));
    }

    public function allDoctors()
    {
        $doctors = Doctor::with('specialist:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'specialist_id']);

        return response()->json([
            'data' => $doctors->map(fn ($d) => [
                'id' => $d->id,
                'name' => $d->name,
                'specialist_name' => $d->specialist?->name,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string',
            'cash_percentage' => 'required|numeric|min:0|max:100',
            'company_percentage' => 'required|numeric|min:0|max:100',
            'static_wage' => 'required|numeric|min:0',
            'lab_percentage' => 'required|numeric|min:0|max:100',
            'specialist_id' => 'required|exists:specialists,id',
            'start' => 'required|integer',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // For file upload
            'calc_insurance' => 'required|boolean',
            'is_default' => 'sometimes|boolean',
            // Add validation for linking to a user if applicable
            // 'user_id_to_link' => 'nullable|exists:users,id|unique:doctors,user_id_column_if_doctor_has_user'
        ]);

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('doctors_images', 'public');
            $validatedData['image'] = $path; // Store the path
        }
        unset($validatedData['image_file']); // remove temp key

        $doctor = DB::transaction(function () use ($validatedData) {
            $doc = Doctor::create($validatedData);
            if (! empty($validatedData['is_default'])) {
                Doctor::where('id', '!=', $doc->id)->update(['is_default' => false]);
            }

            return $doc;
        });

        // If you are creating/linking a User for this doctor:
        // $user = User::find($request->user_id_to_link);
        // if ($user) { $user->update(['doctor_id' => $doctor->id]); }
        // OR create a new user:
        // User::create(['name' => $doctor->name, 'username' => ..., 'password' => ..., 'doctor_id' => $doctor->id]);

        return new DoctorResource($doctor->load(['specialist', 'user']));
    }

    public function show(Doctor $doctor)
    {
        return new DoctorResource($doctor->load(['specialist', 'user']));
    }

    public function update(Request $request, Doctor $doctor)
    {
        $validatedData = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'phone' => ['sometimes', 'required', 'string'],
            'cash_percentage' => 'sometimes|required|numeric|min:0|max:100',
            'company_percentage' => 'sometimes|required|numeric|min:0|max:100',
            'static_wage' => 'sometimes|required|numeric|min:0',
            'lab_percentage' => 'sometimes|required|numeric|min:0|max:100',
            'specialist_id' => 'sometimes|required|exists:specialists,id',
            'start' => 'sometimes|required|integer',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'calc_insurance' => 'sometimes|required|boolean',
            'is_default' => 'sometimes|boolean',
            'firebase_id' => 'sometimes|required|string',
        ]);

        if ($request->hasFile('image_file')) {
            // Delete old image if it exists
            if ($doctor->image) {
                Storage::disk('public')->delete($doctor->image);
            }
            $path = $request->file('image_file')->store('doctors_images', 'public');
            $validatedData['image'] = $path;
        }
        unset($validatedData['image_file']);

        DB::transaction(function () use (&$doctor, $validatedData) {
            $doctor->update($validatedData);
            if (! empty($validatedData['is_default'])) {
                Doctor::where('id', '!=', $doctor->id)->update(['is_default' => false]);
            }
        });

        return new DoctorResource($doctor->load(['specialist', 'user']));
    }

    public function destroy(Doctor $doctor)
    {
        // Deleting a doctor cascades at the DB level (patients.doctor_id → ON DELETE CASCADE,
        // then doctorvisits.patient_id cascades too), so block deletion when history exists.
        if ($doctor->patients()->exists() || $doctor->doctorVisits()->exists()) {
            return response()->json(['message' => 'لا يمكن حذف هذا الطبيب لارتباطه بمرضى أو زيارات.'], 403);
        }

        if ($doctor->image) {
            Storage::disk('public')->delete($doctor->image);
        }
        $doctor->delete();

        return response()->json(null, 204);
    }
}
