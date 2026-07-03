<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * PostgreSQL ignores a plain btree index for `LIKE 'prefix%'` under a non-C
     * collation, so the hot materialized-path subtree queries (organizationNodes,
     * getAccessibleOrganizationNodes, the org-node global scope, descendant) would
     * seq-scan `organization_nodes`. A `varchar_pattern_ops` index makes them
     * index-backed. Driver-conditional and additive, so MySQL/MariaDB/SQLite — which
     * already use their default `path` index for prefix LIKE — are left untouched and
     * portability is preserved.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_org_nodes_path_pattern ON organization_nodes (path varchar_pattern_ops)');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_org_nodes_path_pattern');
        }
    }
};
