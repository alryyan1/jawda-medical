<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserDocSelection;
use App\Models\Doctor;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class UserDocSelectionController extends Controller
{
    /**
     * Get all favorite doctors for the authenticated user
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json(['message' => 'غير مصرح بالوصول'], 401);
            }

            $favorites = UserDocSelection::where('user_id', $userId)
                ->where('active', true)
                ->with(['doctor' => function ($query) {
                    $query->select('doctors.id', 'doctors.name', 'specialists.name as specialist_name')
                        ->leftJoin('specialists', 'doctors.specialist_id', '=', 'specialists.id');
                }])
                ->get();

            return response()->json([
                'success' => true,
                'data' => $favorites->pluck('doctor')->filter()->values()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الأطباء المفضلين',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all doctors with their favorite status for the authenticated user
     */
    public function getDoctorsWithFavorites(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json(['message' => 'غير مصرح بالوصول'], 401);
            }

            $search = $request->get('search', '');

            $doctorsQuery = Doctor::select('doctors.id', 'doctors.name', 'specialists.name as specialist_name')
                ->leftJoin('specialists', 'doctors.specialist_id', '=', 'specialists.id');

            if ($search) {
                $doctorsQuery->where(function ($query) use ($search) {
                    $query->where('doctors.name', 'like', "%{$search}%")
                        ->orWhere('specialists.name', 'like', "%{$search}%");
                });
            }

            $doctors = $doctorsQuery->get();

            // Get favorite doctor data for this user
            $favoriteData = UserDocSelection::where('user_id', $userId)
                ->where('active', true)
                ->get(['doc_id', 'fav_service'])
                ->keyBy('doc_id');

            // Add favorite status and service to each doctor
            $doctorsWithFavorites = $doctors->map(function ($doctor) use ($favoriteData) {
                $favoriteInfo = $favoriteData->get($doctor->id);
                $doctor->is_favorite = $favoriteInfo ? true : false;
                $doctor->fav_service_id = $favoriteInfo ? $favoriteInfo->fav_service : null;
                return $doctor;
            });

            return response()->json([
                'success' => true,
                'data' => $doctorsWithFavorites
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في جلب الأطباء',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add a doctor to favorites
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json(['message' => 'غير مصرح بالوصول'], 401);
            }

            $validator = Validator::make($request->all(), [
                'doc_id' => 'required|integer|exists:doctors,id',
                'fav_service' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات غير صحيحة',
                    'errors' => $validator->errors()
                ], 422);
            }

            $docId = $request->doc_id;

            // Check if already exists
            $existing = UserDocSelection::where('user_id', $userId)
                ->where('doc_id', $docId)
                ->first();

            if ($existing) {
                // Update existing record to active using where clause
                $updated = UserDocSelection::where('user_id', $userId)
                    ->where('doc_id', $docId)
                    ->update([
                        'active' => true,
                        'fav_service' => $request->fav_service
                    ]);

                if (!$updated) {
                    return response()->json([
                        'success' => false,
                        'message' => 'فشل في تحديث المفضلة'
                    ], 500);
                }
            } else {
                // Create new record
                UserDocSelection::create([
                    'user_id' => $userId,
                    'doc_id' => $docId,
                    'active' => true,
                    'fav_service' => $request->fav_service
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'تم إضافة الطبيب للمفضلة بنجاح'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في إضافة الطبيب للمفضلة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove a doctor from favorites
     */
    public function destroy(Request $request, $docId): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json(['message' => 'غير مصرح بالوصول'], 401);
            }

            // Check if the selection exists
            $exists = UserDocSelection::where('user_id', $userId)
                ->where('doc_id', $docId)
                ->exists();

            if (!$exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'الطبيب غير موجود في المفضلة'
                ], 404);
            }

            // Update directly using where clause (since we have composite primary key)
            $updated = UserDocSelection::where('user_id', $userId)
                ->where('doc_id', $docId)
                ->update(['active' => false]);

            if ($updated) {
                return response()->json([
                    'success' => true,
                    'message' => 'تم إزالة الطبيب من المفضلة بنجاح'
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'فشل في إزالة الطبيب من المفضلة'
                ], 500);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في إزالة الطبيب من المفضلة',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle favorite status for a doctor
     */
    public function toggle(Request $request): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json(['message' => 'غير مصرح بالوصول'], 401);
            }

            $validator = Validator::make($request->all(), [
                'doc_id' => 'required|integer|exists:doctors,id',
                'fav_service' => 'nullable|integer'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'بيانات غير صحيحة',
                    'errors' => $validator->errors()
                ], 422);
            }

            $docId = $request->doc_id;

            // Debug logging
            \Log::info('Toggle favorite request', [
                'user_id' => $userId,
                'doc_id' => $docId,
                'fav_service' => $request->fav_service
            ]);

            // Check if the selection exists and get current status
            $selection = UserDocSelection::where('user_id', $userId)
                ->where('doc_id', $docId)
                ->first();

            if ($selection) {
                // Toggle active status
                $newStatus = !$selection->active;

                // Update using where clause (since we have composite primary key)
                \Log::info('Updating selection', [
                    'user_id' => $userId,
                    'doc_id' => $docId,
                    'new_status' => $newStatus
                ]);

                $updated = UserDocSelection::where('user_id', $userId)
                    ->where('doc_id', $docId)
                    ->update([
                        'active' => $newStatus,
                        'fav_service' => $request->fav_service
                    ]);

                \Log::info('Update result', ['updated_rows' => $updated]);

                if ($updated) {
                    $message = $newStatus ? 'تم إضافة الطبيب للمفضلة بنجاح' : 'تم إزالة الطبيب من المفضلة بنجاح';
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'فشل في تحديث المفضلة'
                    ], 500);
                }
            } else {
                // Create new record as active
                UserDocSelection::create([
                    'user_id' => $userId,
                    'doc_id' => $docId,
                    'active' => true,
                    'fav_service' => $request->fav_service
                ]);
                $message = 'تم إضافة الطبيب للمفضلة بنجاح';
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'خطأ في تحديث المفضلة',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
