<?php

namespace App\Services;

use PDO;
use PDOException;

class DatabaseProbe
{
    /**
     * Try the credentials against the real database and create it when it does
     * not exist yet, which is the usual state on a fresh install.
     *
     * @return array{ok: bool, message: string, created: bool}
     */
    public function probe(string $driver, string $host, int $port, string $database, string $username, string $password): array
    {
        $created = false;

        try {
            $this->connect($driver, $host, $port, $database, $username, $password);

            return ['ok' => true, 'message' => 'Connected to the database.', 'created' => false];
        } catch (PDOException $e) {
            if (! $this->missingDatabase($e)) {
                return ['ok' => false, 'message' => $this->friendlyError($e), 'created' => false];
            }
        }

        try {
            $maintenance = $driver === 'pgsql' ? 'postgres' : null;
            $pdo = $this->connect($driver, $host, $port, $maintenance, $username, $password);

            $quoted = $driver === 'pgsql'
                ? '"'.str_replace('"', '""', $database).'"'
                : '`'.str_replace('`', '``', $database).'`';

            $pdo->exec('CREATE DATABASE '.$quoted);
            $created = true;

            $this->connect($driver, $host, $port, $database, $username, $password);

            return ['ok' => true, 'message' => 'Database created and connected.', 'created' => true];
        } catch (PDOException $e) {
            return ['ok' => false, 'message' => $this->friendlyError($e), 'created' => false];
        }
    }

    private function connect(string $driver, string $host, int $port, ?string $database, string $username, string $password): PDO
    {
        $dsn = sprintf('%s:host=%s;port=%d', $driver, $host, $port);

        if ($database !== null && $database !== '') {
            $dsn .= ';dbname='.$database;
        }

        return new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }

    private function missingDatabase(PDOException $e): bool
    {
        $message = $e->getMessage();

        return str_contains($message, 'does not exist')
            || str_contains($message, 'Unknown database');
    }

    private function friendlyError(PDOException $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'password authentication failed') || str_contains($message, 'Access denied')) {
            return 'The database rejected the username or password.';
        }

        if (str_contains($message, 'could not translate host name') || str_contains($message, 'getaddrinfo')) {
            return 'The database host could not be resolved.';
        }

        if (str_contains($message, 'Connection refused') || str_contains($message, 'No connection could be made')) {
            return 'The database refused the connection. Check the host and port.';
        }

        if (str_contains($message, 'could not find driver')) {
            return 'The PHP driver for this database is not installed.';
        }

        return 'Database connection failed: '.$message;
    }
}
