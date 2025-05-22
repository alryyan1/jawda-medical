<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

// app/Http/Controllers/Api/ReportController.php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceGroup; // For filter
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB; // For aggregate functions
use Carbon\Carbon;
// You might create a specific Resource for this report item if needed
use App\Http\Resources\ServiceResource; // Can be adapted or a new one created

class ReportController extends Controller
{
    // ... (other report methods) ...

    public function serviceStatistics(Request $request)
    {
        // Permission check: e.g., can('view service_statistics_report')
        // if (!auth()->user()->can('view service_statistics_report')) {
        //     return response()->json(['message' => 'Unauthorized'], 403);
        // }

        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'service_group_id' => 'nullable|integer|exists:service_groups,id',
            'search_service_name' => 'nullable|string|max:255',
            'sort_by' => 'nullable|string|in:name,request_count',
            'sort_direction' => 'nullable|string|in:asc,desc',
            'per_page' => 'nullable|integer|min:5|max:100',
        ]);

        $query = Service::query()->with('serviceGroup:id,name') // Eager load service group for display
                        ->select([
                            'services.id', 
                            'services.name', 
                            'services.price', // Include price for context
                            'services.service_group_id',
                            'services.activate',
                            // Count requested_services entries
                            DB::raw('COUNT(requested_services.id) as request_count'),
                            // Optionally, sum total revenue from this service
                            // DB::raw('SUM(requested_services.price * requested_services.count) as total_revenue')
                         ])
                        ->leftJoin('requested_services', 'services.id', '=', 'requested_services.service_id');
                        
        // Date range filter for requested_services
        if ($request->filled('date_from')) {
            $dateFrom = Carbon::parse($request->date_from)->startOfDay();
            // Apply date filter on the join condition or a subquery for accuracy
            // For simplicity, applying on requested_services.created_at directly in WHERE
            // This means services with no requests in the date range might still appear with count 0
            // If you only want services requested in the date range, the join condition is better.
            $query->where(function($q) use ($dateFrom) {
                $q->where('requested_services.created_at', '>=', $dateFrom)
                  ->orWhereNull('requested_services.created_at'); // Include services with no requests at all
            });
        }
        if ($request->filled('date_to')) {
            $dateTo = Carbon::parse($request->date_to)->endOfDay();
             $query->where(function($q) use ($dateTo) {
                $q->where('requested_services.created_at', '<=', $dateTo)
                  ->orWhereNull('requested_services.created_at');
            });
        }
        
        // Filter by service group
        if ($request->filled('service_group_id')) {
            $query->where('services.service_group_id', $request->service_group_id);
        }

        // Filter by service name (search)
        if ($request->filled('search_service_name')) {
            $query->where('services.name', 'LIKE', '%' . $request->search_service_name . '%');
        }

        $query->groupBy('services.id', 'services.name', 'services.price', 'services.service_group_id', 'services.activate'); // Must group by all selected non-aggregated columns

        // Sorting
        $sortBy = $request->input('sort_by', 'request_count'); // Default sort by request_count
        $sortDirection = $request->input('sort_direction', 'desc');
        if ($sortBy === 'name') {
            $query->orderBy('services.name', $sortDirection);
        } else { // Default to request_count or if explicitly chosen
            $query->orderBy('request_count', $sortDirection);
        }
        $query->orderBy('services.name', 'asc'); // Secondary sort by name


        $perPage = $request->input('per_page', 15);
        $statistics = $query->paginate($perPage);

        // The 'request_count' (and 'total_revenue' if added) will be available as attributes on each service model in the collection
        // We can use a simple collection resource or adapt ServiceResource if needed.
        // Using a custom transformation here for clarity.
        return response()->json([
            'data' => $statistics->through(function ($service) {
                return [
                    'id' => $service->id,
                    'name' => $service->name,
                    'price' => (float) $service->price,
                    'activate' => (bool) $service->activate,
                    'service_group_id' => $service->service_group_id,
                    'service_group_name' => $service->serviceGroup?->name, // From with('serviceGroup')
                    'request_count' => (int) $service->request_count,
                    // 'total_revenue' => (float) ($service->total_revenue ?? 0),
                ];
            }),
            'links' => [
                'first' => $statistics->url(1),
                'last' => $statistics->url($statistics->lastPage()),
                'prev' => $statistics->previousPageUrl(),
                'next' => $statistics->nextPageUrl(),
            ],
            'meta' => [
                'current_page' => $statistics->currentPage(),
                'from' => $statistics->firstItem(),
                'last_page' => $statistics->lastPage(),
                'path' => $statistics->path(),
                'per_page' => $statistics->perPage(),
                'to' => $statistics->lastItem(),
                'total' => $statistics->total(),
            ],
        ]);
    }
}