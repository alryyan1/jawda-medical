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
        Schema::create('hormone', function (Blueprint $table) {
            $table->integer('id')->autoIncrement();
            $table->string('doctorvisit_id', 11);
            $table->string('tsh');
            $table->string('t3');
            $table->string('t4');
            $table->string('fsh');
            $table->string('lh');
            $table->string('prl');
            $table->string('vitd');
            $table->string('pth');
            $table->string('psa');
            $table->string('fpsa');
            $table->string('ft3');
            $table->string('ft4');
            $table->string('ferr');
            $table->string('folate');
            $table->string('afp');
            $table->string('ca153');
            $table->string('ca199');
            $table->string('ca125');
            $table->string('amh');
            $table->string('e2');
            $table->string('prog');
            $table->string('testo');
            $table->string('bhcg');
            $table->string('cortiso');
            $table->string('cea');
            $table->string('hiv');
            $table->string('antihcv');
            $table->string('trop');
            $table->string('vb12');
            $table->string('hbsag', 40);
            $table->string('ana', 10);
            $table->string('dsdna', 10);
            $table->string('ins');
            $table->string('cp');
            $table->string('antihbc', 10);
            $table->string('Anti_HBe', 10);
            $table->string('HBeAg');
            $table->string('ccp');
            $table->string('CK_MB');
            $table->string('CMV_IgG');
            $table->string('CMV_IgM');
            $table->string('dimer');
            $table->string('GH');
            $table->string('HE4');
            $table->string('HSV_IgG');
            $table->string('HSV_IgM');
            $table->string('IgA', 10);
            $table->string('IgE', 10);
            $table->string('IgG', 10);
            $table->string('IgM', 10);
            $table->string('PCT');
            $table->string('Rubella_IgG');
            $table->string('Rubella_IgM');
            $table->string('TOXO_IgG');
            $table->string('TOXO_IgM');
            $table->string('acth');
            $table->string('antihbs', 10);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hormone');
    }
};
