<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id('id');
            $table->boolean('is_header')->nullable();
            $table->boolean('is_footer')->nullable();
            $table->boolean('is_logo')->nullable();
            $table->boolean('show_logo')->default(0);
            $table->boolean('show_logo_only_whatsapp')->default(0);
            $table->longText('header_base64')->nullable();
            $table->longText('footer_base64')->nullable();
            $table->string('header_content', 255)->nullable();
            $table->string('footer_content', 255)->nullable();
            $table->longText('logo_base64')->nullable();
            $table->string('lab_name', 255)->nullable();
            $table->string('firestore_result_collection', 255)->nullable();
            $table->string('hospital_name', 255)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->boolean('disable_doctor_service_check')->nullable();
            $table->string('phone', 255)->nullable();
            $table->boolean('gov')->nullable();
            $table->boolean('country')->nullable();
            $table->boolean('barcode')->nullable();
            $table->boolean('show_water_mark')->nullable();
            $table->string('vatin', 255)->nullable();
            $table->string('cr', 255)->nullable();
            $table->string('email', 255)->nullable();
            $table->string('address', 255)->nullable();
            $table->string('ultramsg_instance_id', 255)->nullable();
            $table->string('ultramsg_token', 255)->nullable();
            $table->string('ultramsg_base_url', 255)->nullable();
            $table->string('ultramsg_default_country_code', 255)->nullable();
            $table->boolean('send_result_after_auth')->nullable();
            $table->boolean('send_result_after_result')->nullable();
            $table->boolean('edit_result_after_auth')->nullable();
            $table->string('auditor_stamp', 255)->nullable();
            $table->string('manager_stamp', 255)->nullable();
            $table->unsignedBigInteger('finance_account_id')->nullable();
            $table->unsignedBigInteger('bank_id')->nullable();
            $table->unsignedBigInteger('company_account_id')->nullable();
            $table->unsignedBigInteger('endurance_account_id')->nullable();
            $table->unsignedBigInteger('main_cash')->nullable();
            $table->unsignedBigInteger('main_bank')->nullable();
            $table->date('financial_year_start')->nullable();
            $table->date('financial_year_end')->nullable();
            $table->unsignedBigInteger('pharmacy_bank')->nullable();
            $table->unsignedBigInteger('pharmacy_cash')->nullable();
            $table->unsignedBigInteger('pharmacy_income')->nullable();
            $table->text('welcome_message')->nullable()->default('');
            $table->boolean('send_welcome_message')->nullable();
            $table->string('default_lab_report_template', 255)->nullable();
            $table->string('watermark_image', 255)->nullable();
            $table->boolean('send_sms_after_auth')->default(0);
            $table->boolean('send_whatsapp_after_auth')->default(0);
            $table->boolean('show_title_in_lab_result')->nullable()->default(0);
            $table->string('storage_name', 255)->nullable();
            $table->boolean('prevent_backdated_entry')->default(1);
            $table->string('whatsapp_number', 255)->default(96878622990);
            $table->string('pdf_header_type', 255)->nullable()->default('logo');
            $table->string('pdf_header_logo_position', 255)->nullable()->default('left');
            $table->integer('pdf_header_logo_width')->nullable()->default(40);
            $table->integer('pdf_header_logo_height')->nullable()->default(40);
            $table->integer('pdf_header_logo_x_offset')->nullable()->default(5);
            $table->integer('pdf_header_logo_y_offset')->nullable()->default(5);
            $table->integer('pdf_header_image_width')->nullable()->default(200);
            $table->integer('pdf_header_image_height')->nullable()->default(30);
            $table->integer('pdf_header_image_x_offset')->nullable()->default(5);
            $table->integer('pdf_header_image_y_offset')->nullable()->default(5);
            $table->string('pdf_header_title', 255)->nullable();
            $table->string('pdf_header_subtitle', 255)->nullable();
            $table->integer('pdf_header_title_font_size')->nullable()->default(25);
            $table->integer('pdf_header_subtitle_font_size')->nullable()->default(17);
            $table->integer('pdf_header_title_y_offset')->nullable()->default(5);
            $table->integer('pdf_header_subtitle_y_offset')->nullable()->default(5);
            $table->foreign('bank_id', 'settings_bank_id_foreign')
                  ->references('id')
                  ->on('banks')
                  ->onDelete('cascade');
            $table->foreign('company_account_id', 'settings_company_account_id_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
            $table->foreign('endurance_account_id', 'settings_endurance_account_id_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
            $table->foreign('finance_account_id', 'settings_finance_account_id_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
            $table->foreign('main_bank', 'settings_main_bank_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
            $table->foreign('main_cash', 'settings_main_cash_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
            $table->foreign('pharmacy_bank', 'settings_pharmacy_bank_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
            $table->foreign('pharmacy_cash', 'settings_pharmacy_cash_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
            $table->foreign('pharmacy_income', 'settings_pharmacy_income_foreign')
                  ->references('id')
                  ->on('finance_accounts')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
