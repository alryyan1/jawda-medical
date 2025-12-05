<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('settings')->insert([
            'is_header' => null,
            'is_footer' => null,
            'is_logo' => null,
            'show_logo' => 0,
            'show_logo_only_whatsapp' => 0,
            'header_base64' => null,
            'footer_base64' => null,
            'header_content' => null,
            'footer_content' => null,
            'logo_base64' => null,
            'lab_name' => null,
            'firestore_result_collection' => null,
            'hospital_name' => null,
            'cloud_api_token' => null,
            'disable_doctor_service_check' => null,
            'phone_number_id' => null,
            'phone' => null,
            'gov' => null,
            'country' => null,
            'barcode' => null,
            'show_water_mark' => null,
            'vatin' => null,
            'cr' => null,
            'email' => null,
            'address' => null,
            'ultramsg_instance_id' => null,
            'ultramsg_token' => null,
            'ultramsg_base_url' => null,
            'ultramsg_default_country_code' => null,
            'send_result_after_auth' => null,
            'send_result_after_result' => null,
            'edit_result_after_auth' => null,
            'auditor_stamp' => null,
            'manager_stamp' => null,
            'finance_account_id' => null,
            'bank_id' => null,
            'company_account_id' => null,
            'endurance_account_id' => null,
            'main_cash' => null,
            'main_bank' => null,
            'financial_year_start' => null,
            'financial_year_end' => null,
            'pharmacy_bank' => null,
            'pharmacy_cash' => null,
            'pharmacy_income' => null,
            'welcome_message' => '? مرحباً بكم في مستشفى الرومي للأسنان! ✨\n\n? يسعدنا اختياركم لنا للعناية بصحة أسنانكم.\n\n?‍⚕️?‍⚕️ فريقنا المتخصص ملتزم بتقديم خدمات استثنائية في بيئة مريحة.\n\n? ابتسامتكم هي أولويتنا!\n\n? للاستفسارات، يرجى التواصل معنا وسنكون سعداء بالرد على استفساراتكم.\n\n? شكراً لثقتكم بنا.',
            'send_welcome_message' => null,
            'default_lab_report_template' => null,
            'watermark_image' => null,
            'send_sms_after_auth' => 0,
            'send_whatsapp_after_auth' => 0,
            'show_title_in_lab_result' => null,
            'storage_name' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
