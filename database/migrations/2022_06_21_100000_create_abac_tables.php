<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAbacTables extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('role_model_abac_rules', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('role_id')->index();
            $table->string('model_type')->index();
            $table->json('rules_json');
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
        Schema::dropIfExists('role_model_abac_rules');
    }
}
