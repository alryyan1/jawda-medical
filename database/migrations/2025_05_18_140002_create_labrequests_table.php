<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('labrequests', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('main_test_id');
            $table->foreign('main_test_id')->references('id')->on('main_tests')->onDelete('cascade'); // Or restrict

            $table->unsignedBigInteger('pid'); // Patient ID
            $table->foreign('pid')->references('id')->on('patients')->onDelete('cascade'); // Or restrict

            $table->boolean('hidden')->default(true); // int(11) as boolean
            $table->boolean('is_lab2lab')->default(false); // int(11) as boolean
            $table->boolean('valid')->default(true); // int(11) as boolean
            $table->boolean('no_sample')->default(false); // int(11) as boolean

            $table->decimal('price', 10, 1)->default(0.0);
            $table->decimal('amount_paid', 10, 1)->default(0.0);
            $table->integer('discount_per')->default(0); // Percentage
            $table->boolean('is_bankak')->default(false); // int(11) as boolean

            $table->text('comment')->nullable();

            $table->unsignedBigInteger('user_requested')->nullable();
            $table->foreign('user_requested')->references('id')->on('users')->onDelete('set null');

            $table->unsignedBigInteger('user_deposited')->nullable();
            $table->foreign('user_deposited')->references('id')->on('users')->onDelete('set null');

            $table->boolean('approve')->default(false);
            $table->double('endurance'); // Or decimal
            $table->boolean('is_paid')->default(false);
            // Consider adding sample_collection_status, result_status, etc.
            $table->string('sample_id')->nullable()->unique()->comment('Unique ID for the sample collected for this test');


            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('labrequests');
    }
};