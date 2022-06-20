<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOrganizationTables extends Migration
{
    // todo droplar ve nullable'lar, fk
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('organization_scopes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->unsignedInteger('level');
            $table->enum('status', ['active', 'passive']);
            $table->timestamps();

            $table->unique(['name']);

            $table->index(['name', 'status', 'level']);
            $table->index(['name', 'status']);
            $table->index(['name']);
        });

        Schema::create('organization_nodes', function (Blueprint $table) {
            $table->bigIncrements('id')->unsigned();
            $table->unsignedBigInteger('organization_scope_id');
            $table->string('name');

            // for polymorphic relations
            $table->string('model_type')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->string('path');

            $table->unsignedBigInteger('parent_id')->nullable();
            $table->timestamps();

            $table->unique(['model_type', 'model_id']);
            $table->unique(['path']);

            $table->index(['model_type', 'model_id']);
            $table->index(['parent_id']);
            $table->index(['path']);
        });

        Schema::table('user_role_organization_node', function (Blueprint $table) {
            $table->foreign('organization_node_id')
                ->references('id')
                ->on('organization_nodes')
                ->onUpdate('cascade');
        });

        Schema::table('organization_nodes', function (Blueprint $table) {
            $table->foreign('organization_scope_id')
                ->references('id')
                ->on('organization_scopes')
                ->onUpdate('cascade');

            $table->foreign('parent_id')
                ->references('id')
                ->on('organization_nodes')
                ->onUpdate('cascade');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->foreign('organization_scope_id')
                ->references('id')
                ->on('organization_scopes')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('organization_scopes');
        Schema::dropIfExists('organization_nodes');
    }
}
