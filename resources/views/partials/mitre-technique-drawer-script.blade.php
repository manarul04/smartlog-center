    <script>
    // MITRE technique drawer: opened by the technique code buttons in the
    // Threat Hunting "Top Techniques" table. The technique payload is embedded
    // on the button so the panel needs no extra round trip.
    (function () {
        if (window.__wlcMitreDrawerBound) return;
        window.__wlcMitreDrawerBound = true;

        var drawer = document.getElementById('mitreDrawer');
        if (!drawer) return;

        var elCode = document.getElementById('mtCode');
        var elName = document.getElementById('mtName');
        var elTitle = document.getElementById('mtTitle');
        var elStats = document.getElementById('mtStats');
        var elTactics = document.getElementById('mtTactics');
        var elAgents = document.getElementById('mtAgents');
        var elExplore = document.getElementById('mtExplore');
        var elClose = document.getElementById('mtClose');

        function esc(v) {
            return String(v === null || v === undefined ? '' : v)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        function open(d) {
            var id = d.id || '—';
            var name = d.name || d.id || '—';

            elCode.textContent = d.id || '—';
            elName.textContent = name;
            elTitle.textContent = d.id + ' · ' + (d.name || '');

            var tactics = Array.isArray(d.tactics) ? d.tactics : [];

            elStats.innerHTML =
                '<div class="k">Technique</div><div class="v">' + esc(id) + '</div>' +
                '<div class="k">Alerts</div><div class="v">' + esc(Number(d.count || 0).toLocaleString()) + '</div>' +
                '<div class="k">Tactics</div><div class="v">' + esc(tactics.length) + '</div>';

            if (tactics.length) {
                elTactics.innerHTML = tactics.map(function (t) {
                    return '<span class="badge neutral">' + esc(t) + '</span>';
                }).join('');
            } else {
                elTactics.innerHTML = '<span class="fs-11 text-muted">—</span>';
            }

            var agents = Array.isArray(d.agents) ? d.agents.slice(0, 10) : [];
            if (agents.length) {
                elAgents.innerHTML = agents.map(function (a) {
                    a = a || {};
                    return '<div class="k">' + esc(a.name || '—') + '</div>' +
                        '<div class="v">' + esc(Number(a.count || 0).toLocaleString()) + '</div>';
                }).join('');
            } else {
                elAgents.innerHTML = '<div class="k">—</div><div class="v">—</div>';
            }

            elExplore.href = d.explore || '#';

            drawer.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function close() {
            drawer.style.display = 'none';
            document.body.style.overflow = '';
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.tech-code') : null;
            if (!btn) return;

            // Keep the click away from the backdrop handler, or the drawer closes.
            e.preventDefault();
            e.stopPropagation();

            var d;
            try {
                d = JSON.parse(btn.getAttribute('data-technique') || '{}');
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
