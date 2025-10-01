<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HL7MessageController extends Controller
{
    /**
     * Get all HL7 messages with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
            'device' => 'string|max:50',
            'patient_id' => 'string|max:50',
            'message_type' => 'string|max:10',
            'search' => 'string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 10);
        $device = $request->get('device');
        $patientId = $request->get('patient_id');
        $messageType = $request->get('message_type');
        $search = $request->get('search');

        $query = DB::table('hl7_messages');

        // Apply filters
        if ($device) {
            $query->where('device', $device);
        }

        if ($patientId) {
            $query->where('patient_id', $patientId);
        }

        if ($messageType) {
            $query->where('message_type', $messageType);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('raw_message', 'LIKE', "%{$search}%")
                  ->orWhere('patient_id', 'LIKE', "%{$search}%")
                  ->orWhere('device', 'LIKE', "%{$search}%");
            });
        }

        // Get paginated results
        $messages = $query->orderBy('created_at', 'desc')
                         ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $messages->items(),
            'current_page' => $messages->currentPage(),
            'last_page' => $messages->lastPage(),
            'per_page' => $messages->perPage(),
            'total' => $messages->total(),
        ]);
    }

    /**
     * Get a specific HL7 message by ID
     */
    public function show($id): JsonResponse
    {
        $message = DB::table('hl7_messages')->find($id);

        if (!$message) {
            return response()->json([
                'message' => 'HL7 message not found'
            ], 404);
        }

        return response()->json($message);
    }

    /**
     * Delete a specific HL7 message
     */
    public function destroy($id): JsonResponse
    {
        $deleted = DB::table('hl7_messages')->where('id', $id)->delete();

        if (!$deleted) {
            return response()->json([
                'message' => 'HL7 message not found'
            ], 404);
        }

        return response()->json([
            'message' => 'HL7 message deleted successfully'
        ]);
    }

    /**
     * Get recent HL7 messages (last 24 hours)
     */
    public function recent(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'page' => 'integer|min:1',
            'per_page' => 'integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $page = $request->get('page', 1);
        $perPage = $request->get('per_page', 10);

        $messages = DB::table('hl7_messages')
                     ->where('created_at', '>=', now()->subDay())
                     ->orderBy('created_at', 'desc')
                     ->paginate($perPage, ['*'], 'page', $page);

        return response()->json([
            'data' => $messages->items(),
            'current_page' => $messages->currentPage(),
            'last_page' => $messages->lastPage(),
            'per_page' => $messages->perPage(),
            'total' => $messages->total(),
        ]);
    }

    /**
     * Get unique devices from HL7 messages
     */
    public function devices(): JsonResponse
    {
        $devices = DB::table('hl7_messages')
                    ->select('device')
                    ->whereNotNull('device')
                    ->distinct()
                    ->pluck('device');

        return response()->json($devices);
    }

    /**
     * Get message statistics
     */
    public function statistics(): JsonResponse
    {
        $stats = [
            'total_messages' => DB::table('hl7_messages')->count(),
            'messages_today' => DB::table('hl7_messages')
                                ->whereDate('created_at', today())
                                ->count(),
            'messages_this_week' => DB::table('hl7_messages')
                                    ->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])
                                    ->count(),
            'unique_devices' => DB::table('hl7_messages')
                                ->whereNotNull('device')
                                ->distinct('device')
                                ->count(),
            'processed_messages' => DB::table('hl7_messages')
                                   ->whereNotNull('processed_at')
                                   ->count(),
        ];

        return response()->json($stats);
    }
}
