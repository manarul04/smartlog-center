<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Default review state is "not reviewed" (muted). Existing rows created
     * before this change used "open" as a placeholder, which implied work had
     * started, so they are migrated to the new neutral state.
     */
    public function up(): void
    {
        DB::table('vulnerability_reviews')
            ->whereNull('assignee_id')
            ->where('status', 'open')
            ->update(['status' => 'not_reviewed']);

        DB::statement("ALTER TABLE vulnerability_reviews ALTER COLUMN status SET DEFAULT 'not_reviewed'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE vulnerability_reviews ALTER COLUMN status SET DEFAULT 'open'");

        DB::table('vulnerability_reviews')
            ->where('status', 'not_reviewed')
            ->update(['status' => 'open']);
    }
};
