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
        Schema::create('mindray2', function (Blueprint $table) {
            $table->id('id');
            $table->integer('doctorvisit_id');
            $table->string('pho');
            $table->string('mg');
            $table->string('ca');
            $table->integer('gluh');
            $table->string('tb');
            $table->string('db');
            $table->integer('alt');
            $table->integer('ast');
            $table->string('crp');
            $table->integer('alp');
            $table->string('tp');
            $table->string('alb');
            $table->integer('tg');
            $table->integer('ldl');
            $table->integer('hdl');
            $table->integer('tc');
            $table->string('crea');
            $table->string('uric');
            $table->integer('urea');
            $table->string('ckmb');
            $table->string('ck');
            $table->string('ldh');
            $table->string('fe');
            $table->string('fer');
            $table->integer('glug');
            $table->string('ddimer');
            $table->string('amylase');
            $table->string('lipase');
            $table->integer('aso');
            $table->string('tibc');
            $table->string('acr');
            $table->string('pcr');
            $table->decimal('hb', 10, 2);
            $table->string('na');
            $table->string('k');
            $table->string('c1');
            $table->string('c2');
            $table->string('ggt', 200);
            $table->string('a1c', 200);
            $table->string('iron', 200);
            $table->string('tpc3', 200);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mindray2');
    }
};
