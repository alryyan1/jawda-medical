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
        Schema::create('admission_requested_service_costs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_requested_service_id')
                  ->constrained('admission_requested_services')
                  ->onDelete('cascade')
                  ->name('adm_req_svc_costs_req_svc_id_foreign');
            $table->foreignId('service_cost_id')->constrained('service_cost')->onDelete('restrict');
            $table->foreignId('sub_service_cost_id')->nullable()->constrained('sub_service_costs')->onDelete('set null');
            $table->decimal('amount', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admission_requested_service_costs');
    }
};
