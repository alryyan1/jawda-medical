<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class HL7MessageInsertController extends Controller
{
    /**
     * Insert a new HL7 message into the database
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'raw_message' => 'required|string',
            'device' => 'nullable|string|max:50',
            'message_type' => 'nullable|string|max:10',
            'patient_id' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $rawMessage = $request->input('raw_message');
            $device = $request->input('device');
            $messageType = $request->input('message_type');
            $patientId = $request->input('patient_id');

            // If device, message_type, or patient_id are not provided, try to parse from message
            if (!$device || !$messageType) {
                $parsed = $this->parseHL7Message($rawMessage);
                $device = $device ?: $parsed['device'];
                $messageType = $messageType ?: $parsed['message_type'];
                $patientId = $patientId ?: $parsed['patient_id'];
            }

            $messageId = DB::table('hl7_messages')->insertGetId([
                'raw_message' => $rawMessage,
                'device' => $device,
                'message_type' => $messageType,
                'patient_id' => $patientId,
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'HL7 message inserted successfully',
                'id' => $messageId,
                'data' => [
                    'device' => $device,
                    'message_type' => $messageType,
                    'patient_id' => $patientId,
                    'created_at' => now()->toISOString(),
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to insert HL7 message',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Insert multiple HL7 messages
     */
    public function storeBatch(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'messages' => 'required|array|min:1',
            'messages.*.raw_message' => 'required|string',
            'messages.*.device' => 'nullable|string|max:50',
            'messages.*.message_type' => 'nullable|string|max:10',
            'messages.*.patient_id' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $messages = $request->input('messages');
            $insertedIds = [];
            $errors = [];

            foreach ($messages as $index => $messageData) {
                try {
                    $rawMessage = $messageData['raw_message'];
                    $device = $messageData['device'] ?? null;
                    $messageType = $messageData['message_type'] ?? null;
                    $patientId = $messageData['patient_id'] ?? null;

                    // Parse message if needed
                    if (!$device || !$messageType) {
                        $parsed = $this->parseHL7Message($rawMessage);
                        $device = $device ?: $parsed['device'];
                        $messageType = $messageType ?: $parsed['message_type'];
                        $patientId = $patientId ?: $parsed['patient_id'];
                    }

                    $messageId = DB::table('hl7_messages')->insertGetId([
                        'raw_message' => $rawMessage,
                        'device' => $device,
                        'message_type' => $messageType,
                        'patient_id' => $patientId,
                        'processed_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $insertedIds[] = $messageId;

                } catch (\Exception $e) {
                    $errors[] = [
                        'index' => $index,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'message' => 'Batch insert completed',
                'inserted_count' => count($insertedIds),
                'error_count' => count($errors),
                'inserted_ids' => $insertedIds,
                'errors' => $errors
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to insert HL7 messages',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parse HL7 message to extract metadata
     */
    private function parseHL7Message(string $rawMessage): array
    {
        $lines = explode("\n", trim($rawMessage));
        $mshLine = $lines[0];
        $fields = explode('|', $mshLine);
        
        // Extract message type from field 8
        $messageType = isset($fields[8]) ? $fields[8] : null;
        
        // Extract sending facility from field 3 (use as device)
        $device = isset($fields[3]) ? $fields[3] : null;
        
        // Extract patient ID from PID segment if present
        $patientId = null;
        foreach ($lines as $line) {
            if (strpos($line, 'PID|') === 0) {
                $pidFields = explode('|', $line);
                $patientId = isset($pidFields[3]) ? $pidFields[3] : null;
                break;
            }
        }
        
        return [
            'device' => $device,
            'message_type' => $messageType,
            'patient_id' => $patientId,
        ];
    }
}
