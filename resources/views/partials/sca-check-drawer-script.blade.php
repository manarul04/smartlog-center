    <script>
    // SCA check detail drawer: every check code in the table opens the full
    // check record. Read-only, so it also works for passed checks and for
    // roles that cannot triage.
    (function () {
        if (window.__wlcScaCheckDrawerBound) return;
        window.__wlcScaCheckDrawerBound = true;

        var drawer = document.getElementById('scaCheckDrawer');
        if (!drawer) return;

        var elResult = document.getElementById('sccResult');
        var elId = document.getElementById('sccId');
        var elTitle = document.getElementById('sccTitle');
        var elFacts = document.getElementById('sccFacts');
        var elRationale = document.getElementById('sccRationale');
        var elRationaleSection = document.getElementById('sccRationaleSection');
        var elDescription = document.getElementById('sccDescription');
        var elRemediation = document.getElementById('sccRemediation');
        var elRemediationSection = document.getElementById('sccRemediationSection');
        var elCommand = document.getElementById('sccCommand');
        var elDirectory = document.getElementById('sccDirectory');
        var elClose = document.getElementById('sccClose');

        function esc(v) {
            return String(v === null || v === undefined ? '' : v)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function fact(k, v) {
            if (v === null || v === undefined || v === '') return '';
            return '<div class="k">' + esc(k) + '</div><div class="v">' + esc(v) + '</div>';
        }

        function resultClass(result) {
            switch (String(result || '').toLowerCase()) {
                case 'passed': return 'success';
                case 'failed': return 'critical';
                case 'not applicable': return 'info';
                default: return 'neutral';
            }
        }

        function open(d) {
            elResult.className = 'badge ' + resultClass(d.result);
            elResult.textContent = d.result || '—';
            elId.textContent = 'Check ' + (d.check_id || '—');
            elTitle.textContent = d.title || 'Check';

            elFacts.innerHTML =
                fact('Check ID', d.check_id) +
                fact('Policy', d.policy) +
                fact('Result', d.result) +
                fact('Status', d.status_label);

            var rationale = d.rationale || '';
            elRationale.textContent = rationale;
            elRationaleSection.style.display = rationale ? '' : 'none';

            elDescription.textContent = d.description || 'No description recorded for this check.';

            var remediation = d.remediation || '';
            var command = (d.command || []).join('\n');
            var directory = (d.directory || []).join('\n');

            elRemediation.textContent = remediation;
            elCommand.textContent = command;
            elCommand.style.display = command ? '' : 'none';
            elDirectory.textContent = directory;
            elDirectory.style.display = directory ? '' : 'none';

            elRemediationSection.style.display = (remediation || command || directory) ? '' : 'none';

            drawer.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function close() {
            drawer.style.display = 'none';
            document.body.style.overflow = '';
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.check-code') : null;
            if (!btn) return;

            e.preventDefault();
            e.stopPropagation();

            var d;
            try {
                d = JSON.parse(btn.getAttribute('data-check') || '{}');
            } catch (err) {
                return;
            }

            open(d);
        });

        elClose.addEventListener('click', close);

        drawer.addEventListener('click', function (e) {
            if (e.target === drawer) close();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' && e.key !== 'Esc') return;
            if (drawer.style.display === 'none') return;
            close();
        });
    })();
    </script>
