<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add panel_id column to roles table for Filament panel support.
     * When a role has a panel_id, it's only available in that specific Filament panel.
     * When panel_id is NULL, the role is available in all panels.
     */
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('panel_id', 50)->nullable()->after('status');
            $table->index('panel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['panel_id']);
            $table->dropColumn('panel_id');
        });
    }
};
