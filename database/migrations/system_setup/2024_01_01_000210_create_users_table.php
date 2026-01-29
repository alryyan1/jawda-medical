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
            $table->id('id');
            $table->string('username', 255);
            $table->string('password', 255);
            $table->string('remember_token', 100)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->boolean('is_nurse')->default(0);
            $table->boolean('is_supervisor')->default(0);
            $table->boolean('is_active')->default(1);
            $table->enum('user_type', ["استقبال معمل","ادخال نتائج","استقبال عياده","خزنه موحده","تامين"])->nullable();
            $table->longText('nav_items')->nullable();
            $table->string('name', 255);
            $table->enum('user_money_collector_type', ["lab","company","clinic","all"])->default('all');
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
