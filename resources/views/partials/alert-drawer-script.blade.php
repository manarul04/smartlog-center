<script>
(function () {
    var drawer = document.getElementById('alertDrawer');
    if (!drawer) return;

    var elLoading = document.getElementById('adLoading');
    var elError = document.getElementById('adError');
    var elContent = document.getElementById('adContent');
    var elLevel = document.getElementById('adLevel');
    var elMeta = document.getElementById('adMeta');
    var elTitle = document.getElementById('adTitle');
    var elFacts = document.getElementById('adFacts');
    var elRaw = document.getElementById('adRaw');
    var current = null;

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function fact(k, v) {
        if (v === null || v === undefined || v === '') return '';
        return '<div class="k">' + esc(k) + '</div><div class="v">' + esc(v) + '</div>';
    }

    function levelClass(level) {
        level = Number(level) || 0;
        if (level >= 12) return 'critical';
        if (level >= 10) return 'high';
        if (level >= 7) return 'medium';
        if (level >= 4) return 'low';
        return 'info';
    }

    function fillSelect(el, options, selected, placeholder) {
        el.innerHTML = '';
        if (placeholder !== null) {
            var opt = document.createElement('option');
            opt.value = '';
            opt.textContent = placeholder;
            el.appendChild(opt);
        }
        options.forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = o.value;
            opt.textContent = o.label;
            if (String(o.value) === String(selected)) opt.selected = true;
            el.appendChild(opt);
        });
    }

    function render(data) {
        var src = data.source || {};
        var rule = src.rule || {};
        var agent = src.agent || {};
        var level = Number(rule.level || 0);

        elLevel.className = 'badge ' + levelClass(level);
        elLevel.textContent = level;
        elMeta.textContent = (src.timestamp || '') +
            (agent.name ? ' · ' + agent.name : '');
        elTitle.textContent = rule.description || 'Alert';

        elFacts.innerHTML = '<div class="detail-grid">' +
            fact('Time', src.timestamp) +
            fact('Rule', (rule.id || '?') + (rule.firedtimes ? ' · fired ' + rule.firedtimes + 'x' : '')) +
            fact('Groups', (rule.groups || []).join(', ')) +
            fact('Agent', (agent.name || 'â€”') + (agent.ip ? ' (' + agent.ip + ')' : '')) +
            fact('Decoder', (src.decoder || {}).name) +
            fact('Location', src.location) +
            fact('Manager', (src.manager || {}).name) +
            '</div>';

        // Windows event message
        var msg = ((src.data || {}).win || {}).system || {};
        var msgText = msg.message;
        var msgSection = document.getElementById('adMessageSection');
        if (msgText) {
            document.getElementById('adMessage').textContent = msgText;
            msgSection.style.display = '';
        } else {
            msgSection.style.display = 'none';
        }

        // Windows event data
        var eventData = ((src.data || {}).win || {}).eventdata;
        var edSection = document.getElementById('adEventDataSection');
        var edHost = document.getElementById('adEventData');
        if (eventData && Object.keys(eventData).length) {
            var html = '';
            Object.keys(eventData).forEach(function (k) {
                var v = eventData[k];
                html += fact(k, typeof v === 'object' ? JSON.stringify(v) : v);
            });
            edHost.innerHTML = html;
            edSection.style.display = '';
        } else {
            edSection.style.display = 'none';
        }

        // Compliance mapping
        var comp = data.compliance || {};
        var compSection = document.getElementById('adComplianceSection');
        var compHost = document.getElementById('adCompliance');
        var compHtml = '';
        Object.keys(comp).forEach(function (fw) {
            var controls = comp[fw] || [];
            if (!controls.length) return;
            compHtml += '<div style="margin-bottom:10px"><div class="fs-11 text-muted" style="margin-bottom:4px">' +
                esc(fw) + '</div><div class="row wrap" style="gap:4px">';
            controls.forEach(function (c) {
                compHtml += '<span class="badge neutral mono">' + esc(c) + '</span>';
            });
            compHtml += '</div></div>';
        });
        if (compHtml) {
            compHost.innerHTML = compHtml;
            compSection.style.display = '';
        } else {
            compSection.style.display = 'none';
        }

        // Raw JSON with syntax highlighting
        var pretty = JSON.stringify(src, null, 2);
        elRaw.textContent = pretty;
        elRaw.innerHTML = pretty.replace(
            /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
            function (match) {
                var cls = 'n';
                if (/^"/.test(match)) cls = /:$/.test(match) ? 'k' : 's';
                else if (/true|false|null/.test(match)) cls = 'b';
                return '<span class="' + cls + '">' + match + '</span>';
            }
        );

        var opts = data.options || {};
        window.__adStatuses = opts.statuses || [];
        var ann = data.annotation;

        if (opts.can_triage) {
            document.getElementById('adIndex').value = data.index;
            document.getElementById('adId').value = data.id;
            document.getElementById('adRIndex').value = data.index;
            document.getElementById('adRId').value = data.id;

            fillSelect(document.getElementById('adStatus'),
                (opts.statuses || []).map(function (s) { return { value: s.value, label: s.label }; }),
                ann ? ann.status : 'not_reviewed', null);

            fillSelect(document.getElementById('adAssignee'),
                (opts.users || []).map(function (u) { return { value: u.id, label: u.name }; }),
                ann ? ann.assignee_id : '', 'Unassigned');


            var labelHost = document.getElementById('adLabels');
            labelHost.innerHTML = '';
            var selected = ann ? ann.labels.map(function (l) { return String(l.id); }) : [];
            (opts.labels || []).forEach(function (l) {
                var wrap = document.createElement('label');
                wrap.className = 'label-option';
                wrap.style.setProperty('--chip', l.color);
                var input = document.createElement('input');
                input.type = 'checkbox';
                input.name = 'label_ids[]';
                input.value = l.id;
                if (selected.indexOf(String(l.id)) !== -1) input.checked = true;
                var span = document.createElement('span');
                span.textContent = l.name;
                wrap.appendChild(input);
                wrap.appendChild(span);
                labelHost.appendChild(wrap);
            });

            document.getElementById('adRTitle').value = (rule.description || '').slice(0, 200);
            document.getElementById('adRFrom').value = '';
            document.getElementById('adRTo').value = '';

            renderAgentPicker(opts.agents || [], agent.name);
        }

        // Notes timeline
        var notesSection = document.getElementById('adNotesSection');
        var notesHost = document.getElementById('adNotes');
        if (ann && ann.comments && ann.comments.length) {
            notesHost.innerHTML = ann.comments.map(function (c) {
                return '<div class="timeline-item"><div class="timeline-meta">' +
                    esc(c.user) + ' · ' + esc(c.at) + '</div><div class="timeline-body">' +
                    esc(c.body) + '</div></div>';
            }).join('');
            notesSection.style.display = '';
        } else {
            notesSection.style.display = 'none';
        }

        // Compact current state, so the drawer stays clean until the user
        // explicitly opens a form.
        var stateHost = document.getElementById('adCurrentState');
        var parts = [];
        parts.push('Status: ' + (ann ? statusLabel(ann.status) : 'Untriaged'));
        parts.push('Assignee: ' + (ann && ann.assignee ? ann.assignee : 'Unassigned'));
        if (ann && ann.labels && ann.labels.length) {
            parts.push('Labels: ' + ann.labels.map(function (l) { return l.name; }).join(', '));
        }
        stateHost.textContent = parts.join('  ·  ');

        var ticketLink = document.getElementById('adTicketLink');
        if (ann && ann.ticket) {
            ticketLink.style.display = '';
            ticketLink.href = '/reports/' + ann.ticket.id;
            ticketLink.textContent = 'View ' + ann.ticket.reference;
        } else {
            ticketLink.style.display = 'none';
        }

        // Forms start collapsed.
        document.getElementById('adHandlingSection').style.display = 'none';
        document.getElementById('adReportSection').style.display = 'none';

        elLoading.style.display = 'none';
        elError.style.display = 'none';
        elContent.style.display = '';
    }

    function statusLabel(value) {
        var found = (window.__adStatuses || []).filter(function (s) { return s.value === value; })[0];
        return found ? found.label : 'Untriaged';
    }

    /**
     * Optional multi-agent picker for the report form. The alert's own agent is
     * checked by default and is always present, even when it had no other
     * activity in the cached option list.
     */
    function renderAgentPicker(agents, currentAgent) {
        var host = document.getElementById('adRAgents');
        if (!host) return;

        host.innerHTML = '';

        var names = agents.slice();
        if (currentAgent && names.indexOf(currentAgent) === -1) names.unshift(currentAgent);

        names.forEach(function (name) {
            var wrap = document.createElement('label');
            wrap.className = 'label-option';
            wrap.style.setProperty('--chip', 'var(--accent)');

            var input = document.createElement('input');
            input.type = 'checkbox';
            input.name = 'agents[]';
            input.value = name;
            input.checked = name === currentAgent;

            var span = document.createElement('span');
            span.textContent = name;

            wrap.appendChild(input);
            wrap.appendChild(span);
            host.appendChild(wrap);
        });
    }

    function open(index, id) {
        drawer.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        elLoading.style.display = '';
        elError.style.display = 'none';
        elContent.style.display = 'none';
        current = { index: index, id: id };

        var url = new URL(@json(route('explorer.detail')), window.location.origin);
        url.searchParams.set('index', index);
        url.searchParams.set('id', id);

        fetch(url, { headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
            .then(function (res) {
                if (!res.ok || !res.body.ok) {
                    elLoading.style.display = 'none';
                    elError.style.display = '';
                    elError.textContent = (res.body && res.body.error) || 'Unable to load alert.';
                    return;
                }
                render(res.body);
            })
            .catch(function () {
                elLoading.style.display = 'none';
                elError.style.display = '';
                elError.textContent = 'Network error while loading the alert.';
            });
    }

    function close() {
        drawer.style.display = 'none';
        document.body.style.overflow = '';
        if (current) {
            var params = new URLSearchParams(window.location.search);
            params.delete('alert');
            var qs = params.toString();
            history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
        }
        current = null;
    }

    document.querySelectorAll('[data-alert]').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('a, input, button, select')) return;
            var d = JSON.parse(row.getAttribute('data-alert'));
            open(d.index, d.id);
        });
    });

    // Toggle the optional forms; only one open at a time keeps the drawer short.
    function toggleSection(id) {
        var target = document.getElementById(id);
        var others = ['adHandlingSection', 'adReportSection'].filter(function (x) { return x !== id; });
        others.forEach(function (x) {
            var el = document.getElementById(x);
            if (el) el.style.display = 'none';
        });
        target.style.display = target.style.display === 'none' ? '' : 'none';
        if (target.style.display !== 'none') {
            target.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }
    }

    var btnHandling = document.getElementById('adToggleHandling');
    var btnReport = document.getElementById('adToggleReport');
    if (btnHandling) btnHandling.addEventListener('click', function () { toggleSection('adHandlingSection'); });
    if (btnReport) btnReport.addEventListener('click', function () { toggleSection('adReportSection'); });

    document.querySelectorAll('[data-close-section]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById(btn.getAttribute('data-close-section')).style.display = 'none';
        });
    });

    document.getElementById('adClose').addEventListener('click', close);
    drawer.addEventListener('click', function (e) { if (e.target === drawer) close(); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

    // Deep link: ?alert=<uid> opens the drawer for the matching row on this page.
    var wanted = new URLSearchParams(window.location.search).get('alert');
    if (wanted) {
        var rows = document.querySelectorAll('[data-alert]');
        for (var i = 0; i < rows.length; i++) {
            var d = JSON.parse(rows[i].getAttribute('data-alert'));
            if (d.uid === wanted) { open(d.index, d.id); break; }
        }
    }

    // Other pages (Alert Groups) reuse the same drawer instead of duplicating it.
    window.AlertDrawer = { open: open, close: close };
})();
</script>
