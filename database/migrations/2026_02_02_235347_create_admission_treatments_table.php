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
        Schema::create('admission_treatments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->onDelete('cascade');
            $table->text('treatment_plan')->nullable(); // خطة العلاج
            $table->text('treatment_details')->nullable(); // تفاصيل العلاج
            $table->text('notes')->nullable(); // ملاحظات
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict'); // المستخدم الذي أضاف السجل
            $table->date('treatment_date')->nullable(); // تاريخ العلاج
            $table->time('treatment_time')->nullable(); // وقت العلاج
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_treatments');
    }
};
