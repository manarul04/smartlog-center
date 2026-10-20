<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A report can track one rule across several endpoints. `group_agent`
     * stays as the primary agent so existing rows and links keep working;
     * `group_agents` holds the full selection when it is wider than one.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->json('group_agents')->nullable()->after('group_agent');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn('group_agents');
        });
    }
};
