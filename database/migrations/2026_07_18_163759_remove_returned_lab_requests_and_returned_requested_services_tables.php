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
        Schema::dropIfExists('returned_lab_requests');
        Schema::dropIfExists('returned_requested_services');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Irreversible: the refund/return feature and its schema have been removed from the codebase.
    }
};
