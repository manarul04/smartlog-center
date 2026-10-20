<?php

namespace App\Support;

use Illuminate\Support\Facades\Schema;

class Setup
{
    private static ?bool $required = null;

    private static ?bool $databaseReady = null;

    private static ?bool $adminMissing = null;

    /**
     * The console is unusable until it has a database for its own state and an
     * OpenSearch endpoint for the security data, so either one being missing
     * sends every page to the setup wizard.
     */
    public static function required(): bool
    {
        if (self::$required !== null) {
            return self::$required;
        }

        return self::$required = trim((string) config('opensearch.host')) === ''
            || ! self::databaseReady()
            || self::administratorMissing();
    }

    public static function databaseReady(): bool
    {
        if (self::$databaseReady !== null) {
            return self::$databaseReady;
        }

        try {
            return self::$databaseReady = Schema::hasTable('users');
        } catch (\Throwable) {
            return self::$databaseReady = false;
        }
    }

    /**
     * Only a completely empty user table reopens the wizard. Creating the first
     * administrator must never be possible on an install that already has
     * accounts, otherwise the setup page would be an unauthenticated takeover
     * route.
     */
    public static function administratorMissing(): bool
    {
        if (self::$adminMissing !== null) {
            return self::$adminMissing;
        }

        try {
            return self::$adminMissing = ! \App\Models\User::query()->exists();
        } catch (\Throwable) {
            return self::$adminMissing = true;
        }
    }

    /**
     * Called after a step completes so the rest of the request sees the new
     * state without another round of probes.
     */
    public static function refresh(): void
    {
        self::$required = null;
        self::$databaseReady = null;
        self::$adminMissing = null;
    }
}
