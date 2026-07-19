<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Children before parents.
        Schema::dropIfExists('user_sub_routes');
        Schema::dropIfExists('sub_routes');

        Schema::dropIfExists('shippings');
        Schema::dropIfExists('shipping_items');
        Schema::dropIfExists('shipping_states');

        Schema::dropIfExists('states');
        Schema::dropIfExists('skills');
        Schema::dropIfExists('payment_suppliers');
        Schema::dropIfExists('pdf_settings');
        Schema::dropIfExists('pharmacy_types');
        Schema::dropIfExists('tooth_requested_services');
        Schema::dropIfExists('whats_app_messages');
        Schema::dropIfExists('lab_finished_notifications');
        Schema::dropIfExists('mindray_cbc');
        Schema::dropIfExists('new_patient_notifications');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: this dead scaffolding cluster and its schema have been removed from the codebase.
    }
};
