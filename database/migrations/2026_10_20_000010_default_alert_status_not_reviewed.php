<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Alerts start as "not reviewed" rather than "new": an alert nobody has
     * touched is unreviewed, not a workflow state.
     */
    public function up(): void
    {
        DB::table('alert_annotations')
            ->where('status', 'new')
            ->update(['status' => 'not_reviewed']);

        DB::statement("ALTER TABLE alert_annotations ALTER COLUMN status SET DEFAULT 'not_reviewed'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE alert_annotations ALTER COLUMN status SET DEFAULT 'new'");

        DB::table('alert_annotations')
            ->where('status', 'not_reviewed')
            ->update(['status' => 'new']);
    }
};
