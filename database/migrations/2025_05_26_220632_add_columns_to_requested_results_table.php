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
        Schema::table('requested_results', function (Blueprint $table) {
              //flags
              $table->string('flags')->nullable(); 
              //result_comment
              $table->string('result_comment')->nullable();
              //entered_by_user_id   
              $table->string('entered_by_user_id')->nullable();
              //entered_at
              $table->timestamp('entered_at')->nullable();
              //authorized_at
              $table->timestamp('authorized_at')->nullable();
              //authorized_by_user_id
              $table->string('authorized_by_user_id')->nullable();
              //unit_name
              $table->string('unit_name')->nullable();
          
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('requested_results', function (Blueprint $table) {
            $table->dropColumn('flags');
            $table->dropColumn('result_comment');
            $table->dropColumn('entered_by_user_id');
            $table->dropColumn('entered_at');
            $table->dropColumn('authorized_at');
            $table->dropColumn('authorized_by_user_id');
            $table->dropColumn('unit_name');
        });
    }
};
