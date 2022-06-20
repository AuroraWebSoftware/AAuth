<?php


use Aurora\AAuth\Models\OrganizationNode;
use Aurora\AAuth\Models\OrganizationScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class SeedInitialData extends Migration
{

    /**
     * Run the migrations.
     *
     * @return void
     */

    public function up()
    {
        $organizationScope = new OrganizationScope();
        $organizationScope->id = 1;
        $organizationScope->name = "Root Scope";
        $organizationScope->level = 1;
        $organizationScope->status = "active";
        $organizationScope->save();


        if (config('database.default') == 'pgsql') {
            DB::select("
                    SELECT setval(pg_get_serial_sequence('organization_scopes', 'id'), coalesce(max(id)+1, 1), false)
                    FROM organization_scopes;
                ");
        }

        $on = new OrganizationNode();
        $on->id = 1;
        $on->organization_scope_id = 1;
        $on->name = "Root Node";
        $on->path = "1";
        $on->save();

        if (config('database.default') == 'pgsql') {
            DB::select("
                    SELECT setval(pg_get_serial_sequence('organization_scopes', 'id'), coalesce(max(id)+1, 1), false)
                    FROM organization_scopes;
                ");
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */

    public function down()
    {
        OrganizationScope::whereId(1)->delete();
        OrganizationNode::whereId(1)->delete();
    }
}
