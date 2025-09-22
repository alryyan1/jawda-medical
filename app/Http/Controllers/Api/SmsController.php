<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Contracts\SmsClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SmsController extends Controller
{
    public function send(Request $request, SmsClient $sms)
    {
        $validator = Validator::make($request->all(), [
            'sender' => ['sometimes', 'string', 'max:20'],
            'messages' => ['required', 'array', 'min:1'],
            'messages.*.to' => ['required', 'string'],
            'messages.*.message' => ['required', 'string', 'max:1000'],
            'messages.*.is_otp' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $sender = $data['sender'] ?? null;
        $messages = $data['messages'];

        if (count($messages) === 1) {
            $m = $messages[0];
            $result = $sms->send($m['to'], $m['message'], (bool)($m['is_otp'] ?? false), $sender);
            return response()->json($result);
        }

        $result = $sms->sendBulk($messages, $sender);
        return response()->json($result);
    }
}


