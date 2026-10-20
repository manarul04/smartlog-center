<?php

namespace App\Support;

class AlertStatus
{
    public const NOT_REVIEWED = 'not_reviewed';

    public const IN_REVIEW = 'in_review';

    public const IN_PROGRESS = 'in_progress';

    public const RESOLVED = 'resolved';

    public const FALSE_POSITIVE = 'false_positive';

    public const CLOSED = 'closed';

    /**
     * Ordered definition used by filters, badges and the status picker.
     */
    public static function all(): array
    {
        return [
            self::NOT_REVIEWED => ['label' => 'Not Reviewed', 'color' => '#64748b', 'open' => true],
            self::IN_REVIEW => ['label' => 'In Review', 'color' => '#38bdf8', 'open' => true],
            self::IN_PROGRESS => ['label' => 'In Progress', 'color' => '#fbbf24', 'open' => true],
            self::RESOLVED => ['label' => 'Resolved', 'color' => '#10b981', 'open' => false],
            self::FALSE_POSITIVE => ['label' => 'False Positive', 'color' => '#8b5cf6', 'open' => false],
            self::CLOSED => ['label' => 'Closed', 'color' => '#475569', 'open' => false],
        ];
    }

    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function label(?string $status): string
    {
        return self::all()[$status]['label'] ?? 'Not Reviewed';
    }

    public static function color(?string $status): string
    {
        return self::all()[$status]['color'] ?? '#64748b';
    }

    public static function isOpen(?string $status): bool
    {
        return self::all()[$status]['open'] ?? true;
    }

    /**
     * An alert with no annotation row is treated as not reviewed.
     */
    public static function isUnreviewed(?string $status): bool
    {
        return $status === null || $status === self::NOT_REVIEWED;
    }

    public static function openKeys(): array
    {
        return array_keys(array_filter(self::all(), fn ($s) => $s['open']));
    }
}
