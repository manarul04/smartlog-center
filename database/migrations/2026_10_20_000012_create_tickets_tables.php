<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();
            // incident = alert report, vulnerability, sca, alert
            $table->string('type', 20);
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('not_reviewed');
            $table->string('priority', 20)->default('medium');
            $table->string('severity', 20)->nullable();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('due_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();

            // Live alert grouping (used by type=incident).
            $table->string('group_agent', 120)->nullable();
            $table->string('group_rule_id', 40)->nullable();
            $table->timestamp('group_window_from')->nullable();
            $table->timestamp('group_window_to')->nullable();
            $table->timestamp('group_counted_at')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
            $table->index('status');
            $table->index('assignee_id');
            $table->index(['group_agent', 'group_rule_id']);
        });

        Schema::create('ticket_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            // alert | vulnerability | sca_check
            $table->string('kind', 20);
            // Durable identity: alert uid, or agent|cve|package, or agent|policy|check
            $table->string('ref_key', 255);
            $table->string('agent_name', 120)->nullable();
            $table->string('title', 255)->nullable();
            $table->string('severity', 20)->nullable();
            $table->timestamp('occurred_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['ticket_id', 'kind', 'ref_key'], 'ticket_item_unique');
            $table->index(['kind', 'ref_key']);
        });

        Schema::create('ticket_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('ticket_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_events');
        Schema::dropIfExists('ticket_items');
        Schema::dropIfExists('tickets');
    }
};
