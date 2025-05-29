<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    // No need for $table if it's 'settings' (plural of Setting)
    // protected $table = 'settings'; 

    protected $fillable = [
        'is_header', 'is_footer', 'is_logo',
        'header_base64', 'footer_base64', 'logo_base64',
        'header_content', 'footer_content',
        'lab_name', 'hospital_name', 'print_direct',
        'inventory_notification_number',
        'disable_doctor_service_check',
        'currency', 'phone', 'gov', 'country', // These gov/country might be booleans or IDs
        'barcode', 'show_water_mark',
        'vatin', 'cr', 'email', 'address',
        'instance_id', 'token', // For external services like WhatsApp
        'send_result_after_auth', 'send_result_after_result',
        'edit_result_after_auth',
        'auditor_stamp', 'manager_stamp', // Paths or base64 for stamps
        'finance_account_id', 'bank_id', 'company_account_id', 'endurance_account_id',
        'main_cash', 'main_bank',
        'financial_year_start', 'financial_year_end',
        'pharmacy_bank', 'pharmacy_cash', 'pharmacy_income',
        'welcome_message', 'send_welcome_message',
        'report_header_company_name',
        'report_header_address_line1',
        'report_header_address_line2',
        'report_header_phone',
        'report_header_email',
        'report_header_vatin',
        'report_header_cr',
        'report_header_logo_base64', // or 'report_header_logo_path'
    ];

    protected $casts = [
        'is_header' => 'boolean',
        'is_footer' => 'boolean',
        'is_logo' => 'boolean',
        'print_direct' => 'boolean',
        'disable_doctor_service_check' => 'boolean',
        'gov' => 'boolean', // Or 'integer' if it's an ID to a govs table
        'country' => 'boolean', // Or 'integer' if it's an ID to a countries table
        'barcode' => 'boolean',
        'show_water_mark' => 'boolean',
        'send_result_after_auth' => 'boolean',
        'send_result_after_result' => 'boolean',
        'edit_result_after_auth' => 'boolean',
        'send_welcome_message' => 'boolean',
        'financial_year_start' => 'date',
        'financial_year_end' => 'date',
    ];

    // Helper to always get the first (and only) settings record
    public static function instance(): ?self
    {
        return static::first();
    }

    // You might have relationships to FinanceAccount for the various account IDs
    public function defaultFinanceAccount() { return $this->belongsTo(FinanceAccount::class, 'finance_account_id'); }
    public function defaultBank() { return $this->belongsTo(FinanceAccount::class, 'bank_id'); }
    // ... and so on for other finance_account_id fields
}