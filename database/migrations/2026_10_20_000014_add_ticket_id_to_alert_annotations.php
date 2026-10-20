<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Alerts link to the unified report record instead of the legacy incident.
     * Added before the consolidation migration so alerts can be re-pointed.
     */
    public function up(): void
    {
        Schema::table('alert_annotations', function (Blueprint $table) {
            $table->foreignId('ticket_id')->nullable()->after('incident_id')
                ->constrained('tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alert_annotations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ticket_id');
        });
    }
};
