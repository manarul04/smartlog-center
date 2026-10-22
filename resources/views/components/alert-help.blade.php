@props(['context' => 'explorer'])

{{--
    Explains the two labels an analyst sees on every row: the Wazuh severity
    level and the triage status. Level notes follow Wazuh's own convention
    (0-15); the wording is deliberately hedged because the rule author picks
    the number, not this app.
--}}
@php
    $levels = [
        0 => 'Grouping rule. The event is decoded and matched but never alerted on; it only feeds child rules.',
        1 => 'Routine agent or system status. No security meaning on its own, kept as context.',
        2 => 'Normal operational message, such as a service start or a configuration read.',
        3 => 'Successful action, or an error with no security impact (a typo, a missing file).',
        4 => 'Policy or configuration violation, or an error that may matter later, e.g. a denied permission.',
        5 => 'Unexpected behaviour from a legitimate program, or a user-level mistake.',
        6 => 'Weak signal: a low-severity attempt, a scanner probe, something odd but explainable.',
        7 => 'A known-bad pattern matched (bad word, blocked signature) with no confirmed impact yet.',
        8 => 'Patterned behaviour: brute-force thresholds, monitored file changes, failing security services.',
        9 => 'An attack that was stopped, or an error with direct security relevance.',
        10 => 'Evidence of an attack or compromise on the endpoint. Someone has to decide, now.',
        11 => 'Integrity or security breach: critical file modified, malware found, audit log cleared.',
        12 => 'High-impact attack in progress: exploitation, ransomware behaviour, credential dumping.',
        13 => 'Successful intrusion or privilege escalation confirmed by the ruleset.',
        14 => 'Severe compromise: the attacker controls a critical asset or its data.',
        15 => 'Maximum severity, reserved for the worst case the ruleset knows how to describe.',
    ];

    $statusNotes = [
        'not_reviewed' => 'No handling record yet. Nothing has been decided, so the alert stays in the open queue.',
        'in_review' => 'Someone opened it and is checking evidence. No verdict has been recorded.',
        'in_progress' => 'Confirmed as worth handling. Containment or remediation is under way.',
        'resolved' => 'Handled: root cause fixed, or the activity stopped and was verified. Leaves the open queue.',
        'false_positive' => 'Reviewed and judged benign, e.g. an expected admin action. Leaves the queue, stays on record for tuning.',
        'closed' => 'Archived without a verdict: duplicate, out of scope, or superseded. It does not claim the activity was benign.',
    ];

    $pageNotes = $context === 'groups'
        ? [
            ['Alerts', 'Events for that agent and rule inside the selected window. Not unique hosts, not unique alerts after dedup.'],
            ['Max level', 'The highest rule level seen in the group. One critical event lifts the whole row.'],
            ['Level mix', 'How many alerts sit at each level, highest first, top three levels only.'],
            ['Last seen', 'Timestamp of the newest event still inside the window.'],
            ['Rows', 'One row per agent + rule pair, so the same rule on two endpoints is two rows.'],
        ]
        : [
            ['Time', 'The event timestamp reported by the agent, not the moment it reached the indexer.'],
            ['Level', 'The level of the single rule that produced this alert. Nothing is averaged or combined.'],
            ['Matching events', 'Live count from OpenSearch for the current filters and window.'],
            ['Status / Last action / Labels', 'The triage record for this alert, stored apart from the event itself.'],
        ];

    $bands = \App\Support\Severity::bands();
    $statuses = \App\Support\AlertStatus::all();
@endphp

<button type="button" class="btn sm help-trigger"
        data-help-open="alertHelp"
        aria-haspopup="dialog"
        aria-controls="alertHelp"
        title="How levels, statuses and counts work">
    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor"
         stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="9"/>
        <path d="M9.6 9a2.5 2.5 0 114 2c-.9.7-1.6 1.2-1.6 2.3"/>
        <path d="M12 17h.01"/>
    </svg>
    Help
</button>

<div class="modal-backdrop" id="alertHelp" style="display:none">
    <div class="modal help" role="dialog" aria-modal="true" aria-labelledby="alertHelpTitle">
        <div class="modal-head">
            <div>
                <div class="fs-11 text-muted mono">Reference</div>
                <h3 id="alertHelpTitle" style="margin:2px 0 0">Reading levels and statuses</h3>
            </div>
            <div class="spacer"></div>
            <button type="button" class="btn ghost sm" data-help-close aria-label="Close">&times;</button>
        </div>

        <div class="modal-body">
            <div class="help-section">
                <p>
                    Every alert carries two independent labels. The <strong>level</strong> comes from the Wazuh rule
                    that produced the event and explains why the alert exists. The <strong>status</strong> is what
                    your team has done about it. Neither one changes the other.
                </p>
            </div>

            <div class="help-section">
                <h4>Severity level (0&ndash;15)</h4>
                <p>
                    The number is declared by the rule author in the ruleset
                    (<code>&lt;rule level="&hellip;"&gt;</code>) and is passed through untouched, so it is not a
                    count and not a score computed here. It answers one question: how seriously did whoever wrote
                    the rule rate this event, assuming it fires. Open the alert to see the rule id, description and
                    groups behind the number.
                </p>

                <div class="help-bands">
                    @foreach ($bands as $band)
                        <span class="help-band" style="--chip:{{ $band['color'] }}">
                            {{ $band['label'] }}
                            <span class="mono">{{ $band['min'] }}&ndash;{{ $band['max'] }}</span>
                        </span>
                    @endforeach
                </div>

                <table class="help-table">
                    <thead>
                        <tr>
                            <th class="lvl">Level</th>
                            <th class="band">Band</th>
                            <th>What the rule is telling you</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($levels as $level => $note)
                            @php($band = \App\Support\Severity::band($level))
                            <tr>
                                <td><span class="badge {{ \App\Support\Severity::token($level) }}">{{ $level }}</span></td>
                                <td class="band">{{ $bands[$band]['label'] }}</td>
                                <td>{{ $note }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="help-section">
                <h4>Status</h4>
                <p>
                    Status lives in the triage record, not in OpenSearch, so it survives index rotation. An alert
                    with no record at all reads as <strong>Untriaged</strong>.
                </p>

                @foreach ($statuses as $key => $meta)
                    <div class="help-status">
                        <span class="status-chip {{ \App\Support\AlertStatus::isUnreviewed($key) ? 'is-muted' : '' }}"
                              style="--chip:{{ $meta['color'] }}">{{ $meta['label'] }}</span>
                        <div>
                            <div class="help-status-note">{{ $statusNotes[$key] }}</div>
                            <div class="fs-11 text-muted">
                                {{ $meta['open'] ? 'Counts as open, stays in the triage queue' : 'Counts as closed, leaves the queue' }}
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="help-section">
                <h4>Where the numbers come from</h4>
                <table class="help-table">
                    <tbody>
                        @foreach ($pageNotes as [$term, $note])
                            <tr>
                                <td class="term">{{ $term }}</td>
                                <td>{{ $note }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="modal-foot">
            <button type="button" class="btn" data-help-close>Got it</button>
        </div>
    </div>
</div>
