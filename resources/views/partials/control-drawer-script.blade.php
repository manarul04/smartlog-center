    <script>
    // Compliance control drawer: opened by the control code buttons in the
    // compliance table. Catalogue text is embedded on the drawer element so the
    // panel can explain a control without another round trip.
    (function () {
        if (window.__wlcControlDrawerBound) return;
        window.__wlcControlDrawerBound = true;

        var drawer = document.getElementById('controlDrawer');
        if (!drawer) return;

        var aside = drawer.querySelector('.drawer');
        var elFramework = document.getElementById('cdFramework');
        var elCode = document.getElementById('cdCode');
        var elTitle = document.getElementById('cdTitle');
        var elTitleText = document.getElementById('cdTitleText');
        var elSummary = document.getElementById('cdSummary');
        var elFwNote = document.getElementById('cdFwNote');
        var elStats = document.getElementById('cdStats');
        var elDrill = document.getElementById('cdDrill');
        var elClose = document.getElementById('cdClose');

        function esc(v) {
            return String(v === null || v === undefined ? '' : v)
                .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
        }

        // Parsed once and cached; falls back to an empty catalogue.
        var catalogue = null;
        function catalog() {
            if (catalogue) return catalogue;
            try {
                catalogue = JSON.parse((aside && aside.getAttribute('data-compliance-catalogue')) || '{}');
            } catch (e) {
                catalogue = { frameworks: {}, controls: {} };
            }
            return catalogue;
        }

        function open(d) {
            var cat = catalog();
            var framework = d.framework || '';
            var code = d.code || '';
            var entry = ((cat.controls || {})[framework] || {})[code];
            var fwNote = (cat.frameworks || {})[framework] || '';
            var title = entry && entry.title ? entry.title : code;
            var summary = entry && entry.summary ? entry.summary
                : 'Penjelasan untuk kontrol ini belum tersedia di katalog.';

            elFramework.textContent = d.framework_label || framework || '—';
            elCode.textContent = code || '—';
            elTitle.textContent = title || 'Control';
            elTitleText.textContent = title || '—';
            elSummary.textContent = summary;

            if (fwNote) {
                elFwNote.textContent = fwNote;
                elFwNote.style.display = '';
            } else {
                elFwNote.textContent = '';
                elFwNote.style.display = 'none';
            }

            var lastSeen = d.last_seen ? new Date(d.last_seen).toLocaleString() : '—';

            elStats.innerHTML =
                '<div class="k">Alerts</div><div class="v">' + esc(Number(d.count || 0).toLocaleString()) + '</div>' +
                '<div class="k">Max level</div><div class="v">' + esc(d.max_level === null || d.max_level === undefined ? '—' : d.max_level) + '</div>' +
                '<div class="k">Last seen</div><div class="v">' + esc(lastSeen) + '</div>';

            elDrill.href = d.drill || '#';

            drawer.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function close() {
            drawer.style.display = 'none';
            document.body.style.overflow = '';
        }

        document.addEventListener('click', function (e) {
            var btn = e.target.closest ? e.target.closest('.control-code') : null;
            if (!btn) return;

            // Keep the click away from the backdrop handler, or the drawer closes.
            e.preventDefault();
            e.stopPropagation();

            var d;
            try {
                d = JSON.parse(btn.getAttribute('data-control') || '{}');
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
