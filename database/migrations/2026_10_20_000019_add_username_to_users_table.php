<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sign-in uses a username; email becomes an optional contact field. Existing
     * accounts get the local part of their email so nobody loses access.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 60)->nullable()->after('name');
        });

        foreach (DB::table('users')->orderBy('id')->get() as $user) {
            $base = strstr((string) $user->email, '@', true) ?: ('user'.$user->id);
            $candidate = $base;
            $suffix = 1;

            while (DB::table('users')->where('username', $candidate)->exists()) {
                $candidate = $base.$suffix++;
            }

            DB::table('users')->where('id', $user->id)->update(['username' => $candidate]);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->unique('username');
        });

        // Laravel 10 cannot change a column without doctrine/dbal, so the
        // nullable email is applied with the driver's own syntax.
        match (Schema::getConnection()->getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL'),
            'mysql', 'mariadb' => DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL'),
            default => null,
        };
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
        });
    }
};
