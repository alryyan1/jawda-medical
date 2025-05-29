<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('report_header_company_name')->nullable()->after('token'); // Or after a relevant field
            $table->string('report_header_address_line1')->nullable()->after('report_header_company_name');
            $table->string('report_header_address_line2')->nullable()->after('report_header_address_line1');
            $table->string('report_header_phone')->nullable()->after('report_header_address_line2');
            $table->string('report_header_email')->nullable()->after('report_header_phone');
            $table->string('report_header_vatin')->nullable()->after('report_header_email');
            $table->string('report_header_cr')->nullable()->after('report_header_vatin');
            $table->text('report_header_logo_base64')->nullable()->after('report_header_cr'); // If storing logo for reports as base64
            // OR if storing path:
            // $table->string('report_header_logo_path')->nullable()->after('report_header_cr');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn([
                'report_header_company_name',
                'report_header_address_line1',
                'report_header_address_line2',
                'report_header_phone',
                'report_header_email',
                'report_header_vatin',
                'report_header_cr',
                'report_header_logo_base64', // or 'report_header_logo_path'
            ]);
        });
    }
};