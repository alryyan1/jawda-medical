<?php

use App\Models\User;
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
        Schema::create('balance_sheet_statements', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Table name (e.g., "Income Statement - [User]")
            $table->foreignIdFor(User::class); // Optional: User who created the table
            $table->json('assets'); // Store table data as JSON
            $table->json('obligations'); // Store table data as JSON
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('balance_sheet_statements');
    }
};
