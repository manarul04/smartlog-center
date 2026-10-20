<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An incident tracks a group of similar alerts (same rule on the same
     * agent) over an explicit window. The count is computed live from
     * OpenSearch instead of copying every alert into the database.
     */
    public function up(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->string('group_agent', 120)->nullable()->after('severity');
            $table->string('group_rule_id', 40)->nullable()->after('group_agent');
            $table->timestamp('group_window_from')->nullable()->after('group_rule_id');
            $table->timestamp('group_window_to')->nullable()->after('group_window_from');
            $table->timestamp('group_counted_at')->nullable()->after('group_window_to');

            $table->index(['group_agent', 'group_rule_id']);
        });
    }

    public function down(): void
    {
        Schema::table('incidents', function (Blueprint $table) {
            $table->dropIndex(['group_agent', 'group_rule_id']);
            $table->dropColumn([
                'group_agent', 'group_rule_id', 'group_window_from',
                'group_window_to', 'group_counted_at',
            ]);
        });
    }
};
