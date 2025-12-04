<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\FinanceAccount; // For validating exists
use Illuminate\Http\Request;
use App\Http\Resources\SettingResource;
use Illuminate\Support\Facades\Storage; // For handling file uploads (stamps, logo)

class SettingsController extends Controller
{
    public function __construct()
    {
        // Apply middleware for permissions
        // $this->middleware('can:view settings')->only('show');
        // $this->middleware('can:update settings')->only('update');
    }

    /**
     * Display the application settings.
     * There should only be one settings record.
     */
    public function show()
    {
        // Eager load any finance accounts needed for display by name in the form
        $settings = Setting::with([
             // 'defaultFinanceAccount', 'defaultBank', /* ... other finance account relations */
        ])->first();

        if (!$settings) {
            // Optionally, create a default settings record if none exists
            $settings = Setting::create([
                'is_header' => false,
                'is_footer' => false,
                'is_logo' => false,
                'header_content' => '',
                'footer_content' => '',
                'lab_name' => 'المختبر',
                'hospital_name' => 'المستشفى',
                'cloud_api_token' => null,
                'phone_number_id' => null,
                'disable_doctor_service_check' => false,
                'phone' => '',
                'gov' => false,
                'country' => false,
                'barcode' => true,
                'show_water_mark' => false,
                'vatin' => '',
                'cr' => '',
                'email' => '',
                'address' => '',
                'instance_id' => '',
                'token' => '',
                'send_result_after_auth' => false,
                'send_result_after_result' => false,
                'edit_result_after_auth' => false,
                'send_welcome_message' => false,
                'welcome_message' => 'مرحباً بكم',
                'financial_year_start' => now()->startOfYear()->format('Y-m-d'),
                'financial_year_end' => now()->endOfYear()->format('Y-m-d'),
            ]);
            
            // For now, return empty or an error if setup is expected
            return response()->json(['message' => 'الإعدادات غير مهيأة بعد.'], 404);
        }
        return new SettingResource($settings);
    }

