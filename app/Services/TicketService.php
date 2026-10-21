<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\TicketEvent;
use App\Models\TicketItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TicketService
{
    /**
     * Create an incident ticket from an alert, storing the grouping key so the
     * alert total is always counted live instead of copied.
     *
     * `agents` lets one report track the same rule across several endpoints;
     * `window_to` closes the counting window when the analyst wants a fixed
     * period instead of a running total.
     */
    public function createFromAlert(array $alert, array $data): Ticket
    {
        $agent = $alert['agent']['name'] ?? null;
        $ruleId = $alert['rule']['id'] ?? null;

        $agents = array_values(array_filter((array) ($data['agents'] ?? [])));

        if ($agents === [] && $agent !== null) {
            $agents = [$agent];
        }

        $from = ! empty($data['window_from'])
            ? Carbon::parse($data['window_from'])->startOfDay()
            : (isset($alert['timestamp']) ? Carbon::parse($alert['timestamp']) : Carbon::now());

        $to = ! empty($data['window_to'])
            ? Carbon::parse($data['window_to'])->endOfDay()
            : null;

        return DB::transaction(function () use ($alert, $data, $agent, $agents, $ruleId, $from, $to) {
            $ticket = Ticket::create([
                'reference' => Ticket::nextReference(),
                'type' => 'incident',
                'title' => $data['title'] ?: mb_substr(
                    (string) ($alert['rule']['description'] ?? 'Security incident'),
                    0,
                    200
                ),
                'description' => $data['description'] ?? null,
                'status' => $data['assignee_id'] ?? null ? 'assigned' : 'open',
                'priority' => $data['priority'] ?? 'high',
                'severity' => $data['severity'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'created_by' => auth()->id(),
                'group_agent' => $agent,
                'group_agents' => count($agents) > 1 ? $agents : null,
                'group_rule_id' => $ruleId !== null ? (string) $ruleId : null,
                'group_window_from' => $from,
                'group_window_to' => $to,
                'group_counted_at' => Carbon::now(),
                'meta' => [
                    'rule_description' => $alert['rule']['description'] ?? null,
                    'rule_level' => $alert['rule']['level'] ?? null,
                    'group_agents' => $agents,
                ],
            ]);

            $this->addItem($ticket, [
                'kind' => TicketItem::KIND_ALERT,
                'ref_key' => (string) ($alert['id'] ?? ''),
                'agent_name' => $agent,
                'title' => $alert['rule']['description'] ?? null,
                'severity' => $alert['rule']['level'] ?? null,
                'occurred_at' => isset($alert['timestamp']) ? Carbon::parse($alert['timestamp']) : null,
            ]);

            $this->log($ticket, 'created', null, $ticket->status, 'Reported from alert');

            return $ticket;
        });
    }

    /**
     * Create an incident ticket that tracks one rule firing on one or more
     * agents. Used by the Alert Groups page, where the analyst picks a whole
     * rule group instead of a single alert.
     */
    public function createFromGroup(array $group): Ticket
    {
        $agents = array_values(array_unique(array_filter((array) ($group['agents'] ?? []))));

        if ($agents === [] && ! empty($group['agent'])) {
            $agents = [$group['agent']];
        }

        $from = Carbon::parse($group['window_from']);

        $to = ! empty($group['window_to']) ? Carbon::parse($group['window_to']) : null;

        return DB::transaction(function () use ($group, $agents, $from, $to) {
            $ticket = Ticket::create([
                'reference' => Ticket::nextReference(),
                'type' => 'incident',
                'title' => $group['title'] ?: mb_substr(
                    (string) ($group['rule_description'] ?? 'Rule group'),
                    0,
                    200
                ),
                'description' => $group['description'] ?? null,
                'status' => ($group['assignee_id'] ?? null) ? 'assigned' : 'open',
                'priority' => $group['priority'] ?? 'high',
                'severity' => isset($group['rule_level'])
                    ? \App\Support\Severity::label((int) $group['rule_level'])
                    : null,
                'assignee_id' => $group['assignee_id'] ?? null,
                'created_by' => auth()->id(),
                'group_agent' => $group['agent'] ?? $agents[0] ?? null,
                'group_agents' => count($agents) > 1 ? $agents : null,
                'group_rule_id' => (string) $group['rule_id'],
                'group_window_from' => $from,
                'group_window_to' => $to,
                'group_counted_at' => Carbon::now(),
                'meta' => [
                    'rule_description' => $group['rule_description'] ?? null,
                    'rule_level' => $group['rule_level'] ?? null,
                    'group_agents' => $agents,
                    'source' => 'alert-groups',
                ],
            ]);

            $this->log($ticket, 'created', null, $ticket->status, 'Reported from alert group');

            return $ticket;
        });
    }

    /**
     * Create a vulnerability ticket for one finding.
     */
    public function createFromVulnerability(array $finding, array $data): Ticket
    {
        return DB::transaction(function () use ($finding, $data) {
            $ticket = Ticket::create([
                'reference' => Ticket::nextReference(),
                'type' => 'vulnerability',
                'title' => $data['title'] ?: mb_substr(
                    (string) ($finding['cve_id'] ?? 'Vulnerability review'),
                    0,
                    200
                ),
                'description' => $data['description'] ?? null,
                'status' => $data['assignee_id'] ?? null ? 'assigned' : 'open',
                'priority' => $data['priority'] ?? 'high',
                'severity' => $finding['severity'] ?? null,
                'assignee_id' => $data['assignee_id'] ?? null,
                'created_by' => auth()->id(),
                'due_at' => $data['due_at'] ?? null,
                'still_present' => true,
                'last_verified_at' => Carbon::now(),
                'meta' => [
                    'cve_id' => $finding['cve_id'] ?? null,
                    'cvss' => $finding['cvss'] ?? null,
                    'package_name' => $finding['package_name'] ?? null,
                    'package_version' => $finding['package_version'] ?? null,
                ],
            ]);

            $this->addItem($ticket, [
                'kind' => TicketItem::KIND_VULNERABILITY,
                'ref_key' => implode('|', [
                    $finding['agent_name'] ?? '',
                    $finding['cve_id'] ?? '',
                    $finding['package_name'] ?? '',
                ]),
                'agent_name' => $finding['agent_name'] ?? null,
                'title' => $finding['cve_id'] ?? null,
                'severity' => $finding['severity'] ?? null,
            ]);

            $this->log($ticket, 'created', null, $ticket->status, 'Created from vulnerability finding');

            return $ticket;
        });
    }

    /**
     * Create an SCA ticket for one failed check.
     */
    public function createFromSca(array $check, array $data): Ticket
    {
        return DB::transaction(function () use ($check, $data) {
            $ticket = Ticket::create([
                'reference' => Ticket::nextReference(),
                'type' => 'sca',
                'title' => $data['title'] ?: mb_substr(
                    (string) ($check['title'] ?? 'SCA remediation'),
                    0,
                    200
                ),
                'description' => $data['description'] ?? ($check['description'] ?? null),
                'status' => $data['assignee_id'] ?? null ? 'assigned' : 'open',
                'priority' => $data['priority'] ?? 'medium',
                'assignee_id' => $data['assignee_id'] ?? null,
                'created_by' => auth()->id(),
                'due_at' => $data['due_at'] ?? null,
                'meta' => [
                    'check_id' => $check['check_id'] ?? null,
                    'policy' => $check['policy'] ?? null,
                    'result' => $check['result'] ?? null,
                    'remediation' => $check['remediation'] ?? null,
                ],
            ]);

            $this->addItem($ticket, [
                'kind' => TicketItem::KIND_SCA,
                'ref_key' => implode('|', [
                    $check['agent_name'] ?? '',
                    $check['policy'] ?? '',
                    $check['check_id'] ?? '',
                ]),
                'agent_name' => $check['agent_name'] ?? null,
                'title' => $check['title'] ?? null,
                'severity' => $check['result'] ?? null,
            ]);

            $this->log($ticket, 'created', null, $ticket->status, 'Created from SCA check');

            return $ticket;
        });
    }

    public function addItem(Ticket $ticket, array $item): TicketItem
    {
        return TicketItem::firstOrCreate(
            [
                'ticket_id' => $ticket->id,
                'kind' => $item['kind'],
                'ref_key' => $item['ref_key'],
            ],
            [
                'agent_name' => $item['agent_name'] ?? null,
                'title' => $item['title'] ?? null,
                'severity' => $item['severity'] ?? null,
                'occurred_at' => $item['occurred_at'] ?? null,
                'meta' => $item['meta'] ?? null,
            ]
        );
    }

    /**
     * Apply a status change and record it in the timeline.
     */
    public function transition(Ticket $ticket, string $status, ?string $note = null, ?int $assigneeId = null): Ticket
    {
        $previous = $ticket->status;

        $ticket->status = $status;

        if ($assigneeId !== null) {
            $ticket->assignee_id = $assigneeId;
        }

        // Assigning work to somebody moves it out of the unreviewed state.
        if ($previous === Ticket::UNREVIEWED && $ticket->assignee_id !== null && $status === Ticket::UNREVIEWED) {
            $ticket->status = 'assigned';
        }

        $closing = in_array($ticket->status, Ticket::closingStatuses(), true);
        $ticket->resolved_at = $closing ? ($ticket->resolved_at ?? Carbon::now()) : null;
        $ticket->closed_at = $ticket->status === 'closed' ? ($ticket->closed_at ?? Carbon::now()) : null;

        $ticket->save();

        $this->log($ticket, 'status_changed', $previous, $ticket->status, $note);

        return $ticket;
    }

    public function log(Ticket $ticket, string $action, ?string $from = null, ?string $to = null, ?string $note = null): TicketEvent
    {
        return TicketEvent::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
        ]);
    }

    /**
     * Users that can be assigned work.
     */
    public function assignableUsers(): array
    {
        return User::where('is_active', true)->orderBy('name')->get(['id', 'name'])->all();
    }

    /**
     * Find the report that already covers a finding, if any. Uses the durable
     * item identity so a rescan or a second click cannot create duplicates.
     */
    public function findExisting(string $kind, string $refKey): ?Ticket
    {
        $item = TicketItem::where('kind', $kind)->where('ref_key', $refKey)->first();

        return $item?->ticket;
    }

    /**
     * Re-check a vulnerability report against the scanner and move it to
     * "verified" when the finding is gone.
     */
    public function verifyVulnerability(Ticket $ticket, callable $exists): Ticket
    {
        $meta = $ticket->meta ?? [];
        $cve = $meta['cve_id'] ?? null;
        $package = $meta['package_name'] ?? null;
        $agent = $ticket->items()
            ->where('kind', TicketItem::KIND_VULNERABILITY)
            ->value('agent_name');

        if ($cve === null || $agent === null) {
            throw new \RuntimeException('This report has no vulnerability identity to verify.');
        }

        $present = $exists($agent, $cve, $package);

        $previous = $ticket->status;

        $ticket->still_present = $present;
        $ticket->last_verified_at = Carbon::now();

        if (! $present && $ticket->status === 'remediating') {
            $ticket->status = 'verified';
            $ticket->resolved_at = Carbon::now();
        }

        if ($present && in_array($ticket->status, ['verified', 'closed'], true)) {
            $ticket->status = 'open';
            $ticket->resolved_at = null;
        }

        $ticket->save();

        $this->log(
            $ticket,
            $present ? 'verified_present' : 'verified_absent',
            $previous,
            $ticket->status,
            $present
                ? 'Scanner still reports this finding.'
                : 'Scanner no longer reports this finding.'
        );

        return $ticket;
    }
}
