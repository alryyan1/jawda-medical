<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAccountHierarchyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('account_hierarchy', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id');
            $table->unsignedBigInteger('child_id');
            $table->integer('level')->nullable();
            $table->primary(['parent_id', 'child_id']);
            $table->foreign('parent_id')->references('id')->on('finance_accounts')->onDelete('cascade');
            $table->foreign('child_id')->references('id')->on('finance_accounts')->onDelete('cascade');
            //created_At
            $table->timestamps();
       
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('account_hierarchy');
    }
}