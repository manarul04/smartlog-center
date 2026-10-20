<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Throwable;

/**
 * Resolves a report period from a preset key or explicit dates, so every
 * export queries exactly the window its cover page claims.
 */
class ReportRange
{
    public const PRESETS = [
        '24h' => ['label' => 'Last 24 hours', 'days' => 1],
        '7d' => ['label' => 'Last 7 days', 'days' => 7],
        '30d' => ['label' => 'Last 30 days', 'days' => 30],
        '90d' => ['label' => 'Last 90 days', 'days' => 90],
        'custom' => ['label' => 'Custom dates', 'days' => null],
        'all' => ['label' => 'All time', 'days' => null],
    ];

    public static function resolve(?string $range, ?string $from = null, ?string $to = null): array
    {
        $key = isset(self::PRESETS[(string) $range]) ? (string) $range : '30d';

        if ($key === 'custom') {
            $fromDate = self::parse($from)?->startOfDay();
            $toDate = self::parse($to)?->endOfDay();

            // A half-filled custom range still has to produce a usable window,
            // and the key stays "custom" so the form keeps the chosen mode.
            if ($fromDate === null && $toDate === null) {
                $toDate = Carbon::now();
                $fromDate = $toDate->copy()->subDays(30)->startOfDay();
            } elseif ($fromDate === null) {
                $fromDate = $toDate->copy()->subDays(30)->startOfDay();
            } elseif ($toDate === null) {
                $toDate = Carbon::now();
            }
        }

        if ($key === 'all') {
            $fromDate = null;
            $toDate = Carbon::now();
        } elseif ($key !== 'custom') {
            $toDate = Carbon::now();
            $fromDate = $toDate->copy()->subDays(self::PRESETS[$key]['days']);
        }

        if ($fromDate !== null && $fromDate->greaterThan($toDate)) {
            [$fromDate, $toDate] = [$toDate->copy()->startOfDay(), $fromDate->copy()->endOfDay()];
        }

        return [
            'key' => $key,
            'label' => $key === 'custom'
                ? $fromDate->format('d M Y') . ' — ' . $toDate->format('d M Y')
                : self::PRESETS[$key]['label'],
            'from' => $fromDate,
            'to' => $toDate,
            'from_iso' => $fromDate?->toIso8601String(),
            'to_iso' => $toDate->toIso8601String(),
            'label_long' => ($fromDate?->format('d F Y H:i') ?? 'start of retention')
                . ' — ' . $toDate->format('d F Y H:i'),
        ];
    }

    private static function parse(?string $value): ?Carbon
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
