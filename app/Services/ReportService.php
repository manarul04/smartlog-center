<?php

namespace App\Services;

use App\Models\Label;
use App\Services\OpenSearch\AlertRepository;
use App\Support\AlertStatus;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ReportService
{
    public function __construct(
        private AlertRepository $alerts,
        private AnnotationService $annotations,
    ) {}

    /**
     * Turn a relative range key (now-7d) into concrete bounds so the report can
     * state the exact period it covers.
     */
    public function resolveRange(string $range): array
    {
        $ranges = config('opensearch.time_ranges');
        $key = isset($ranges[$range]) ? $range : '24h';
        $meta = $ranges[$key];

        $to = Carbon::now();
        $from = $this->subtract($to, $meta['from']);

        return [
            'key' => $key,
            'label' => $meta['label'],
            'from' => $from,
            'to' => $to,
            'from_iso' => $from->toIso8601String(),
            'to_iso' => $to->toIso8601String(),
        ];
    }

    private function subtract(Carbon $base, string $expression): Carbon
    {
        if (! preg_match('/^now-(\d+)([smhdw])$/', $expression, $m)) {
            return $base->copy()->subDay();
        }

        $amount = (int) $m[1];

        return match ($m[2]) {
            's' => $base->copy()->subSeconds($amount),
            'm' => $base->copy()->subMinutes($amount),
            'h' => $base->copy()->subHours($amount),
            'd' => $base->copy()->subDays($amount),
            'w' => $base->copy()->subWeeks($amount),
        };
    }

    /**
     * Build the human-readable report identity from the active filters.
     * The title is assembled from every filter that is actually set, so the
     * document describes itself without the reader needing the UI.
     */
    public function describe(array $filters, string $range, ?array $period = null, ?string $title = null, string $prefix = 'alert-report'): array
    {
        $period ??= $this->resolveRange($range);
        $descriptors = [];

        if (! empty($filters['q'])) {
            $descriptors[] = ['label' => 'Search', 'value' => $filters['q']];
        }
        if (! empty($filters['agent'])) {
            $descriptors[] = ['label' => 'Agent', 'value' => $filters['agent']];
        }
        if (! empty($filters['group'])) {
            $descriptors[] = ['label' => 'Rule group', 'value' => $filters['group']];
        }
        if (isset($filters['level_min']) && $filters['level_min'] !== '' && $filters['level_min'] !== null) {
            $descriptors[] = ['label' => 'Severity', 'value' => '≥ ' . (int) $filters['level_min']];
        }
        if (isset($filters['level_max']) && $filters['level_max'] !== '' && $filters['level_max'] !== null) {
            $descriptors[] = ['label' => 'Severity', 'value' => '≤ ' . (int) $filters['level_max']];
        }
        if (! empty($filters['status'])) {
            $descriptors[] = ['label' => 'Status', 'value' => AlertStatus::label($filters['status'])];
        }
        if (! empty($filters['label'])) {
            $label = Label::find($filters['label']);
            $descriptors[] = ['label' => 'Label', 'value' => $label?->name ?? 'Unknown'];
        }
        if (! empty($filters['mitre'])) {
            $descriptors[] = ['label' => 'MITRE', 'value' => $filters['mitre']];
        }
        if (! empty($filters['compliance_field']) && ! empty($filters['compliance_value'])) {
            $framework = collect(config('opensearch.compliance_frameworks'))
                ->firstWhere('field', $filters['compliance_field']);
            $descriptors[] = [
                'label' => $framework['label'] ?? 'Compliance',
                'value' => $filters['compliance_value'],
            ];
        }

        $scope = $descriptors === []
            ? 'All Alerts'
            : implode(' · ', array_map(fn ($d) => $d['label'] . ': ' . $d['value'], $descriptors));

        return [
            'title' => $title ?? 'Security Alert Report',
            'scope' => $scope,
            'descriptors' => $descriptors,
            'period' => $period,
            'period_label' => ($period['from']?->translatedFormat('d F Y H:i') ?? 'start of retention')
                . ' — ' . $period['to']->translatedFormat('d F Y H:i')
                . ' (' . $period['label'] . ')',
            'filename' => $this->filename($descriptors, $period, $prefix),
            'generated_at' => Carbon::now(),
        ];
    }

    private function filename(array $descriptors, array $period, string $prefix = 'alert-report'): string
    {
        $parts = [$prefix];

        foreach ($descriptors as $descriptor) {
            $parts[] = Str::slug($descriptor['label'] . '-' . $descriptor['value'], '-');
        }

        $parts[] = $period['from']?->format('Ymd') ?? 'start';
        $parts[] = $period['to']->format('Ymd');

        $name = Str::limit(implode('_', array_filter($parts)), 120, '');

        return $name . '.pdf';
    }

    /**
     * Collect everything the report renders, applying the same filter
     * constraints used by the explorer.
     */
    public function collect(array $filters, string $range, int $limit = 300, ?array $period = null): array
    {
        $period ??= $this->resolveRange($range);

        $constraint = $this->annotations->uidConstraint(
            $filters['status'] ?? null,
            isset($filters['label']) && $filters['label'] !== null ? (int) $filters['label'] : null
        );

        $queryFilters = $filters;
        $queryFilters['from'] = $period['from_iso'] ?? $filters['from'] ?? 'now-24h';
        $queryFilters['to'] = $period['to_iso'];

        if ($constraint !== null) {
            if (array_key_exists('include', $constraint)) {
                $queryFilters['uid_include'] = $constraint['include'];
            }
            if (array_key_exists('exclude', $constraint)) {
                $queryFilters['uid_exclude'] = $constraint['exclude'];
            }
        }

        $aggregates = $this->alerts->reportAggregations($queryFilters);
        $rows = $this->alerts->searchText($queryFilters, 1, $limit);
        $rows['items'] = $this->annotations->attach($rows['items']);

        return [
            'total' => $aggregates['total'],
            'severity' => $aggregates['severity'],
            'agents' => $aggregates['agents'],
            'groups' => $aggregates['groups'],
            'rules' => $aggregates['rules'],
            'decoders' => $aggregates['decoders'],
            'timeline' => $aggregates['timeline'],
            'rows' => $rows['items'],
            'shown' => count($rows['items']),
            'limit' => $limit,
            'truncated' => $aggregates['total'] > count($rows['items']),
        ];
    }
}
