<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();

        DB::table('payment_methods')->insert([
            ['name' => 'كاش', 'code' => 'cash', 'is_active' => true, 'is_default' => true, 'sort_order' => 0, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'بنك', 'code' => 'bank', 'is_active' => true, 'is_default' => false, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'فوري', 'code' => 'fawry', 'is_active' => true, 'is_default' => false, 'sort_order' => 2, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'اوكاش', 'code' => 'ocash', 'is_active' => true, 'is_default' => false, 'sort_order' => 3, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
