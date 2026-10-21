<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\User;
use App\Services\OpenSearch\AlertRepository;
use App\Services\TicketService;
use App\Support\Severity;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class AlertGroupController extends Controller
{
    public function __construct(
        private AlertRepository $alerts,
        private TicketService $reports,
    ) {}

    /**
     * Agent → rule tree for the selected window. Everything comes from
     * OpenSearch; the page never copies alerts into Postgres.
     */
    public function index(Request $request)
    {
        $range = $this->range($request->query('range'));

        $filters = [
            'from' => $range['from_iso'],
            'agent' => $request->query('agent') ?: null,
            'group' => $request->query('group') ?: null,
            'level_min' => $request->query('level_min') ?: null,
            'q' => trim((string) $request->query('q', '')),
        ];

        $tree = null;
        $error = null;

        try {
            $tree = $this->alerts->agentRuleGroups($filters);
        } catch (RuntimeException $e) {
            $error = $e->getMessage();
        }

        $options = ['agents' => [], 'groups' => []];
        try {
            $options = $this->alerts->filterOptions($range['from_iso']);
        } catch (RuntimeException) {
            // Filters degrade to free text when the aggregation fails.
        }

        return view('alert-groups.index', [
            'range' => $range,
            'ranges' => $this->ranges(),
            'filters' => $filters,
            'tree' => $tree,
            'error' => $error,
            'options' => $options,
            'bands' => Severity::bands(),
            'priorities' => Ticket::PRIORITIES,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Alerts behind one agent + rule pair, loaded when the row is expanded.
     */
    public function alerts(Request $request)
    {
        $data = $request->validate([
            'agent' => ['required', 'string', 'max:120'],
            'rule_id' => ['required', 'string', 'max:40'],
            'range' => ['nullable', 'string'],
            'limit' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $range = $this->range($data['range'] ?? null);

        try {
            $items = $this->alerts->ruleAlerts(
                $data['agent'],
                $data['rule_id'],
                $range['from_iso'],
                null,
                (int) ($data['limit'] ?? 25)
            );
        } catch (RuntimeException $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 503);
        }

        return response()->json([
            'ok' => true,
            'items' => collect($items)->map(fn ($item) => [
                'index' => $item['index'],
                'id' => $item['id'],
                'timestamp' => $item['source']['timestamp'] ?? null,
                'level' => (int) ($item['source']['rule']['level'] ?? 0),
                'description' => $item['source']['rule']['description'] ?? null,
                'agent' => $item['source']['agent']['name'] ?? null,
                'agent_ip' => $item['source']['agent']['ip'] ?? null,
                'decoder' => $item['source']['decoder']['name'] ?? null,
                'location' => $item['source']['location'] ?? null,
            ])->all(),
        ]);
    }

    /**
     * Create an incident report that tracks the whole agent + rule group inside
     * the window the analyst is looking at. The alert total stays live, so the
     * report always agrees with a fresh OpenSearch query.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'agent' => ['required', 'string', 'max:120'],
            'rule_id' => ['required', 'string', 'max:40'],
            'rule_description' => ['nullable', 'string', 'max:255'],
            'range' => ['required', 'string'],
            'level' => ['nullable', 'integer', 'between:0,15'],
            'agents' => ['nullable', 'array'],
            'agents.*' => ['string', 'max:120'],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $range = $this->range($data['range']);

        if ($range['from_iso'] === null) {
            return back()->with('error', 'Choose a bounded date range before creating a report.');
        }

        // An open report for the same group and window is almost always a double
        // submit; point the analyst at it instead of duplicating the work.
        $existing = Ticket::query()
            ->where('type', 'incident')
            ->where('group_rule_id', $data['rule_id'])
            ->where('group_agent', $data['agent'])
            ->where('group_window_from', $range['from'])
            ->open()
            ->first();

        if ($existing !== null) {
            return redirect()->route('reports.show', $existing)
                ->with('status', 'This group already has open report '.$existing->reference.'.');
        }

        try {
            $group = $this->alerts->countGroupedAlerts(
                array_values(array_unique(array_filter((array) ($data['agents'] ?? [$data['agent']])))),
                $data['rule_id'],
                $range['from_iso']
            );
            $count = (int) ($group['total'] ?? 0);
        } catch (RuntimeException $e) {
            return back()->with('error', 'Could not count the alerts in this group: '.$e->getMessage());
        }

        $agents = array_values(array_unique(array_filter((array) ($data['agents'] ?? []))));

        if ($agents === []) {
            $agents = [$data['agent']];
        }

        $report = $this->reports->createFromGroup([
            'agent' => $data['agent'],
            'agents' => $agents,
            'rule_id' => $data['rule_id'],
            'rule_description' => $data['rule_description'] ?? null,
            'rule_level' => $data['level'] ?? null,
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'assignee_id' => $data['assignee_id'] ?? null,
            'window_from' => $range['from'],
            'window_to' => $range['to'],
        ]);

        AuditLog::record('report.created', $report->reference, [
            'type' => 'incident',
            'source' => 'alert-groups',
            'agent' => $data['agent'],
            'agents' => $agents,
            'rule' => $data['rule_id'],
            'alerts' => $count,
            'window_from' => $range['from']->toIso8601String(),
            'window_to' => $range['to']?->toIso8601String(),
        ]);

        return redirect()->route('reports.show', $report)
            ->with('status', "Report {$report->reference} created for rule {$data['rule_id']} on {$data['agent']} ({$count} alerts).");
    }

    /**
     * @return array{key: string, label: string, from: \Illuminate\Support\Carbon, to: ?\Illuminate\Support\Carbon, from_iso: ?string, to_iso: ?string}
     */
    private function range(?string $key): array
    {
        $ranges = $this->ranges();
        $key = isset($ranges[$key]) ? $key : '24h';
        $meta = $ranges[$key];

        $to = \Illuminate\Support\Carbon::now();
        $from = $to->copy()->subMinutes($meta['minutes']);

        return [
            'key' => $key,
            'label' => $meta['label'],
            'from' => $from,
            'to' => $to,
            'from_iso' => $from->toIso8601String(),
            'to_iso' => $to->toIso8601String(),
        ];
    }

    /**
     * Presets in minutes, so the same list drives the query window, the report
     * window and the label shown on the page.
     */
    private function ranges(): array
    {
        return [
            '15m' => ['label' => 'Last 15 minutes', 'minutes' => 15],
            '1h' => ['label' => 'Last 1 hour', 'minutes' => 60],
            '24h' => ['label' => 'Last 24 hours', 'minutes' => 60 * 24],
            '7d' => ['label' => 'Last 7 days', 'minutes' => 60 * 24 * 7],
            '30d' => ['label' => 'Last 30 days', 'minutes' => 60 * 24 * 30],
            '90d' => ['label' => 'Last 90 days', 'minutes' => 60 * 24 * 90],
        ];
    }
}
