<?php

use App\Models\Container;
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
        Schema::create('main_tests', function (Blueprint $table) {
            $table->id(); // `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT
            $table->string('main_test_name', 70); // `main_test_name` varchar(70) NOT NULL

            // `pack_id` int(11) DEFAULT NULL
            // We'll define it as an unsigned integer and make it nullable.
            // Foreign key can be added later if 'packs' table is introduced.
            $table->unsignedInteger('pack_id')->nullable();
            // Example for later: $table->foreign('pack_id')->references('id')->on('packs')->onDelete('set null');

            $table->boolean('pageBreak')->default(false); // `pageBreak` tinyint(1) NOT NULL DEFAULT 0

            // `container_id` int(11) NOT NULL DEFAULT 1
            // If containers.id is INT (using increments()), use unsignedInteger here.
            // If containers.id is BIGINT (using id()), use unsignedBigInteger here.
            // Assuming containers.id was made with $table->id() (BIGINT for consistency for now)
            // $table->unsignedBigInteger('container_id')->default(1);
            // If containers.id was made with $table->increments() (INT to match schema)
            // $table->unsignedInteger('container_id')->default(1);
            $table->foreignIdFor(Container::class); // Or 'cascade'

            $table->decimal('price', 10, 1)->nullable(); // `price` double(10,1) DEFAULT NULL - Using decimal
            $table->boolean('divided')->default(false); // `divided` tinyint(1) NOT NULL DEFAULT 0
            $table->boolean('available')->default(true); // `available` tinyint(1) NOT NULL DEFAULT 1

            // This table does not have created_at/updated_at in the SQL.
            // If you want them: $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('main_tests');
    }
};