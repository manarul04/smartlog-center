<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Services\KbCatalogService;
use App\Services\OpenSearch\AgentRepository;
use App\Services\OpenSearch\AlertRepository;
use App\Support\ReportRange;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class AgentController extends Controller
{
    public function __construct(
        private AgentRepository $agents,
        private AlertRepository $alerts,
        private KbCatalogService $kbCatalog,
    ) {}

    public function index(): View
    {
        $warnings = [];

        $status = [];
        try {
            $status = $this->agents->latestStatus();
        } catch (RuntimeException $e) {
            $warnings[] = 'Agent status: ' . $e->getMessage();
        }

        $activity = [];
        try {
            $activity = $this->alerts->agentActivity('now-24h');
        } catch (RuntimeException $e) {
            $warnings[] = 'Agent activity: ' . $e->getMessage();
        }

        $vulns = ['available' => false, 'total' => 0, 'severity' => [], 'agents' => [], 'cves' => [], 'by_agent_severity' => []];
        try {
            $vulns = $this->agents->vulnerabilities();
        } catch (RuntimeException $e) {
            $warnings[] = 'Vulnerabilities: ' . $e->getMessage();
        }

        $sca = [];
        try {
            $sca = $this->agents->scaSummary();
        } catch (RuntimeException $e) {
            $warnings[] = 'SCA: ' . $e->getMessage();
        }

        // Merge monitoring state with 24h alert activity so the table answers
        // "who is online and how noisy are they" in one pass.
        $activityByName = collect($activity)->keyBy('name');
        $rows = collect($status)->map(function ($agent) use ($activityByName) {
            $act = $activityByName->get($agent['name']);

            return array_merge($agent, [
                'events_24h' => $act['count'] ?? 0,
                'max_level_24h' => $act['max_level'] ?? 0,
                'last_event' => $act['last_seen'] ?? null,
            ]);
        })->all();

        return view('agents.index', [
            'rows' => $rows,
            'activity' => $activity,
            'vulns' => $vulns,
            'sca' => $sca,
            'warnings' => $warnings,
        ]);
    }

    public function show(string $id): View
    {
        $status = collect($this->agents->latestStatus())->firstWhere('id', $id);
        $history = $this->agents->statusHistory($id, 'now-7d');
        $activity = $this->alerts->agentActivity('now-24h');

        $agentActivity = collect($activity)->firstWhere('name', $status['name'] ?? '');

        $vulnFacets = ['total' => 0, 'severity' => []];
        $scaFacets = ['total' => 0, 'result' => [], 'policies' => []];
        $scaRows = [];
        $inventory = ['packages' => ['items' => [], 'total' => 0, 'types' => []], 'hotfixes' => ['items' => [], 'total' => 0]];
        $hardware = ['available' => false];
        $kbTitles = [];
        $kbPending = [];
        $kbContext = [];

        if ($status !== null) {
            try {
                $vulnFacets = $this->agents->vulnerabilityFacets($status['name']);
            } catch (RuntimeException) {
                // Facets are supplementary; the page still renders.
            }

            try {
                $scaFacets = $this->agents->scaFacets($status['name'], ['from' => 'now-30d']);
                $scaRows = collect($this->agents->scaSummary())->firstWhere('agent', $status['name']);
            } catch (RuntimeException) {
                // Same here.
            }

            try {
                $inventory = $this->agents->inventory($status['name']);
            } catch (RuntimeException) {
                // Inventory may not be enabled on every agent.
            }

            try {
                $hardware = $this->agents->hardware($status['name']);

                // Hostname, OS platform and architecture live in the system
                // inventory, not the hardware one, so they are merged here.
                $profile = $this->agents->systemProfile($status['name']);
                $hardware = array_merge($hardware, [
                    'hostname' => $profile['hostname'] ?? null,
                    'os_name' => $profile['os_name'] ?? null,
                    'os_platform' => $profile['os_platform'] ?? null,
                    'arch' => $profile['arch'] ?? null,
                ]);
            } catch (RuntimeException) {
                // Syscollector may not ship the hardware module.
            }

            // Titles come from the local cache only: the page must never wait
            // on Microsoft. Anything still pending is resolved in the
            // background by kbTitles() once the page is already on screen.
            if (! empty($inventory['hotfixes']['items'])) {
                try {
                    $kbContext = $this->kbContext($status['name']);
                    $kbTitles = $this->kbCatalog->titles($inventory['hotfixes']['items'], $kbContext);
                    $kbPending = array_keys(array_filter(
                        $kbTitles,
                        fn ($t) => $t['status'] === 'pending'
                    ));
                } catch (\Throwable) {
                    // The KB table degrades to bare codes.
                }
            }
        }

        return view('agents.show', [
            'agent' => $status,
            'history' => $history,
            'activity' => $agentActivity,
            'id' => $id,
            'vulnFacets' => $vulnFacets,
            'scaFacets' => $scaFacets,
            'scaRow' => $scaRows,
            'inventory' => $inventory,
            'hardware' => $hardware,
            'kbTitles' => $kbTitles,
            'kbPending' => $kbPending,
        ]);
    }

    /**
     * Resolve a few missing KB titles, then hand the new rows back to the page.
     *
     * Called from the agent page after render, in small batches, so the visitor
     * pays for the lookups one screen at a time instead of on every load.
     */
    public function kbTitles(string $id): JsonResponse
    {
        $status = collect($this->agents->latestStatus())->firstWhere('id', $id);

        if ($status === null) {
            return response()->json(['error' => 'Agent not found'], 404);
        }

        $inventory = $this->agents->inventory($status['name'], 500);
        $kbs = $inventory['hotfixes']['items'] ?? [];

        if ($kbs === []) {
            return response()->json(['titles' => [], 'remaining' => 0]);
        }

        $context = $this->kbContext($status['name']);
        $titles = $this->kbCatalog->resolve($kbs, (int) config('kb.batch', 5), $context);

        return response()->json([
            'titles' => $titles,
            'remaining' => count($this->kbCatalog->pending($kbs)),
        ]);
    }

    /**
     * Host OS/arch plus the installed Windows Security version, so the catalog
     * entry chosen for a KB matches the machine that reported it.
     */
    private function kbContext(string $agentName): array
    {
        $profile = $this->agents->systemProfile($agentName);

        return [
            'os_name' => $profile['os_name'] ?? null,
            'arch' => $profile['arch'] ?? null,
            'package_version' => $this->agents->packageVersion($agentName, 'Windows Security'),
        ];
    }

    /**
     * All agents affected by one CVE (Top CVEs drill-down).
     */
    public function cveDetail(string $cve)
    {
        $result = $this->agents->vulnerabilityByCve($cve);

        return view('agents.cve', [
            'cve' => $cve,
            'result' => $result,
        ]);
    }

    /**
     * Vulnerability findings for one agent, filterable by severity.
     */
    public function vulnerabilities(Request $request, string $id): View
    {
        $status = collect($this->agents->latestStatus())->firstWhere('id', $id);

        if ($status === null) {
            abort(404, 'Agent not found in the last 30 minutes of monitoring data.');
        }

        $filters = [
            'agent' => $status['name'],
            'severity' => $request->query('severity'),
            'category' => $request->query('category'),
            'q' => trim((string) $request->query('q', '')),
        ];

        $range = ReportRange::resolve(
            $request->query('range', 'all'),
            $request->query('from'),
            $request->query('to')
        );

        if ($range['key'] !== 'all') {
            $filters['from'] = $range['from_iso'];
            $filters['to'] = $range['to_iso'];
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;

        $error = null;
        $result = ['available' => true, 'items' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
        $facets = ['total' => 0, 'severity' => [], 'category' => [], 'packages' => []];

        try {
            $result = $this->agents->vulnerabilityList($filters, $page, $perPage);
            $facets = $this->agents->vulnerabilityFacets($status['name'], $filters);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        // Attach any existing report for each finding so rows can show status
        // and the drawer can offer "View report" instead of "Create report".
        $refKeys = [];
        foreach ($result['items'] as $item) {
            $src = $item['source'];
            $refKeys[] = implode('|', [
                $src['agent']['name'] ?? '',
                $src['vulnerability']['id'] ?? '',
                $src['package']['name'] ?? '',
            ]);
        }

        $reportsByKey = [];
        if ($refKeys !== []) {
            $reportItems = TicketItem::with(['ticket.assignee:id,name'])
                ->where('kind', TicketItem::KIND_VULNERABILITY)
                ->whereIn('ref_key', array_values(array_unique($refKeys)))
                ->get();

            foreach ($reportItems as $reportItem) {
                $reportsByKey[$reportItem->ref_key] = $reportItem->ticket;
            }
        }

        foreach ($result['items'] as &$item) {
            $src = $item['source'];
            $key = implode('|', [
                $src['agent']['name'] ?? '',
                $src['vulnerability']['id'] ?? '',
                $src['package']['name'] ?? '',
            ]);
            $item['review'] = $reportsByKey[$key] ?? null;
        }
        unset($item);

        return view('agents.vulnerabilities', [
            'agent' => $status,
            'result' => $result,
            'facets' => $facets,
            'filters' => $filters,
            'range' => $range,
            'error' => $error,
            'priorities' => Ticket::PRIORITIES,
            'users' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * SCA check results for one agent with handling state.
     */
    public function sca(Request $request, string $id): View
    {
        $status = collect($this->agents->latestStatus())->firstWhere('id', $id);

        if ($status === null) {
            abort(404, 'Agent not found in the last 30 minutes of monitoring data.');
        }

        $range = ReportRange::resolve(
            $request->query('range', '30d'),
            $request->query('from'),
            $request->query('to')
        );

        $filters = [
            'result' => $request->query('result'),
            'policy' => $request->query('policy'),
            'q' => trim((string) $request->query('q', '')),
        ];

        if ($range['key'] !== 'all') {
            $filters['from'] = $range['from_iso'];
            $filters['to'] = $range['to_iso'];
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = 50;

        $error = null;
        $result = ['available' => true, 'items' => [], 'total' => 0, 'page' => 1, 'pages' => 0];
        $facets = ['total' => 0, 'result' => [], 'policies' => []];

        try {
            $result = $this->agents->scaChecks($status['name'], $filters, $page, $perPage);
            $facets = $this->agents->scaFacets($status['name'], $filters);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        // Attach any existing report per check so the modal can offer
        // "View report" instead of "Create report".
        $refKeys = [];
        foreach ($result['items'] as $item) {
            $refKeys[] = implode('|', [
                $status['name'],
                $item['policy'] ?? '',
                $item['check_id'] ?? '',
            ]);
        }

        $reportsByKey = [];
        if ($refKeys !== []) {
            $reportItems = TicketItem::with(['ticket.assignee:id,name'])
                ->where('kind', TicketItem::KIND_SCA)
                ->whereIn('ref_key', array_values(array_unique($refKeys)))
                ->get();

            foreach ($reportItems as $reportItem) {
                $reportsByKey[$reportItem->ref_key] = $reportItem->ticket;
            }
        }

        foreach ($result['items'] as &$item) {
            $key = implode('|', [
                $status['name'],
                $item['policy'] ?? '',
                $item['check_id'] ?? '',
            ]);
            $item['state'] = $reportsByKey[$key] ?? null;
        }
        unset($item);

        return view('agents.sca', [
            'agent' => $status,
            'result' => $result,
            'facets' => $facets,
            'filters' => $filters,
            'range' => $range,
            'error' => $error,
            'states' => Ticket::STATUSES,
            'users' => User::orderBy('name')->get(['id', 'name']),
            // The score card must describe the selected period, not a fixed window.
            'summary' => $this->agents->scaScore($status['name'], $range['from_iso'], $range['to_iso']),
        ]);
    }
}
