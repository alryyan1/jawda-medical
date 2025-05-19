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
        Schema::create('users', function (Blueprint $table) {
            // `id` bigint(20) UNSIGNED NOT NULL (AUTO_INCREMENT implied by id())
            $table->id();

            // `username` varchar(255) NOT NULL
            $table->string('username')->unique(); // Assuming username should be unique

            // `password` varchar(255) NOT NULL
            $table->string('password');

            // `remember_token` varchar(100) DEFAULT NULL
            $table->rememberToken(); // This creates a nullable VARCHAR(100) 'remember_token' column

            // `created_at` timestamp NULL DEFAULT NULL
            // `updated_at` timestamp NULL DEFAULT NULL
            $table->timestamps(); // This creates nullable `created_at` and `updated_at` timestamp columns

            // `doctor_id` bigint(20) UNSIGNED DEFAULT NULL
            // We'll define it as an unsigned big integer and make it nullable.
            // The foreign key constraint will be added once we have the 'doctors' table migration.
            $table->unsignedBigInteger('doctor_id')->nullable();

            // `is_nurse` tinyint(1) NOT NULL DEFAULT 0
            // In Laravel, tinyint(1) is typically represented as a boolean.
            $table->boolean('is_nurse')->default(false);

            // `name` varchar(255) NOT NULL (Laravel's default User model expects 'name')
            $table->string('name');

            // `user_money_collector_type` enum('lab','company','clinic','all') NOT NULL DEFAULT 'all'
            $table->enum('user_money_collector_type', ['lab', 'company', 'clinic', 'all'])->default('all');

            // Foreign key constraint for doctor_id (assuming 'doctors' table will exist)
            // You might want to add this later, or ensure the doctors table migration runs before this one.
            // $table->foreign('doctor_id')->references('id')->on('doctors')->onDelete('set null');
            // For now, we'll keep it commented out or add it when the 'doctors' table is defined.
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
