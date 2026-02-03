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
        Schema::create('admission_nursing_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained('admissions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict'); // الممرض/الممرضة المسؤول
            $table->text('assignment_description'); // وصف المهمة
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium'); // الأولوية
            $table->enum('status', ['pending', 'in_progress', 'completed', 'cancelled'])->default('pending'); // الحالة
            $table->date('due_date')->nullable(); // تاريخ الاستحقاق
            $table->time('due_time')->nullable(); // وقت الاستحقاق
            $table->date('completed_date')->nullable(); // تاريخ الإنجاز
            $table->time('completed_time')->nullable(); // وقت الإنجاز
            $table->text('notes')->nullable(); // ملاحظات
            $table->text('completion_notes')->nullable(); // ملاحظات الإنجاز
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->onDelete('set null'); // المستخدم الذي كلف بالمهمة
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_nursing_assignments');
    }
};
