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
        if (!Schema::hasColumn('settings', 'cloud_api_token')) {    
            Schema::table('settings', function (Blueprint $table) {
                $table->string('cloud_api_token')->nullable();
            });
        } else {
            Schema::table('settings', function (Blueprint $table) {
                $table->string('cloud_api_token')->nullable()->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('cloud_api_token');
        });
    }
};
