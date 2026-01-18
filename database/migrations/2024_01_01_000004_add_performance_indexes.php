<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('roles') && ! $this->hasIndex('roles', 'idx_roles_panel_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->index('panel_id', 'idx_roles_panel_id');
            });
        }

        if (Schema::hasTable('role_permission') && ! $this->hasIndex('role_permission', 'idx_role_permission_composite')) {
            Schema::table('role_permission', function (Blueprint $table) {
                $table->index(['role_id', 'permission'], 'idx_role_permission_composite');
            });
        }

        if (Schema::hasTable('user_role_organization_node') && ! $this->hasIndex('user_role_organization_node', 'idx_uron_composite')) {
            Schema::table('user_role_organization_node', function (Blueprint $table) {
                $table->index(['user_id', 'role_id'], 'idx_uron_composite');
            });
        }

        if (Schema::hasTable('organization_nodes') && ! $this->hasIndex('organization_nodes', 'idx_organization_nodes_path')) {
            Schema::table('organization_nodes', function (Blueprint $table) {
                $table->index('path', 'idx_organization_nodes_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('roles') && $this->hasIndex('roles', 'idx_roles_panel_id')) {
            Schema::table('roles', function (Blueprint $table) {
                $table->dropIndex('idx_roles_panel_id');
            });
        }

        if (Schema::hasTable('role_permission') && $this->hasIndex('role_permission', 'idx_role_permission_composite')) {
            Schema::table('role_permission', function (Blueprint $table) {
                $table->dropIndex('idx_role_permission_composite');
            });
        }

        if (Schema::hasTable('user_role_organization_node') && $this->hasIndex('user_role_organization_node', 'idx_uron_composite')) {
            Schema::table('user_role_organization_node', function (Blueprint $table) {
                $table->dropIndex('idx_uron_composite');
            });
        }

        if (Schema::hasTable('organization_nodes') && $this->hasIndex('organization_nodes', 'idx_organization_nodes_path')) {
            Schema::table('organization_nodes', function (Blueprint $table) {
                $table->dropIndex('idx_organization_nodes_path');
            });
        }
    }

    protected function hasIndex(string $table, string $indexName): bool
    {
        if (method_exists(Schema::class, 'getIndexes')) {
            $indexes = Schema::getIndexes($table);
            foreach ($indexes as $index) {
                if ($index['name'] === $indexName || $index['name'] === strtolower($indexName)) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }
};
