<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Models\Setting;

class SettingUploadController extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:jpg,jpeg,png,webp,pdf|max:5120',
            'field' => 'required|string|in:header_base64,footer_base64,watermark_image',
        ]);

        $file = $request->file('file');
        $filename = Str::uuid()->toString() . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('public/settings', $filename);

        // Generate public URL (requires storage:link)
        $publicPath = Storage::url('settings/' . $filename);
        $pathWithoutSlash = ltrim($publicPath, '/');

        // Persist to settings table if exists
        $field = $request->string('field')->toString();
        /** @var Setting|null $settings */
        $settings = Setting::query()->first();
        if ($settings) {
            $settings->update([$field => $pathWithoutSlash]);
        }

        return response()->json([
            'path' => $pathWithoutSlash,
            'updated_field' => $field,
        ]);
    }
}


