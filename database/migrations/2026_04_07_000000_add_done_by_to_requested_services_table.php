<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('requested_services', function (Blueprint $table) {
            $table->unsignedBigInteger('done_by_user_id')->nullable()->after('done');
            $table->timestamp('done_at')->nullable()->after('done_by_user_id');
            $table->foreign('done_by_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('requested_services', function (Blueprint $table) {
            $table->dropForeign(['done_by_user_id']);
            $table->dropColumn(['done_by_user_id', 'done_at']);
        });
    }
};
