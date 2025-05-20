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
        Schema::create('shifts', function (Blueprint $table) {
            $table->id(); // `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT
            $table->decimal('total', 8, 2); // `total` double(8,2) NOT NULL - Using decimal for precision
            $table->decimal('bank', 8, 2); // `bank` double(8,2) NOT NULL - Using decimal
            $table->decimal('expenses', 8, 2); // `expenses` double(8,2) NOT NULL - Using decimal
            $table->boolean('touched'); // `touched` tinyint(1) NOT NULL
            $table->dateTime('closed_at')->nullable(); // `closed_at` datetime DEFAULT NULL
            $table->boolean('is_closed')->default(false); // `is_closed` tinyint(1) NOT NULL DEFAULT 0
            $table->boolean('pharmacy_entry')->nullable(); // `pharamacy_entry` tinyint(1) DEFAULT NULL (corrected spelling)
            //user id
            $table->foreignIdFor(App\Models\User::class, 'user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps(); // `created_at` and `updated_at`
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};