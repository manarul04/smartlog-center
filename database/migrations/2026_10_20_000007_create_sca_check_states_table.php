<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sca_check_states', function (Blueprint $table) {
            $table->id();
            // Stable identity of a check on a given agent and policy: the
            // OpenSearch document id changes on every rescan.
            $table->string('agent_name', 120);
            $table->string('policy', 200);
            $table->string('check_id', 40);
            $table->string('status', 20)->default('open');
            $table->text('note')->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['agent_name', 'policy', 'check_id'], 'sca_state_unique');
            $table->index('status');
            $table->index('agent_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sca_check_states');
    }
};
