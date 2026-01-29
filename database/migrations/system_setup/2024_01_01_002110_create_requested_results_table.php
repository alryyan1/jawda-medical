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
            Schema::create('requested_results', function (Blueprint $table) {
                  $table->id('id');
                  $table->unsignedBigInteger('lab_request_id');
                  $table->unsignedBigInteger('patient_id');
                  $table->unsignedBigInteger('main_test_id');
                  $table->unsignedBigInteger('child_test_id');
                  $table->text('result')->default('');
                  $table->text('normal_range');
                  $table->timestamp('created_at')->nullable();
                  $table->timestamp('updated_at')->nullable();
                  $table->unsignedBigInteger('unit_id')->nullable();
                  $table->string('flags', 50)->nullable();
                  $table->text('result_comment')->nullable();
                  $table->longText('json_results')->nullable();
                  $table->unsignedBigInteger('entered_by_user_id')->nullable();
                  $table->timestamp('entered_at')->nullable();
                  $table->unsignedBigInteger('authorized_by_user_id')->nullable();
                  $table->timestamp('authorized_at')->nullable();
                  $table->unique(['main_test_id', 'patient_id', 'child_test_id'], 'requested_results_main_test_id_patient_id_child_test_id_unique');
                  $table->foreign('authorized_by_user_id', 'requested_results_authorized_by_user_id_foreign')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');
                  $table->foreign('entered_by_user_id', 'requested_results_entered_by_user_id_foreign')
                        ->references('id')
                        ->on('users')
                        ->onDelete('cascade');
                  $table->foreign('lab_request_id', 'requested_results_lab_request_id_foreign')
                        ->references('id')
                        ->on('labrequests')
                        ->onDelete('cascade');
                  $table->foreign('main_test_id', 'requested_results_main_test_id_foreign')
                        ->references('id')
                        ->on('main_tests')
                        ->onDelete('cascade');
                  $table->foreign('patient_id', 'requested_results_patient_id_foreign')
                        ->references('id')
                        ->on('patients')
                        ->onDelete('cascade');
                  $table->foreign('unit_id', 'requested_results_unit_id_foreign')
                        ->references('id')
                        ->on('units')
                        ->onDelete('cascade');
            });
      }

      /**
       * Reverse the migrations.
       */
      public function down(): void
      {
            Schema::dropIfExists('requested_results');
      }
};
