<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auditors ask who touched an alert last and when. The annotation row is the
     * only per-alert record, so it carries that stamp alongside the status.
     */
    public function up(): void
    {
        Schema::table('alert_annotations', function (Blueprint $table) {
            $table->foreignId('last_action_by')->nullable()->after('assignee_id')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('last_action_at')->nullable()->after('last_action_by');

            $table->index('alert_timestamp');
        });
    }

    public function down(): void
    {
        Schema::table('alert_annotations', function (Blueprint $table) {
            $table->dropIndex(['alert_timestamp']);
            $table->dropConstrainedForeignId('last_action_by');
            $table->dropColumn('last_action_at');
        });
    }
};
