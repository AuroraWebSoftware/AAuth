<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRolesAndPermissionsTables extends Migration
{
    // todo droplar ve nullable'lar, fk

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            // the roles' organization scope
            $table->unsignedBigInteger('organization_scope_id')->nullable();
            $table->enum('type', ['system', 'organization']);
            $table->string('name');
            $table->enum('status', ['active', 'passive']);
            $table->timestamps();

            $table->unique(['name', 'type']);

            $table->index(['type', 'name', 'status']);
            $table->index(['name', 'status']);
            $table->index(['name']);
        });

        Schema::create('role_permission', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id');
            $table->string('permission', 128);

            $table->unique(['role_id', 'permission']);

            $table->index(['role_id', 'permission']);
            $table->index(['role_id']);
        });

        Schema::create('user_role_organization_node', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('role_id');
            $table->unsignedBigInteger('organization_node_id')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'role_id', 'organization_node_id'], 'user_role_organization_node_3_index');
            $table->index(['user_id', 'role_id']);
            $table->index(['user_id', 'organization_node_id']);
            $table->index(['role_id', 'organization_node_id']);
            $table->index(['user_id']);
        });

        Schema::table('user_role_organization_node', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onUpdate('cascade');

            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
                ->onUpdate('cascade');

            // organization node id is below
        });

        Schema::table('role_permission', function (Blueprint $table) {
            $table->foreign('role_id')
                ->references('id')
                ->on('roles')
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
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('user_role_organization_node');
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('roles');
    }
}
