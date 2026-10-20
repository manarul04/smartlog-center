<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The legacy handling tables are fully consolidated into tickets by the
     * previous migration. Dropping them removes the duplicate vocabularies.
     */
    public function up(): void
    {
        // incident_id was superseded by ticket_id on alert_annotations.
        if (Schema::hasColumn('alert_annotations', 'incident_id')) {
            Schema::table('alert_annotations', function ($table) {
                $table->dropConstrainedForeignId('incident_id');
            });
        }

        Schema::dropIfExists('incident_comments');
        Schema::dropIfExists('incidents');
        Schema::dropIfExists('vulnerability_review_events');
        Schema::dropIfExists('vulnerability_reviews');
        Schema::dropIfExists('sca_check_states');
    }

    public function down(): void
    {
        // Removed tables cannot be reconstructed from tickets without loss.
    }
};