    /**
     * Update the application settings.
     */
    public function update(Request $request)
    {
        $settings = Setting::first();
        if (!$settings) {
            // Or create if it doesn't exist: $settings = new Setting;
            return response()->json(['message' => 'الإعدادات غير مهيأة.'], 404);
        }

        // Extensive validation based on your settings fields
        $validatedData = $request->validate([
            'is_header' => 'sometimes|boolean',
            'is_footer' => 'sometimes|boolean',
            'is_logo' => 'sometimes|boolean',
            // Base64 fields are large, consider separate endpoints or direct file uploads
            // For base64: 'header_base64' => 'nullable|string',
            // For file uploads:
            'logo_file' => 'nullable|image|mimes:png,jpg,jpeg,svg|max:1024', // 1MB max
            'header_image_file' => 'nullable|image|mimes:png,jpg,jpeg|max:1024', // For header image if not base64
            'footer_image_file' => 'nullable|image|mimes:png,jpg,jpeg|max:1024', // For footer image if not base64
            'auditor_stamp_file' => 'nullable|image|mimes:png|max:512',
            'manager_stamp_file' => 'nullable|image|mimes:png|max:512',
            'default_lab_report_template' => 'sometimes|string|in:template_a,template_b,template_c', // Allowed template keys

            'header_content' => 'nullable|string|max:255',
            'footer_content' => 'nullable|string|max:255',
            'lab_name' => 'nullable|string|max:255',
            'hospital_name' => 'nullable|string|max:255',
            'cloud_api_token' => 'nullable|string',
            'phone_number_id' => 'nullable|string|max:50',
            'disable_doctor_service_check' => 'sometimes|boolean',
            'phone' => 'sometimes|max:20|nullable',
            'gov' => 'sometimes|boolean', // Or 'nullable|exists:govs,id'
            'country' => 'sometimes|boolean', // Or 'nullable|exists:countries,id'
            'barcode' => 'sometimes|boolean',
            'show_water_mark' => 'sometimes|boolean',
            'vatin' => 'nullable|string|max:50',
            'cr' => 'nullable|string|max:50',
            'email' => 'sometimes|max:255',
            'address' => 'nullable|string|max:500',
            'instance_id' => 'nullable|string|max:255',
            'token' => 'nullable|string|max:500', // Store sensitive tokens encrypted
            'send_result_after_auth' => 'sometimes|boolean',
            'send_result_after_result' => 'sometimes|boolean',
            'edit_result_after_auth' => 'sometimes|boolean',
            
            'finance_account_id' => 'nullable|exists:finance_accounts,id',
            'bank_id' => 'nullable|exists:finance_accounts,id',
            'company_account_id' => 'nullable|exists:finance_accounts,id',
            'endurance_account_id' => 'nullable|exists:finance_accounts,id',
            'main_cash' => 'nullable|exists:finance_accounts,id',
            'main_bank' => 'nullable|exists:finance_accounts,id',
            'financial_year_start' => 'nullable|date_format:Y-m-d',
            'financial_year_end' => 'nullable|date_format:Y-m-d|after_or_equal:financial_year_start',
            'pharmacy_bank' => 'nullable|exists:finance_accounts,id',
            'pharmacy_cash' => 'nullable|exists:finance_accounts,id',
            'pharmacy_income' => 'nullable|exists:finance_accounts,id',
            'welcome_message' => 'nullable|string|max:2000',
            'send_welcome_message' => 'sometimes|boolean',
            'report_header_company_name' => 'nullable|string|max:255',
            'report_header_address_line1' => 'nullable|string|max:255',
            'report_header_address_line2' => 'nullable|string|max:255',
            'report_header_phone' => 'nullable|string|max:50',
            'report_header_email' => 'nullable|email|max:255',
            'report_header_vatin' => 'nullable|string|max:50',
            'report_header_cr' => 'nullable|string|max:50',
            'report_header_logo_file' => 'nullable|image|mimes:png,jpg,jpeg|max:1024', // For upload
            'send_sms_after_auth' => 'sometimes|boolean',
            'send_whatsapp_after_auth' => 'sometimes|boolean',
            'watermark_image' => 'nullable|string',
            'show_logo' => 'sometimes|boolean',
            'show_logo_only_whatsapp' => 'sometimes|boolean',
        ]);
        
        $updateData = $request->except(['logo_file', 'header_image_file', 'footer_image_file', 'auditor_stamp_file', 'manager_stamp_file',  'report_header_logo_file']);

        // Handle file uploads (example for logo)
        $fileFields = [
            'logo_file' => 'logo_base64', // request field name => db column name (if storing path, change db column name)
            // 'header_image_file' => 'header_image_path',
            // 'footer_image_file' => 'footer_image_path',
            'auditor_stamp_file' => 'auditor_stamp',
            'manager_stamp_file' => 'manager_stamp',
            'watermark_image' => 'watermark_image',
        ];

        foreach($fileFields as $requestField => $dbField) {
            if ($request->hasFile($requestField)) {
                // Delete old file if storing paths and one exists
                if ($settings->$dbField && !str_starts_with($settings->$dbField, 'data:image')) { // Basic check if it's not base64 already
                    Storage::disk('public')->delete($settings->$dbField);
                }
                // Store new file and get path
                // $path = $request->file($requestField)->store('settings_uploads', 'public');
                // $updateData[$dbField] = $path; 
                
                // OR if storing as base64 (as per your schema for logo/header/footer)
                $updateData[$dbField] = 'data:' . $request->file($requestField)->getMimeType() . ';base64,' . base64_encode(file_get_contents($request->file($requestField)->getRealPath()));
            } elseif ($request->input("clear_".$dbField)) { // Add input like clear_logo_base64
                if ($settings->$dbField && !str_starts_with($settings->$dbField, 'data:image')) {
                    Storage::disk('public')->delete($settings->$dbField);
                }
                $updateData[$dbField] = null;
            }
        }

  // Handle Report Header Logo Upload (if storing as base64)
  if ($request->hasFile('report_header_logo_file')) {
    $updateData['report_header_logo_base64'] = 'data:' . 
        $request->file('report_header_logo_file')->getMimeType() . ';base64,' . 
        base64_encode(file_get_contents($request->file('report_header_logo_file')->getRealPath()));
} elseif ($request->input("clear_report_header_logo_base64")) { // Check for clear flag
    $updateData['report_header_logo_base64'] = null;
    // If storing path and need to delete old file:
    // if ($settings->report_header_logo_path) {
    //     Storage::disk('public')->delete($settings->report_header_logo_path);
    // }
}

        // If token needs to be encrypted:
        // if ($request->filled('token')) {
        //     $updateData['token'] = encrypt($validatedData['token']);
        // }

        $settings->fill($updateData)->save();

        return new SettingResource($settings->refresh());
    }
}