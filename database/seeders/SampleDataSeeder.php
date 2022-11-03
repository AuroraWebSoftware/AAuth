<?php

namespace AuroraWebSoftware\AAuth\Database\Seeders;

use AuroraWebSoftware\AAuth\Models\OrganizationNode;
use AuroraWebSoftware\AAuth\Models\OrganizationScope;
use AuroraWebSoftware\AAuth\Models\Role;
use AuroraWebSoftware\AAuth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user1 = User::create(
            [
                'name' => 'Example User 1',
                'email' => 'user1@example.com',
                'password' => Hash::make('password'),
            ]
        );

        $user2 = User::create(
            [
                'name' => 'Example User 2',
                'email' => 'user2@example.com',
                'password' => Hash::make('password'),
            ]
        );

        $user3 = User::create(
            [
                'name' => 'Example User 3',
                'email' => 'user3@example.com',
                'password' => Hash::make('password'),
            ]
        );

        $organizationScope1 = OrganizationScope::whereName('Root Scope')->first();

        $organizationScope2 = OrganizationScope::create([
            'name' => 'Sub-Scope',
            'level' => 10,
            'status' => 'active',
        ]);

        $organizationScope3 = OrganizationScope::create([
            'name' => 'Sub-Sub-Scope',
            'level' => 20,
            'status' => 'active',
        ]);

        $organizationNode1 = OrganizationNode::whereName('Root Node')->first();

        $organizationNode2 = OrganizationNode::create(
            [
                'organization_scope_id' => $organizationScope2->id,
                'name' => 'Organization Node 1.2',
                'model_type' => null,
                'model_id' => null,
                'path' => '1/temp',
                'parent_id' => $organizationNode1->id,
            ]
        );
        $organizationNode2->path = $organizationNode1->id.'/'.$organizationNode2->id;
        $organizationNode2->save();

        $organizationNode3 = OrganizationNode::create(
            [
                'organization_scope_id' => $organizationScope2->id,
                'name' => 'Organization Node 1.3',
                'model_type' => null,
                'model_id' => null,
                'path' => '1/temp',
                'parent_id' => $organizationNode1->id,
            ]
        );
        $organizationNode3->path = $organizationNode1->id.'/'.$organizationNode3->id;
        $organizationNode3->save();

        $organizationNode4 = OrganizationNode::create(
            [
                'organization_scope_id' => $organizationScope3->id,
                'name' => 'Organization Node 1.2.4',
                'model_type' => null,
                'model_id' => null,
                'path' => '1/temp',
                'parent_id' => $organizationNode2->id,
            ]
        );
        $organizationNode4->path = $organizationNode2->path.'/'.$organizationNode4->id;
        $organizationNode4->save();

        $role1 = Role::create([
            'type' => 'system',
            'name' => 'System Role 1',
            'status' => 'active',
        ]);

        $role2 = Role::create([
            'type' => 'system',
            'name' => 'System Role 2',
            'status' => 'active',
        ]);

        $role3 = Role::create([
            'organization_scope_id' => $organizationScope1->id,
            'type' => 'organization',
            'name' => 'Root Role 1',
            'status' => 'active',
        ]);

        $role4 = Role::create([
            'organization_scope_id' => $organizationScope1->id,
            'type' => 'organization',
            'name' => 'Root Role 2',
            'status' => 'active',
        ]);

        $role5 = Role::create([
            'organization_scope_id' => $organizationScope2->id,
            'type' => 'organization',
            'name' => 'Sub-Scope Role 1',
            'status' => 'active',
        ]);

        $role6 = Role::create([
            'organization_scope_id' => $organizationScope2->id,
            'type' => 'organization',
            'name' => 'Sub-Scope Role 2',
            'status' => 'active',
        ]);

        $role7 = Role::create([
            'organization_scope_id' => $organizationScope3->id,
            'type' => 'organization',
            'name' => 'Sub-Sub-Scope Role 1',
            'status' => 'active',
        ]);

        DB::table('user_role_organization_node')->insert([
            'user_id' => $user1->id,
            'role_id' => $role1->id,
        ]);

        DB::table('user_role_organization_node')->insert([
            'user_id' => $user1->id,
            'role_id' => $role2->id,
        ]);

        DB::table('user_role_organization_node')->insert([
            'user_id' => $user1->id,
            'role_id' => $role3->id,
            'organization_node_id' => $organizationNode1->id,
        ]);

        DB::table('user_role_organization_node')->insert([
            'user_id' => $user1->id,
            'role_id' => $role5->id,
            'organization_node_id' => $organizationNode2->id,
        ]);

        DB::table('user_role_organization_node')->insert([
            'user_id' => $user1->id,
            'role_id' => $role7->id,
            'organization_node_id' => $organizationNode4->id,
        ]);

        $systemPermissions = config('aauth.permissions.system');

        foreach ($systemPermissions as $key => $val) {
            DB::table('role_permission')->insert([
                'role_id' => $role1->id,
                'permission' => $key,
            ]);
        }

        foreach ($systemPermissions as $key => $val) {
            DB::table('role_permission')->insert([
                'role_id' => $role2->id,
                'permission' => $key,
            ]);
        }

        $organizationPermissions = config('aauth.permissions.organization');

        foreach ($organizationPermissions as $key => $val) {
            DB::table('role_permission')->insert([
                'role_id' => $role3->id,
                'permission' => $key,
            ]);
        }

        foreach ($organizationPermissions as $key => $val) {
            DB::table('role_permission')->insert([
                'role_id' => $role4->id,
                'permission' => $key,
            ]);
        }

        foreach ($organizationPermissions as $key => $val) {
            DB::table('role_permission')->insert([
                'role_id' => $role5->id,
                'permission' => $key,
            ]);
        }

        foreach ($organizationPermissions as $key => $val) {
            DB::table('role_permission')->insert([
                'role_id' => $role6->id,
                'permission' => $key,
            ]);
        }

        foreach ($organizationPermissions as $key => $val) {
            DB::table('role_permission')->insert([
                'role_id' => $role7->id,
                'permission' => $key,
            ]);
        }

        if (config('database.default') == 'pgsql') {
            $tables = DB::select('SELECT table_name FROM information_schema.tables WHERE table_schema = \'public\' ORDER BY table_name;');

            // Set the tables in the database you would like to ignore
            $ignores = ['admin_setting', 'model_has_permissions', 'model_has_roles', 'password_resets', 'role_has_permissions', 'sessions'];

            //loop through the tables
            foreach ($tables as $table) {
                // if the table is not to be ignored then:
                if (! in_array($table->table_name, $ignores)) {
                    //Get the max id from that table and add 1 to it
                    $seq = DB::table($table->table_name)->max('id') + 1;

                    // alter the sequence to now RESTART WITH the new sequence index from above
                    DB::select('ALTER SEQUENCE '.$table->table_name.'_id_seq RESTART WITH '.$seq);
                }
            }
        }
    }
}
