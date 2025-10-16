<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ImageProxyController extends Controller
{
    public function fetchBase64(Request $request)
    {
        $url = $request->query('url');

        if (!$url || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or missing url parameter',
            ], 422);
        }

        try {
            $response = Http::timeout(15)->withHeaders([
                'User-Agent' => 'JawdaImageProxy/1.0'
            ])->get($url);

            if (!$response->ok()) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to fetch the image',
                    'status' => $response->status(),
                ], 502);
            }

            $binary = $response->body();
            $contentType = $response->header('Content-Type');

            // Fallback to guessing mime if header missing
            if (empty($contentType)) {
                $extension = Str::of(parse_url($url, PHP_URL_PATH))->afterLast('.')->lower();
                $map = [
                    'jpg' => 'image/jpeg',
                    'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'gif' => 'image/gif',
                    'webp' => 'image/webp',
                ];
                $contentType = $map[(string) $extension] ?? 'application/octet-stream';
            }

            return response()->json([
                'success' => true,
                'mime' => $contentType,
                'base64' => base64_encode($binary),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'error' => 'Unexpected error fetching image',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}


