<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\OpenSearch\AgentRepository;
use App\Services\ReportService;
use App\Support\ReportRange;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use RuntimeException;

class AgentReportController extends Controller
{
    /**
     * Sections a single agent report can contain, keyed by the checkbox value.
     */
    public const SECTIONS = [
        'alerts' => 'Security alerts',
        'vulnerabilities' => 'Vulnerabilities',
        'sca' => 'SCA checks',
    ];

    public function __construct(
        private AgentRepository $agents,
        private ReportService $reports,
    ) {}

    /**
     * One PDF covering a single endpoint: alert activity, vulnerability
     * findings and configuration assessment for a chosen period.
     */
    public function agent(Request $request, string $id)
    {
        $agent = $this->findAgent($id);

        $period = ReportRange::resolve(
            $request->query('range', '30d'),
            $request->query('from'),
            $request->query('to')
        );

        $sections = collect((array) $request->query('sections', array_keys(self::SECTIONS)))
            ->filter(fn ($key) => is_string($key) && array_key_exists($key, self::SECTIONS))
            ->values()
            ->all();

        if ($sections === []) {
            $sections = array_keys(self::SECTIONS);
        }

        $data = ['alerts' => null, 'vulnerabilities' => null, 'sca' => null];

        try {
            if (in_array('alerts', $sections, true)) {
                $data['alerts'] = $this->reports->collect([
                    // "All time" has no lower bound; index retention decides the start.
                    'agent' => $agent['name'],
                    'from' => $period['from_iso'] ?? 'now-3650d',
                    'to' => $period['to_iso'],
                ], $period['key'], 200, $period);
            }

            if (in_array('vulnerabilities', $sections, true)) {
                $filters = [
                    'agent' => $agent['name'],
                    'from' => $period['from_iso'],
                    'to' => $period['to_iso'],
                ];

                $data['vulnerabilities'] = [
                    'facets' => $this->agents->vulnerabilityFacets($agent['name'], $filters),
                    'items' => $this->agents->vulnerabilityFindings($filters, 200),
                ];
            }

            if (in_array('sca', $sections, true)) {
                $filters = [
                    'from' => $period['from_iso'],
                    'to' => $period['to_iso'],
                ];

                $data['sca'] = [
                    'score' => $this->agents->scaScore($agent['name'], $period['from_iso'], $period['to_iso']),
                    'facets' => $this->agents->scaFacets($agent['name'], $filters),
                    'items' => $this->agents->scaCheckResults($agent['name'], $filters, 200),
                ];
            }
        } catch (RuntimeException $e) {
            return back()->with('error', 'Agent report could not be generated: ' . $e->getMessage());
        }

        $scope = implode(' · ', array_map(fn ($key) => self::SECTIONS[$key], $sections));

        $meta = $this->meta('Agent Security Report', [
            ['label' => 'Agent', 'value' => $agent['name']],
            ['label' => 'Sections', 'value' => $scope],
        ], $period, 'agent-report', $agent['name']);

        AuditLog::record('report.exported', 'agent:' . $agent['name'], [
            'sections' => $sections,
            'range' => $period['key'],
            'from' => $period['from_iso'],
            'to' => $period['to_iso'],
        ]);

        return $this->pdf('reports.agent', [
            'meta' => $meta,
            'agent' => $agent,
            'period' => $period,
            'sections' => $sections,
            'data' => $data,
            'bands' => config('opensearch.severity_bands'),
            'generatedBy' => $request->user(),
        ], $meta['filename']);
    }

    /**
     * Export the vulnerability list for one agent, honouring the filters that
     * are active on the page.
     */
    public function vulnerabilities(Request $request, string $id)
    {
        $agent = $this->findAgent($id);

        $period = ReportRange::resolve(
            $request->query('range', 'all'),
            $request->query('from'),
            $request->query('to')
        );

        $filters = [
            'agent' => $agent['name'],
            'severity' => $request->query('severity'),
            'category' => $request->query('category'),
            'q' => trim((string) $request->query('q', '')),
        ];

        if ($period['key'] !== 'all') {
            $filters['from'] = $period['from_iso'];
            $filters['to'] = $period['to_iso'];
        }

        try {
            $facets = $this->agents->vulnerabilityFacets($agent['name'], $filters);
            $items = $this->agents->vulnerabilityFindings($filters, 400);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Vulnerability report could not be generated: ' . $e->getMessage());
        }

        $meta = $this->meta('Vulnerability Report', $this->vulnerabilityDescriptors($agent['name'], $filters), $period, 'vulnerability-report', $agent['name']);

        AuditLog::record('report.exported', 'agent:' . $agent['name'] . ':vulnerabilities', [
            'range' => $period['key'],
            'filters' => array_filter($filters),
            'total' => $facets['total'],
        ]);

        return $this->pdf('reports.vulnerabilities', [
            'meta' => $meta,
            'agent' => $agent,
            'period' => $period,
            'filters' => $filters,
            'facets' => $facets,
            'items' => $items,
            'generatedBy' => $request->user(),
        ], $meta['filename']);
    }

