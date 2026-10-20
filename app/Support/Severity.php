<?php

namespace App\Support;

class Severity
{
    public static function band(int $level): string
    {
        foreach (config('opensearch.severity_bands') as $key => $band) {
            if ($level >= $band['min'] && $level <= $band['max']) {
                return $key;
            }
        }

        return 'info';
    }

    public static function label(int $level): string
    {
        $band = self::band($level);

        return config("opensearch.severity_bands.{$band}.label", 'Info');
    }

    public static function color(int $level): string
    {
        $band = self::band($level);

        return config("opensearch.severity_bands.{$band}.color", '#64748b');
    }

    /**
     * CSS class token used by the UI badge components.
     */
    public static function token(int $level): string
    {
        return match (self::band($level)) {
            'critical' => 'critical',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'low',
            default => 'info',
        };
    }

    /**
     * Ordered band metadata for legends and charts.
     */
    public static function bands(): array
    {
        return config('opensearch.severity_bands');
    }

    public static function levelGroups(): array
    {
        return [
            'critical' => [12, 15],
            'high' => [10, 11],
            'medium' => [7, 9],
            'low' => [4, 6],
            'info' => [0, 3],
        ];
    }
}
