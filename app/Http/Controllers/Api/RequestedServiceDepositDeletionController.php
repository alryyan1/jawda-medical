<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RequestedServiceDepositDeletionResource;
use App\Models\RequestedServiceDepositDeletion;
use Illuminate\Http\Request;

class RequestedServiceDepositDeletionController extends Controller
{
    /**
     * Display a listing of deleted/voided deposits.
     *
     * For now this returns a simple paginated list of the most recent deletions.
     * You can add filters later (by date, user, requested service, etc.).
     */
    public function index(Request $request)
    {
        $perPage = (int) ($request->get('per_page', 50));
        $serviceName = $request->get('service_name');
        $fromDate = $request->get('from_date');
        $toDate = $request->get('to_date');

        $query = RequestedServiceDepositDeletion::query()
            ->with(['user:id,name', 'deletedByUser:id,name', 'requestedService.service'])
            ->orderByDesc('deleted_at')
            ->orderByDesc('id');

        // Filter by service name (via related requestedService -> service)
        if ($serviceName) {
            $query->whereHas('requestedService.service', function ($q) use ($serviceName) {
                $q->where('name', 'like', '%' . $serviceName . '%');
            });
        }

        // Filter by deletion date range
        if ($fromDate) {
            $query->whereDate('deleted_at', '>=', $fromDate);
        }
        if ($toDate) {
            $query->whereDate('deleted_at', '<=', $toDate);
        }

        $paginator = $query->paginate($perPage);

        return RequestedServiceDepositDeletionResource::collection($paginator);
    }
}


