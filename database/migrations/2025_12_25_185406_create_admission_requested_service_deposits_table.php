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
        Schema::create('admission_requested_service_deposits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_requested_service_id')
                  ->constrained('admission_requested_services')
                  ->onDelete('cascade')
                  ->name('adm_req_svc_deposits_req_svc_id_foreign');
            $table->foreignId('user_id')->constrained('users')->onDelete('restrict');
            $table->decimal('amount', 10, 2);
            $table->boolean('is_bank')->default(false);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_requested_service_deposits');
    }
};
