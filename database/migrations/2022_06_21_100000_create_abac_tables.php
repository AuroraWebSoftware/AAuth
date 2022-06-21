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
            $table->unsignedBigInteger('role_id')->index();
            $table->string('model_type')->index();
            $table->json('abac_rule');
            /**
            [ "and" : [ { "column" : "name", "operator" : "<", "value": "3" } ] ]
             */

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
