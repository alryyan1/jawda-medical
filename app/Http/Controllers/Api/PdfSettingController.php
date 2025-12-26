<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PdfSetting;
use App\Http\Resources\PdfSettingResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class PdfSettingController extends Controller
{
    /**
     * Get current PDF settings (singleton pattern).
     */
    public function index()
    {
        $settings = PdfSetting::getSettings();
        return new PdfSettingResource($settings);
    }

    /**
     * Update PDF settings.
     */
    public function update(Request $request)
    {
        $validatedData = $request->validate([
            'font_family' => 'sometimes|string|max:255',
            'font_size' => 'sometimes|integer|min:6|max:72',
            'logo_width' => 'nullable|numeric|min:0|max:200',
            'logo_height' => 'nullable|numeric|min:0|max:200',
            'logo_position' => 'nullable|in:left,right',
            'hospital_name' => 'nullable|string|max:255',
            'footer_phone' => 'nullable|string|max:255',
            'footer_address' => 'nullable|string',
            'footer_email' => 'nullable|email|max:255',
        ]);

        $settings = PdfSetting::getSettings();
        $settings->update($validatedData);

        return new PdfSettingResource($settings->fresh());
    }

    /**
     * Upload logo image.
     */
    public function uploadLogo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'logo' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        $settings = PdfSetting::getSettings();

        // Delete old logo if exists
        if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        // Store new logo
        $logoPath = $request->file('logo')->store('pdf-assets', 'public');
        $settings->logo_path = $logoPath;
        $settings->save();

        return new PdfSettingResource($settings->fresh());
    }

    /**
     * Upload header image.
     */
    public function uploadHeader(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'header' => 'required|image|mimes:jpeg,jpg,png,webp|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'خطأ في التحقق من البيانات',
                'errors' => $validator->errors(),
            ], 422);
        }

        $settings = PdfSetting::getSettings();

        // Delete old header if exists
        if ($settings->header_image_path && Storage::disk('public')->exists($settings->header_image_path)) {
            Storage::disk('public')->delete($settings->header_image_path);
        }

        // Store new header
        $headerPath = $request->file('header')->store('pdf-assets', 'public');
        $settings->header_image_path = $headerPath;
        $settings->save();

        return new PdfSettingResource($settings->fresh());
    }

    /**
     * Delete logo.
     */
    public function deleteLogo()
    {
        $settings = PdfSetting::getSettings();

        if ($settings->logo_path && Storage::disk('public')->exists($settings->logo_path)) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $settings->logo_path = null;
        $settings->logo_width = null;
        $settings->logo_height = null;
        $settings->save();

        return new PdfSettingResource($settings->fresh());
    }

    /**
     * Delete header image.
     */
    public function deleteHeader()
    {
        $settings = PdfSetting::getSettings();

        if ($settings->header_image_path && Storage::disk('public')->exists($settings->header_image_path)) {
            Storage::disk('public')->delete($settings->header_image_path);
        }

        $settings->header_image_path = null;
        $settings->save();

        return new PdfSettingResource($settings->fresh());
    }
}
