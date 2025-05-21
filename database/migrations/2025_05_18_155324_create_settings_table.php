<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id(); // Even if it's a single-row table, an ID is standard.

            $table->boolean('is_header')->default(false);
            $table->boolean('is_footer')->default(false);
            $table->boolean('is_logo')->default(false);
            $table->longText('header_base64')->nullable();
            $table->longText('footer_base64')->nullable();
            $table->string('header_content')->nullable();
            $table->string('footer_content')->nullable();
            $table->longText('logo_base64')->nullable();
            $table->string('lab_name')->nullable();
            $table->string('hospital_name')->nullable();
            $table->boolean('print_direct')->nullable();
            $table->string('inventory_notification_number')->nullable(); // Assuming this is a phone number or count

            $table->boolean('disable_doctor_service_check')->default(true);
            $table->string('currency')->default('USD'); // Set a sensible default
            $table->string('phone')->default('');
            $table->boolean('gov')->default(false); // Related to government/nationality?
            $table->boolean('country')->default(false); // Related to country display?
            $table->boolean('barcode')->default(false);
            $table->boolean('show_water_mark')->default(false);
            $table->string('vatin')->nullable()->default(''); // VAT Identification Number
            $table->string('cr')->default(''); // Commercial Registration number
            $table->string('email')->default('');
            $table->string('address')->default('');
            $table->string('instance_id')->default(''); // For WhatsApp or other services
            $table->string('token')->default(''); // API token for WhatsApp or other services
            $table->boolean('send_result_after_auth')->default(false); // Defaulted to false, schema has NOT NULL
            $table->boolean('send_result_after_result')->default(false); // Defaulted to false
            $table->boolean('edit_result_after_auth')->default(true);
            $table->string('auditor_stamp')->nullable(); // Path to image or base64
            $table->string('manager_stamp')->nullable(); // Path to image or base64

            $table->unsignedBigInteger('finance_account_id')->nullable();
            $table->foreign('finance_account_id')->references('id')->on('finance_accounts')->onDelete('set null');

            $table->unsignedBigInteger('bank_id')->nullable(); // Assuming FK to finance_accounts (type 'bank')
            $table->foreign('bank_id')->references('id')->on('finance_accounts')->onDelete('set null');

            $table->unsignedBigInteger('company_account_id')->nullable();
            $table->foreign('company_account_id')->references('id')->on('finance_accounts')->onDelete('set null');

            $table->unsignedBigInteger('endurance_account_id')->nullable();
            $table->foreign('endurance_account_id')->references('id')->on('finance_accounts')->onDelete('set null');

            $table->unsignedBigInteger('main_cash')->nullable();
            $table->foreign('main_cash')->references('id')->on('finance_accounts')->onDelete('set null');

            $table->unsignedBigInteger('main_bank')->nullable();
            $table->foreign('main_bank')->references('id')->on('finance_accounts')->onDelete('set null');

            $table->date('financial_year_start')->nullable();
            $table->date('financial_year_end')->nullable();

            $table->unsignedBigInteger('pharmacy_bank')->nullable();
            $table->foreign('pharmacy_bank')->references('id')->on('finance_accounts')->onDelete('set null');

            $table->unsignedBigInteger('pharmacy_cash')->nullable();
            $table->foreign('pharmacy_cash')->references('id')->on('finance_accounts')->onDelete('set null');

            $table->unsignedBigInteger('pharmacy_income')->nullable();
            $table->foreign('pharmacy_income')->references('id')->on('finance_accounts')->onDelete('set null');

            $welcomeMessage = " مرحباً بكم في مستشفى الرومي للأسنان! ✨\n\n" .
                              " يسعدنا اختياركم لنا للعناية بصحة أسنانكم.\n\n" .
                              "‍⚕️‍⚕️ فريقنا المتخصص ملتزم بتقديم خدمات استثنائية في بيئة مريحة.\n\n" .
                              " ابتسامتكم هي أولويتنا!\n\n" .
                              " للاستفسارات، يرجى التواصل معنا وسنكون سعداء بالرد على استفساراتكم.\n\n" .
                              " شكراً لثقتكم بنا.";
            $table->text('welcome_message')->default($welcomeMessage);
            $table->boolean('send_welcome_message')->default(false); // Defaulted, schema has NOT NULL

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};