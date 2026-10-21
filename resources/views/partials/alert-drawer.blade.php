    {{-- Alert detail off-canvas drawer --}}
    <div class="drawer-backdrop" id="alertDrawer" style="display:none">
        <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="adTitle">
            <header class="drawer-head">
                <div style="min-width:0;flex:1">
                    <div class="row" style="gap:8px">
                        <span id="adLevel" class="badge info">0</span>
                        <span class="mono fs-11 text-muted" id="adMeta">&mdash;</span>                    </div>
                    <h3 id="adTitle" style="margin:5px 0 0;font-size:13px;font-weight:600;line-height:1.4">&mdash;</h3>
                </div>
                <button type="button" class="btn ghost sm" id="adClose" aria-label="Close">&times;</button>
            </header>

            <div class="drawer-body">
                <div id="adLoading" class="empty" style="padding:30px 10px">Loading alert&hellip;</div>
                <div id="adError" class="banner error" style="display:none"></div>

                <div id="adContent" style="display:none">
                    <div id="adFacts"></div>

                    <div class="drawer-section" id="adMessageSection" style="display:none">
                        <h4>Windows Event Message</h4>
                        <pre class="json" id="adMessage" style="max-height:220px"></pre>
                    </div>

                    <div class="drawer-section" id="adEventDataSection" style="display:none">
                        <h4>Event Data</h4>
                        <div class="detail-grid" id="adEventData"></div>
                    </div>

                    <div class="drawer-section" id="adComplianceSection" style="display:none">
                        <h4>Compliance Mapping</h4>
                        <div id="adCompliance"></div>
                    </div>

                    <div class="drawer-section">
                        <h4>Raw Event</h4>
                        <pre class="json" id="adRaw" data-json style="max-height:260px"></pre>
                    </div>

                    <div class="drawer-section" id="adActionsSection">
                        <h4>Actions</h4>

                        <div class="row wrap" style="gap:6px">
                            @can('triage')
                            <button type="button" class="btn sm" id="adToggleHandling">
                                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                    <path d="M12 20h9M16.5 3.5a2.1 2.1 0 013 3L7 19l-4 1 1-4z"/>
                                </svg>
                                Update handling
                            </button>
                            @endcan
                            @can('triage')
                                <button type="button" class="btn sm" id="adToggleReport">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                                        <path d="M12 5v14M5 12h14"/>
                                    </svg>
                                    New report
                                </button>
                            @endcan
                            <a class="btn sm" id="adTicketLink" style="display:none" href="#">View ticket</a>
                        </div>

                        <div class="fs-12 text-secondary mt-14" id="adCurrentState"></div>
                    </div>

                    @can('triage')
                        <div class="drawer-section" id="adHandlingSection" style="display:none">
                            <h4>Handling</h4>
                            <form method="POST" action="{{ route('alerts.update') }}" id="adHandleForm">
                                @csrf
                                <input type="hidden" name="index" id="adIndex">
                                <input type="hidden" name="id" id="adId">

                                <div class="form-row">
                                    <label for="adStatus">Status</label>
                                    <select id="adStatus" name="status" class="select" required></select>
                                </div>

                                <div class="form-row">
                                    <label for="adAssignee">Assignee</label>
                                    <select id="adAssignee" name="assignee_id" class="select"></select>
                                </div>

                                <div class="form-row">
                                    <span class="form-row-label" id="adLabelsLabel">Labels</span>
                                    <div class="label-picker" id="adLabels" role="group" aria-labelledby="adLabelsLabel"></div>
                                </div>

                                <div class="form-row">
                                    <label for="adComment">Add note</label>
                                    <textarea id="adComment" name="comment" class="input" rows="2"
                                              placeholder="What did you find? (optional)"></textarea>
                                </div>

                                <div class="row" style="gap:6px">
                                    <button type="submit" class="btn primary sm" style="flex:1;justify-content:center">
                                        Save handling
                                    </button>
                                    <button type="button" class="btn ghost sm" data-close-section="adHandlingSection">Cancel</button>
                                </div>
                            </form>
                        </div>

                        <div class="drawer-section" id="adReportSection" style="display:none">
                            <h4>New Report</h4>
                            <form method="POST" action="{{ route('reports.from-alert') }}" id="adIncidentForm">
                                @csrf
                                <input type="hidden" name="index" id="adRIndex">
                                <input type="hidden" name="id" id="adRId">

                                <div class="form-row">
                                    <label for="adRTitle">Title</label>
                                    <input id="adRTitle" name="title" class="input" maxlength="200">
                                </div>

                                <div class="form-row">
                                    <label for="adRDesc">Description</label>
                                    <textarea id="adRDesc" name="description" class="input" rows="2"
                                              placeholder="Initial findings, scope..."></textarea>
                                </div>

                                <div class="form-row">
                                    <label for="adRPriority">Priority</label>
                                    <select id="adRPriority" name="priority" class="select" required>
                                        @foreach (\App\Models\Ticket::PRIORITIES as $key => $meta)
                                            <option value="{{ $key }}">{{ $meta['label'] }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-row">
                                    <label for="adRAssignee">Assign to</label>
                                    <select id="adRAssignee" name="assignee_id" class="select">
                                        <option value="">Unassigned</option>
                                        @foreach ($users as $user)
                                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="form-row">
                                    <label for="adRFrom">Count alerts from <span class="text-muted" style="font-weight:400;text-transform:none">(optional)</span></label>
                                    <input id="adRFrom" name="window_from" type="date" class="input">
                                    <input id="adRTo" name="window_to" type="date" class="input" style="margin-top:6px"
                                           aria-label="Count alerts until (optional)">
                                    <span class="fs-11 text-muted">
                                        Leave both empty to count from this alert's timestamp with no end.
                                        Fill "to" to freeze the window at a fixed period.
                                    </span>
                                </div>

                                <div class="form-row">
                                    <span class="form-row-label" id="adRAgentsLabel">Agents <span class="text-muted" style="font-weight:400;text-transform:none">(optional)</span></span>
                                    <div class="label-picker" id="adRAgents" role="group" aria-labelledby="adRAgentsLabel">
                                        @foreach (($options['agents'] ?? []) as $agentName)
                                            <label class="label-option" style="--chip:var(--accent)">
                                                <input type="checkbox" name="agents[]" value="{{ $agentName }}">
                                                <span>{{ $agentName }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                    <span class="fs-11 text-muted">
                                        Leave empty to count only this alert's agent. Select more to track
                                        the same rule across several endpoints.
                                    </span>
                                </div>

                                <div class="row" style="gap:6px">
                                    <button type="submit" class="btn primary sm" style="flex:1;justify-content:center">
                                        Create ticket
                                    </button>
                                    <button type="button" class="btn ghost sm" data-close-section="adReportSection">Cancel</button>
                                </div>
                            </form>
                        </div>
                    @endcan


                    <div class="drawer-section" id="adNotesSection" style="display:none">
                        <h4>Notes</h4>
                        <div class="timeline" id="adNotes"></div>
                    </div>
                </div>
            </div>
        </aside>
    </div>
