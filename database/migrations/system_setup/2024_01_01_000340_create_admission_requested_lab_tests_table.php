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
        Schema::create('admission_requested_lab_tests', function (Blueprint $table) {
            $table->id('id');
            $table->unsignedBigInteger('admission_id');
            $table->unsignedBigInteger('main_test_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->decimal('discount', 10, 2)->default(0.00);
            $table->integer('discount_per')->default(0);
            $table->boolean('done')->default(0);
            $table->boolean('approval')->default(0);
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->foreign('admission_id', 'admission_requested_lab_tests_admission_id_foreign')
                  ->references('id')
                  ->on('admissions')
                  ->onDelete('cascade');
            $table->foreign('doctor_id', 'admission_requested_lab_tests_doctor_id_foreign')
                  ->references('id')
                  ->on('doctors')
                  ->onDelete('cascade');
            $table->foreign('main_test_id', 'admission_requested_lab_tests_main_test_id_foreign')
                  ->references('id')
                  ->on('main_tests')
                  ->onDelete('cascade');
            $table->foreign('user_id', 'admission_requested_lab_tests_user_id_foreign')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_requested_lab_tests');
    }
};