    /**
     * Export the SCA check list for one agent, honouring result/policy/search
     * and the selected period.
     */
    public function sca(Request $request, string $id)
    {
        $agent = $this->findAgent($id);

        $period = ReportRange::resolve(
            $request->query('range', '30d'),
            $request->query('from'),
            $request->query('to')
        );

        $filters = [
            'result' => $request->query('result'),
            'policy' => $request->query('policy'),
            'q' => trim((string) $request->query('q', '')),
        ];

        if ($period['key'] !== 'all') {
            $filters['from'] = $period['from_iso'];
            $filters['to'] = $period['to_iso'];
        }

        try {
            $facets = $this->agents->scaFacets($agent['name'], $filters);
            $items = $this->agents->scaCheckResults($agent['name'], $filters, 400);
            $score = $this->agents->scaScore($agent['name'], $period['from_iso'], $period['to_iso']);
        } catch (RuntimeException $e) {
            return back()->with('error', 'SCA report could not be generated: ' . $e->getMessage());
        }

        $meta = $this->meta('SCA Compliance Report', $this->scaDescriptors($agent['name'], $filters), $period, 'sca-report', $agent['name']);

        AuditLog::record('report.exported', 'agent:' . $agent['name'] . ':sca', [
            'range' => $period['key'],
            'filters' => array_filter($filters),
            'total' => $facets['total'],
        ]);

        return $this->pdf('reports.sca', [
            'meta' => $meta,
            'agent' => $agent,
            'period' => $period,
            'filters' => $filters,
            'facets' => $facets,
            'score' => $score,
            'items' => $items,
            'generatedBy' => $request->user(),
        ], $meta['filename']);
    }

    private function findAgent(string $id): array
    {
        $agent = collect($this->agents->latestStatus())->firstWhere('id', $id);

        if ($agent === null) {
            abort(404, 'Agent not found in the last 30 minutes of monitoring data.');
        }

        return $agent;
    }

    private function vulnerabilityDescriptors(string $agentName, array $filters): array
    {
        $descriptors = [['label' => 'Agent', 'value' => $agentName]];

        if (! empty($filters['severity'])) {
            $descriptors[] = ['label' => 'Severity', 'value' => $filters['severity']];
        }
        if (! empty($filters['category'])) {
            $descriptors[] = ['label' => 'Category', 'value' => $filters['category']];
        }
        if (! empty($filters['q'])) {
            $descriptors[] = ['label' => 'Search', 'value' => $filters['q']];
        }

        return $descriptors;
    }

    private function scaDescriptors(string $agentName, array $filters): array
    {
        $descriptors = [['label' => 'Agent', 'value' => $agentName]];

        if (! empty($filters['result'])) {
            $descriptors[] = ['label' => 'Result', 'value' => ucfirst((string) $filters['result'])];
        }
        if (! empty($filters['policy'])) {
            $descriptors[] = ['label' => 'Policy', 'value' => $filters['policy']];
        }
        if (! empty($filters['q'])) {
            $descriptors[] = ['label' => 'Search', 'value' => $filters['q']];
        }

        return $descriptors;
    }

    private function meta(string $title, array $descriptors, array $period, string $prefix, string $agentName): array
    {
        $parts = [$prefix, Str::slug($agentName, '-')];

        foreach ($descriptors as $descriptor) {
            if ($descriptor['label'] === 'Agent') {
                continue;
            }
            $parts[] = Str::slug($descriptor['label'] . '-' . $descriptor['value'], '-');
        }

        $parts[] = $period['from']?->format('Ymd') ?? 'start';
        $parts[] = $period['to']->format('Ymd');

        return [
            'title' => $title,
            'scope' => implode(' · ', array_map(fn ($d) => $d['label'] . ': ' . $d['value'], $descriptors)),
            'descriptors' => $descriptors,
            'period' => $period,
            'period_label' => ($period['from']?->translatedFormat('d F Y H:i') ?? 'start of retention')
                . ' — ' . $period['to']->translatedFormat('d F Y H:i')
                . ' (' . $period['label'] . ')',
            'filename' => Str::limit(implode('_', array_filter($parts)), 120, '') . '.pdf',
            'generated_at' => Carbon::now(),
        ];
    }

    private function pdf(string $view, array $data, string $filename)
    {
        return Pdf::loadView($view, $data)
            ->setPaper('a4', 'portrait')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->download($filename);
    }
}
