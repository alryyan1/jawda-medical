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
        Schema::create('admission_doses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->onDelete('cascade');
            $table->string('medication_name'); // اسم الدواء
            $table->string('dosage')->nullable(); // الجرعة
            $table->string('frequency')->nullable(); // التكرار (مثل: كل 8 ساعات)
            $table->string('route')->nullable(); // طريقة الإعطاء (فموي، وريدي، عضلي، إلخ)
            $table->date('start_date')->nullable(); // تاريخ البدء
            $table->date('end_date')->nullable(); // تاريخ الانتهاء
            $table->text('instructions')->nullable(); // تعليمات خاصة
            $table->text('notes')->nullable(); // ملاحظات
            $table->foreignId('doctor_id')->nullable()->constrained('doctors')->onDelete('set null'); // الطبيب الموصي
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict'); // المستخدم الذي أضاف السجل
            $table->boolean('is_active')->default(true); // هل الجرعة نشطة
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_doses');
    }
};
