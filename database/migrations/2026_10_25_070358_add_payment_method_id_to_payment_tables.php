<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('labrequests', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('is_bankak')->constrained('payment_methods')->nullOnDelete();
        });

        Schema::table('requested_services', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('bank')->constrained('payment_methods')->nullOnDelete();
        });

        Schema::table('requested_service_deposits', function (Blueprint $table) {
            $table->foreignId('payment_method_id')->nullable()->after('is_bank')->constrained('payment_methods')->nullOnDelete();
        });

        $cashId = DB::table('payment_methods')->where('code', 'cash')->value('id');
        $bankId = DB::table('payment_methods')->where('code', 'bank')->value('id');

        DB::table('labrequests')->where('is_bankak', 1)->update(['payment_method_id' => $bankId]);
        DB::table('labrequests')->where('is_bankak', 0)->orWhereNull('is_bankak')->update(['payment_method_id' => $cashId]);

        DB::table('requested_services')->where('bank', 1)->update(['payment_method_id' => $bankId]);
        DB::table('requested_services')->where('bank', 0)->orWhereNull('bank')->update(['payment_method_id' => $cashId]);

        DB::table('requested_service_deposits')->where('is_bank', 1)->update(['payment_method_id' => $bankId]);
        DB::table('requested_service_deposits')->where('is_bank', 0)->orWhereNull('is_bank')->update(['payment_method_id' => $cashId]);
    }

    public function down(): void
    {
        Schema::table('labrequests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
        });

        Schema::table('requested_services', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
        });

        Schema::table('requested_service_deposits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_method_id');
        });
    }
};
