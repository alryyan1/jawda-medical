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
    public function up()
    {
        Schema::create('income_statements', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Table name (e.g., "Income Statement - [User]")
            $table->foreignIdFor(User::class); // Optional: User who created the table
            $table->json('data'); // Store table data as JSON
            $table->timestamps();

        });
    }

    public function down()
    {
        Schema::dropIfExists('dynamic_tables');
    }
};
