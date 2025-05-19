<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('lab_endurance', 8, 2);
            $table->decimal('service_endurance', 8, 2);
            $table->boolean('status');
            $table->integer('lab_roof');
            $table->integer('service_roof');
            $table->string('phone');
            $table->string('email'); // Consider adding ->unique() if emails should be unique

            $table->unsignedBigInteger('finance_account_id')->nullable();
            $table->foreign('finance_account_id')->references('id')->on('finance_accounts')->onDelete('set null');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};