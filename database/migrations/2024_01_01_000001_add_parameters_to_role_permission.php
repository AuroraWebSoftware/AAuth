<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add parameters JSON column to role_permission table for parametric permissions.
     * This allows storing permission-specific parameters like max_edits_per_day, allowed_statuses, etc.
     */
    public function up(): void
    {
        Schema::table('role_permission', function (Blueprint $table) {
            $table->json('parameters')->nullable()->after('permission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('role_permission', function (Blueprint $table) {
            $table->dropColumn('parameters');
        });
    }
};
