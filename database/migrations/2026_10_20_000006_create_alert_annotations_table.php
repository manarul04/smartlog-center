<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alert_annotations', function (Blueprint $table) {
            $table->id();
            // Stable identifier taken from the alert document's own `id` field,
            // so an annotation survives index rotation and reindexing.
            $table->string('alert_uid', 120)->unique();
            $table->string('alert_index', 120)->nullable();
            $table->string('status', 20)->default('new');
            $table->foreignId('incident_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('agent_name', 120)->nullable();
            $table->string('rule_id', 40)->nullable();
            $table->string('rule_description', 255)->nullable();
            $table->unsignedTinyInteger('rule_level')->nullable();
            $table->timestamp('alert_timestamp')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('incident_id');
            $table->index('assignee_id');
        });

        Schema::create('alert_annotation_label', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_annotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('label_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['alert_annotation_id', 'label_id'], 'annotation_label_unique');
        });

        Schema::create('alert_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alert_annotation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index('alert_annotation_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alert_comments');
        Schema::dropIfExists('alert_annotation_label');
        Schema::dropIfExists('alert_annotations');
    }
};
