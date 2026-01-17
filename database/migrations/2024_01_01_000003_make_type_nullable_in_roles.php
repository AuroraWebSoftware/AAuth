<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique(['name', 'type']);
            $table->dropIndex(['type', 'name', 'status']);
        });

        DB::statement("ALTER TABLE roles MODIFY COLUMN type ENUM('system', 'organization') NULL");

        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['name', 'organization_scope_id'], 'roles_name_scope_unique');
            $table->index(['organization_scope_id', 'name', 'status']);
        });
    }

    public function down(): void
    {
        DB::statement("UPDATE roles SET type = 'system' WHERE type IS NULL AND organization_scope_id IS NULL");
        DB::statement("UPDATE roles SET type = 'organization' WHERE type IS NULL AND organization_scope_id IS NOT NULL");

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_scope_unique');
            $table->dropIndex(['organization_scope_id', 'name', 'status']);
        });

        DB::statement("ALTER TABLE roles MODIFY COLUMN type ENUM('system', 'organization') NOT NULL");

        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['name', 'type']);
            $table->index(['type', 'name', 'status']);
        });
    }
};
