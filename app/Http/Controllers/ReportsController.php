<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Ticket;
use App\Models\TicketItem;
use App\Models\User;
use App\Services\OpenSearch\AgentRepository;
use App\Services\OpenSearch\AlertRepository;
use App\Services\TicketService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class ReportsController extends Controller
{
    public function __construct(
        private TicketService $reports,
        private AlertRepository $alerts,
        private AgentRepository $agents,
    ) {}

    /**
     * Central queue: every report type in one list.
     */
    public function index(Request $request)
    {
        $query = Ticket::with(['assignee:id,name', 'creator:id,name'])
            ->withCount('items')
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->orderByDesc('created_at');

        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }

        if ($request->filled('status')) {
            if ($request->query('status') === 'open_all') {
                $query->open();
            } else {
                $query->where('status', $request->query('status'));
            }
        }

        if ($request->filled('assignee_id')) {
            if ($request->query('assignee_id') === 'me') {
                $query->where('assignee_id', auth()->id());
            } elseif ($request->query('assignee_id') === 'unassigned') {
                $query->whereNull('assignee_id');
            } else {
                $query->where('assignee_id', $request->query('assignee_id'));
            }
        }

        if ($request->filled('q')) {
            $term = '%' . $request->query('q') . '%';
            $query->where(function ($q) use ($term) {
                $q->where('reference', 'like', $term)
                    ->orWhere('title', 'like', $term)
                    ->orWhere('description', 'like', $term)
                    ->orWhere('group_agent', 'like', $term)
                    ->orWhere('group_rule_id', 'like', $term);
            });
        }

        $reports = $query->paginate(25)->withQueryString();

        $stats = [
            'total' => Ticket::count(),
            'open' => Ticket::open()->count(),
            'unassigned' => Ticket::open()->whereNull('assignee_id')->count(),
            'mine' => Ticket::where('assignee_id', auth()->id())->open()->count(),
            'incidents' => Ticket::where('type', 'incident')->count(),
            'vulnerabilities' => Ticket::where('type', 'vulnerability')->count(),
            'sca' => Ticket::where('type', 'sca')->count(),
        ];

        return view('reports.index', [
            'reports' => $reports,
            'stats' => $stats,
            'statuses' => Ticket::STATUSES,
            'types' => Ticket::TYPES,
            'priorities' => Ticket::PRIORITIES,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Full report page with type-specific sections.
     */
    public function show(Ticket $report)
    {
        $report->load(['assignee:id,name', 'creator:id,name', 'events.user:id,name']);

        $items = $report->items()->orderByDesc('occurred_at')->get();

        $group = null;
        $groupAlerts = [];

        if ($report->isGrouped()) {
            try {
                $agents = $report->groupedAgents();

                $group = $this->alerts->countGroupedAlerts(
                    $agents,
                    $report->group_rule_id,
                    $report->group_window_from?->toIso8601String() ?? 'now-30d',
                    $report->group_window_to?->toIso8601String()
                );

                $groupAlerts = $this->alerts->groupedAlerts(
                    $agents,
                    $report->group_rule_id,
                    $report->group_window_from?->toIso8601String() ?? 'now-30d',
                    $report->group_window_to?->toIso8601String(),
                    50
                );
            } catch (RuntimeException $e) {
                $group = ['error' => $e->getMessage()];
            }
        }

        return view('reports.show', [
            'report' => $report,
            'items' => $items,
            'group' => $group,
            'groupAlerts' => $groupAlerts,
            'statuses' => Ticket::STATUSES,
            'types' => Ticket::TYPES,
            'priorities' => Ticket::PRIORITIES,
            'users' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Create an incident report from an alert.
     *
     * The counting window is optional: leave both dates empty and the report
     * counts live from the alert's own timestamp. The agent list is optional
     * too — empty means "just this alert's agent", several means the report
     * tracks the same rule across all selected endpoints.
     */
    public function storeFromAlert(Request $request)
    {
        $data = $request->validate([
            'index' => ['required', 'string'],
            'id' => ['required', 'string'],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'window_from' => ['nullable', 'date'],
            'window_to' => ['nullable', 'date', 'after_or_equal:window_from'],
            'agents' => ['nullable', 'array'],
            'agents.*' => ['string', 'max:120'],
        ]);

        $alert = $this->alerts->find($data['index'], $data['id']);

        if ($alert === null) {
            return back()->with('error', 'Alert could not be loaded from OpenSearch.');
        }

        $agent = $alert['agent']['name'] ?? null;
        $ruleId = $alert['rule']['id'] ?? null;

        if ($agent === null || $ruleId === null) {
            return back()->with('error', 'Alert is missing agent or rule information; cannot group it.');
        }

        $agents = array_values(array_unique(array_filter((array) ($data['agents'] ?? []))));

        if ($agents === []) {
            $agents = [$agent];
        }

        $existing = $this->reports->findExisting(TicketItem::KIND_ALERT, (string) ($alert['id'] ?? ''));
        if ($existing !== null) {
            return redirect()->route('reports.show', $existing)
                ->with('status', 'This alert already has report ' . $existing->reference . '.');
        }

        $report = $this->reports->createFromAlert($alert, [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'severity' => $this->severityFromLevel((int) ($alert['rule']['level'] ?? 0)),
            'assignee_id' => $data['assignee_id'] ?? null,
            'window_from' => $data['window_from'] ?? null,
            'window_to' => $data['window_to'] ?? null,
            'agents' => $agents,
        ]);

        AuditLog::record('report.created', $report->reference, [
            'type' => $report->type,
            'agent' => $agent,
            'agents' => $agents,
            'rule' => $ruleId,
        ]);

        $scope = count($agents) > 1
            ? count($agents) . ' agents'
            : $agent;

        return redirect()->route('reports.show', $report)
            ->with('status', "Report {$report->reference} created for rule {$ruleId} on {$scope}.");
    }

    /**
     * Create a vulnerability report from a finding.
     */
    public function storeFromVulnerability(Request $request)
    {
        $data = $request->validate([
            'agent_name' => ['required', 'string', 'max:120'],
            'cve_id' => ['required', 'string', 'max:40'],
            'package_name' => ['nullable', 'string', 'max:200'],
            'package_version' => ['nullable', 'string', 'max:80'],
            'severity' => ['nullable', 'string', 'max:20'],
            'cvss' => ['nullable', 'numeric', 'between:0,10'],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        $refKey = implode('|', [
            $data['agent_name'],
            $data['cve_id'],
            $data['package_name'] ?? '',
        ]);

        $existing = $this->reports->findExisting(TicketItem::KIND_VULNERABILITY, $refKey);
        if ($existing !== null) {
            return redirect()->route('reports.show', $existing)
                ->with('status', 'This finding already has report ' . $existing->reference . '.');
        }

        $report = $this->reports->createFromVulnerability($data, [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'assignee_id' => $data['assignee_id'] ?? null,
            'due_at' => $data['due_at'] ?? null,
        ]);

        AuditLog::record('report.created', $report->reference, [
            'type' => 'vulnerability',
            'cve' => $data['cve_id'],
            'agent' => $data['agent_name'],
        ]);

        return redirect()->route('reports.show', $report)
            ->with('status', "Report {$report->reference} created for {$data['cve_id']} on {$data['agent_name']}.");
    }

    /**
     * Create an SCA report from a failed check.
     */
    public function storeFromSca(Request $request)
    {
        $data = $request->validate([
            'agent_name' => ['required', 'string', 'max:120'],
            'policy' => ['required', 'string', 'max:200'],
            'check_id' => ['required', 'string', 'max:40'],
            'title' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'due_at' => ['nullable', 'date'],
        ]);

        $refKey = implode('|', [$data['agent_name'], $data['policy'], $data['check_id']]);

        $existing = $this->reports->findExisting(TicketItem::KIND_SCA, $refKey);
        if ($existing !== null) {
            return redirect()->route('reports.show', $existing)
                ->with('status', 'This check already has report ' . $existing->reference . '.');
        }

        $report = $this->reports->createFromSca($data, [
            'title' => $data['title'] ?? null,
            'description' => $data['description'] ?? null,
            'priority' => $data['priority'],
            'assignee_id' => $data['assignee_id'] ?? null,
            'due_at' => $data['due_at'] ?? null,
        ]);

        AuditLog::record('report.created', $report->reference, [
            'type' => 'sca',
            'check' => $data['check_id'],
            'agent' => $data['agent_name'],
        ]);

        return redirect()->route('reports.show', $report)
            ->with('status', "Report {$report->reference} created for check {$data['check_id']}.");
    }

    public function update(Request $request, Ticket $report)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Ticket::statusKeys())],
            'priority' => ['required', Rule::in(array_keys(Ticket::PRIORITIES))],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'resolution' => ['nullable', 'string', 'max:4000'],
            'note' => ['nullable', 'string', 'max:4000'],
        ]);

        $report->title = $data['title'];
        $report->description = $data['description'] ?? null;
        $report->resolution = $data['resolution'] ?? null;
        $report->priority = $data['priority'];
        $report->assignee_id = $data['assignee_id'] ?? null;

        $this->reports->transition(
            $report,
            $data['status'],
            $data['note'] ?? null,
            $data['assignee_id'] ?? null
        );

        AuditLog::record('report.updated', $report->reference, ['status' => $report->status]);

        return back()->with('status', 'Report updated.');
    }

    /**
     * Re-verify a vulnerability report against the scanner.
     */
    public function verify(Ticket $report)
    {
        if ($report->type !== 'vulnerability') {
            return back()->with('error', 'Only vulnerability reports can be verified against the scanner.');
        }

        try {
            $this->reports->verifyVulnerability($report, function ($agent, $cve, $package) {
                return $this->agents->findingExists($agent, $cve, $package);
            });
        } catch (RuntimeException $e) {
            return back()->with('error', 'Verification failed: ' . $e->getMessage());
        }

        AuditLog::record('report.verified', $report->reference, [
            'still_present' => $report->still_present,
        ]);

        return back()->with('status', $report->still_present
            ? 'Verification: finding is still present in the scanner.'
            : 'Verification: finding is no longer reported by the scanner.');
    }

    public function refresh(Ticket $report)
    {
        if (! $report->isGrouped()) {
            return back()->with('error', 'This report does not track an alert group.');
        }

        $report->forceFill(['group_counted_at' => now()])->save();

        return back()->with('status', 'Alert count refreshed.');
    }

    public function addNote(Request $request, Ticket $report)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:4000']]);

        $this->reports->log($report, 'note', null, null, $data['note']);

        return back()->with('status', 'Note added.');
    }

    private function severityFromLevel(int $level): string
    {
        return match (true) {
            $level >= 12 => 'critical',
            $level >= 10 => 'high',
            $level >= 7 => 'medium',
            default => 'low',
        };
    }
}
