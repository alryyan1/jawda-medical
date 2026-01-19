<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class WhatsAppMessageController extends Controller
{
    /**
     * Get chat history for a specific phone number.
     *
     * @param string $phoneNumber
     * @return \Illuminate\Http\JsonResponse
     */
    public function index($phoneNumber)
    {
        // Normalize phone number if needed (remove + or spaces)
        // For now assume exact match or simple search

        $messages = WhatsAppMessage::where('to', $phoneNumber)
            ->orWhere('from', $phoneNumber)
            ->orderBy('created_at', 'asc') // Oldest first for chat history
            ->limit(50)
            ->get();

        return response()->json($messages);
    }

    /**
     * Store a new message (incoming or outgoing).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'waba_id' => 'nullable|string',
            'phone_number_id' => 'nullable|string',
            'to' => 'required|string',
            'from' => 'nullable|string',
            'type' => 'required|string',
            'body' => 'required|string',
            'status' => 'required|string', // sent, delivered, read, received
            'direction' => 'required|in:incoming,outgoing',
            'message_id' => 'nullable|string',
            'raw_payload' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if ($request->message_id) {
            $message = WhatsAppMessage::firstOrCreate(
                ['message_id' => $request->message_id],
                $request->all()
            );
        } else {
            $message = WhatsAppMessage::create($request->all());
        }

        return response()->json($message, 201);
    }
}
