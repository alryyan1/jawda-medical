<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BankakImage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class BankakImageController extends Controller
{
    /**
     * Get bankak images with optional date filtering
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = BankakImage::with('doctorvisit.patient')
                ->orderBy('created_at', 'desc');

            // Filter by date if provided
            if ($request->has('date') && $request->date) {
                $date = Carbon::parse($request->date)->format('Y-m-d');
                $query->whereDate('created_at', $date);
            }

            // Filter by date range if provided
            if ($request->has('start_date') && $request->start_date) {
                $startDate = Carbon::parse($request->start_date)->startOfDay();
                $query->where('created_at', '>=', $startDate);
            }

            if ($request->has('end_date') && $request->end_date) {
                $endDate = Carbon::parse($request->end_date)->endOfDay();
                $query->where('created_at', '<=', $endDate);
            }

            // Filter by phone if provided
            if ($request->has('phone') && $request->phone) {
                $query->where('phone', 'like', '%' . $request->phone . '%');
            }

            $perPage = $request->get('per_page', 20);
            $images = $query->paginate($perPage);

            // Transform the data to include full image URLs
            $transformedImages = $images->getCollection()->map(function ($image) {
                return [
                    'id' => $image->id,
                    'image_url' => $image->image_url,
                    'full_image_url' => asset('storage/' . $image->image_url),
                    'phone' => $image->phone,
                    'doctorvisit_id' => $image->doctorvisit_id,
                    'patient_name' => $image->doctorvisit?->patient?->name ?? 'غير محدد',
                    'created_at' => $image->created_at->format('Y-m-d H:i:s'),
                    'created_at_human' => $image->created_at->diffForHumans(),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedImages,
                'pagination' => [
                    'current_page' => $images->currentPage(),
                    'last_page' => $images->lastPage(),
                    'per_page' => $images->perPage(),
                    'total' => $images->total(),
                    'has_more_pages' => $images->hasMorePages(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الصور',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available dates for filtering
     *
     * @return JsonResponse
     */
    public function getAvailableDates(): JsonResponse
    {
        try {
            $dates = BankakImage::selectRaw('DATE(created_at) as date')
                ->distinct()
                ->orderBy('date', 'desc')
                ->limit(30)
                ->pluck('date');

            return response()->json([
                'success' => true,
                'data' => $dates,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب التواريخ',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get image statistics
     *
     * @return JsonResponse
     */
    public function getStats(): JsonResponse
    {
        try {
            $totalImages = BankakImage::count();
            $todayImages = BankakImage::whereDate('created_at', today())->count();
            $thisWeekImages = BankakImage::whereBetween('created_at', [
                now()->startOfWeek(),
                now()->endOfWeek()
            ])->count();
            $thisMonthImages = BankakImage::whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_images' => $totalImages,
                    'today_images' => $todayImages,
                    'this_week_images' => $thisWeekImages,
                    'this_month_images' => $thisMonthImages,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'حدث خطأ في جلب الإحصائيات',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}