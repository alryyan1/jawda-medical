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
        Schema::create('requested_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctorvisits_id')->constrained()->references('id')->on('doctorvisits');
            $table->foreignIdFor(\App\Models\Service::class)->constrained()->cascadeOnDelete()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\User::class)->constrained()->cascadeOnDelete()->cascadeOnDelete();
            $table->foreignIdFor(\App\Models\User::class,'user_deposited')->nullable()->constrained()->references('id')->on('users');
            $table->foreignIdFor(\App\Models\Doctor::class)->constrained()->cascadeOnDelete()->cascadeOnDelete();
            $table->float('price',11,2);
            $table->float('amount_paid',11,2);
            $table->float('endurance',11,2);
            $table->boolean('is_paid');
            $table->integer('discount');
            $table->boolean('bank');
            $table->integer('count');
            $table->timestamps();
            $table->unique(['doctorvisits_id','service_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('requested_services');
    }
};
