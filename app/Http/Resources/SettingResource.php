<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if (!$this->resource) {
            return []; // Return empty array if no settings record found
        }
        return [
            'id' => $this->id, // Though likely always 1
            'is_header' => (bool) $this->is_header,
            'is_footer' => (bool) $this->is_footer,
            'is_logo' => (bool) $this->is_logo,
            'header_base64' => $this->header_base64,
            'footer_base64' => $this->footer_base64,
            'logo_base64' => $this->logo_base64,
            'header_content' => $this->header_content,
            'footer_content' => $this->footer_content,
            'lab_name' => $this->lab_name,
            'hospital_name' => $this->hospital_name,
            'firestore_result_collection' => $this->firestore_result_collection,
            'inventory_notification_number' => $this->inventory_notification_number,
            'disable_doctor_service_check' => (bool) $this->disable_doctor_service_check,
            'currency' => $this->currency,
            'phone' => $this->phone,
            'gov' => $this->gov, // bool or int
            'country' => $this->country, // bool or int
            'barcode' => (bool) $this->barcode,
            'show_water_mark' => (bool) $this->show_water_mark,
            'vatin' => $this->vatin,
            'cr' => $this->cr,
            'email' => $this->email,
            'address' => $this->address,
            'ultramsg_instance_id' => $this->ultramsg_instance_id,
            'ultramsg_token' => $this->ultramsg_token, // Be careful about exposing sensitive tokens directly
            'ultramsg_base_url' => $this->ultramsg_base_url,
            'ultramsg_default_country_code' => $this->ultramsg_default_country_code,
            'send_result_after_auth' => (bool) $this->send_result_after_auth,
            'send_result_after_result' => (bool) $this->send_result_after_result,
            'edit_result_after_auth' => (bool) $this->edit_result_after_auth,
            'auditor_stamp' => $this->auditor_stamp,
            'manager_stamp' => $this->manager_stamp,
            'finance_account_id' => $this->finance_account_id,
            'bank_id' => $this->bank_id,
            'company_account_id' => $this->company_account_id,
            'endurance_account_id' => $this->endurance_account_id,
            'main_cash' => $this->main_cash,
            'main_bank' => $this->main_bank,
            'financial_year_start' => $this->financial_year_start?->toDateString(),
            'financial_year_end' => $this->financial_year_end?->toDateString(),
            'pharmacy_bank' => $this->pharmacy_bank,
            'pharmacy_cash' => $this->pharmacy_cash,
            'pharmacy_income' => $this->pharmacy_income,
            'welcome_message' => $this->welcome_message,
            'send_welcome_message' => (bool) $this->send_welcome_message,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'report_header_company_name' => $this->report_header_company_name,
            'report_header_address_line1' => $this->report_header_address_line1,
            'report_header_address_line2' => $this->report_header_address_line2,
            'report_header_phone' => $this->report_header_phone,
            'report_header_email' => $this->report_header_email,
            'report_header_vatin' => $this->report_header_vatin,
            'report_header_cr' => $this->report_header_cr,
            'send_sms_after_auth' => (bool) $this->send_sms_after_auth,
            'send_whatsapp_after_auth' => (bool) $this->send_whatsapp_after_auth,
            'watermark_image' => $this->watermark_image,
            'show_logo' => (bool) $this->show_logo,
            'show_logo_only_whatsapp' => (bool) $this->show_logo_only_whatsapp,
            'show_title_in_lab_result' => (bool) $this->show_title_in_lab_result,
            'storage_name' => $this->storage_name,
            

            // Eager load related finance accounts if needed for display
            // 'default_finance_account_details' => new FinanceAccountResource($this->whenLoaded('defaultFinanceAccount')),
        ];
    }
}