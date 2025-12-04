<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 
 *
 * @property int $id
 * @property bool $is_header
 * @property bool $is_footer
 * @property bool $is_logo
 * @property string|null $header_base64
 * @property string|null $footer_base64
 * @property string|null $header_content
 * @property string|null $footer_content
 * @property string|null $logo_base64
 * @property string|null $lab_name
 * @property string|null $hospital_name
 * @property string|null $firestore_result_collection
 * @property string|null $cloud_api_token
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property bool $disable_doctor_service_check
 * @property string|null $phone_number_id
 * @property string $phone
 * @property bool $gov
 * @property bool $country
 * @property bool $barcode
 * @property bool $show_water_mark
 * @property string $vatin
 * @property string $cr
 * @property string $email
 * @property string $address
 * @property bool $send_result_after_auth
 * @property bool $send_result_after_result
 * @property bool $edit_result_after_auth
 * @property string|null $auditor_stamp
 * @property string|null $manager_stamp
 * @property int|null $finance_account_id
 * @property int|null $bank_id
 * @property int|null $company_account_id
 * @property int|null $endurance_account_id
 * @property int|null $main_cash
 * @property int|null $main_bank
 * @property \Illuminate\Support\Carbon|null $financial_year_start
 * @property \Illuminate\Support\Carbon|null $financial_year_end
 * @property int|null $pharmacy_bank
 * @property int|null $pharmacy_cash
 * @property int|null $pharmacy_income
 * @property string|null $welcome_message
 * @property bool $send_welcome_message
 * @property-read \App\Models\FinanceAccount|null $defaultBank
 * @property-read \App\Models\FinanceAccount|null $defaultFinanceAccount
 * @method static \Illuminate\Database\Eloquent\Builder|Setting newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Setting newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Setting query()
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereAuditorStamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereBankId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereBarcode($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereCompanyAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereCountry($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereCr($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereDisableDoctorServiceCheck($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereEditResultAfterAuth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereEnduranceAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereFinanceAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereFinancialYearEnd($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereFinancialYearStart($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereFooterBase64($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereFooterContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereGov($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereHeaderBase64($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereHeaderContent($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereHospitalName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereInstanceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereInventoryNotificationNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereIsFooter($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereIsHeader($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereIsLogo($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereLabName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereLogoBase64($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereMainBank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereMainCash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereManagerStamp($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting wherePharmacyBank($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting wherePharmacyCash($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting wherePharmacyIncome($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting wherePhone($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereFirestoreResultCollection($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereSendResultAfterAuth($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereSendResultAfterResult($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereSendWelcomeMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereShowWaterMark($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereVatin($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Setting whereWelcomeMessage($value)
 * @mixin \Eloquent
 */
class Setting extends Model
{
    use HasFactory;

    // No need for $table if it's 'settings' (plural of Setting)
    // protected $table = 'settings'; 

    protected $fillable = [
        'is_header', 'is_footer', 'is_logo',
        'header_base64', 'footer_base64', 'logo_base64',
        'header_content', 'footer_content',
        'lab_name', 'hospital_name', 'firestore_result_collection',
        'cloud_api_token',
        'disable_doctor_service_check',
        'phone_number_id', 'phone', 'gov', 'country', // These gov/country might be booleans or IDs
        'barcode', 'show_water_mark',
        'vatin', 'cr', 'email', 'address',
        'ultramsg_instance_id', 'ultramsg_token', 'ultramsg_base_url', 'ultramsg_default_country_code', // For Ultramsg WhatsApp API
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
        'default_lab_report_template',
        'send_sms_after_auth',
        'send_whatsapp_after_auth',
        'watermark_image',
        'show_logo',
        'show_logo_only_whatsapp',
        'show_title_in_lab_result',
        'storage_name',
    ];

    protected $casts = [
        'is_header' => 'boolean',
        'is_footer' => 'boolean',
        'is_logo' => 'boolean',
        'firestore_result_collection' => 'string',
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
        'default_lab_report_template' => 'string',
        'welcome_sms' => 'boolean',
        'send_sms_after_auth' => 'boolean',
        'send_whatsapp_after_auth' => 'boolean',
        'watermark_image' => 'string',
        'show_logo' => 'boolean',
        'show_logo_only_whatsapp' => 'boolean',
        'show_title_in_lab_result' => 'boolean',
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