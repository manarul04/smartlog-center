<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kb_catalog', function (Blueprint $table) {
            $table->id();
            // Wazuh's hotfix inventory only carries the article number, so the
            // article number is the natural key of everything we resolve here.
            $table->string('kb', 20)->unique();
            $table->string('title', 300)->nullable();
            $table->string('product', 160)->nullable();
            $table->string('classification', 80)->nullable();
            // One article can ship as several catalog entries (x64, arm64,
            // Server). The full list is kept so the best match for a given
            // agent can be chosen without another network call.
            $table->json('variants')->nullable();
            $table->string('source', 20)->default('none');
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('fetched_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kb_catalog');
    }
};
