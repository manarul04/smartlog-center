<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A ticket is the single report record. Vulnerability lifecycle fields move
     * here so one table covers incidents, vulnerabilities and SCA findings.
     */
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->text('resolution')->nullable()->after('description');
            $table->timestamp('last_verified_at')->nullable()->after('resolved_at');
            $table->boolean('still_present')->default(true)->after('last_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['resolution', 'last_verified_at', 'still_present']);
        });
    }
};
