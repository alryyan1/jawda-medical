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
        Schema::table('labrequests', function (Blueprint $table) {
            //done
            $table->boolean('done')->default(false);
       
          
       
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('labrequests', function (Blueprint $table) {
            $table->dropColumn('done');
           
         
        });
    }
};
