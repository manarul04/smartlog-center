<script>
// Shared compliance chip behaviour: works for chips rendered by Blade as well
// as chips rebuilt by the alert drawer on every render.
(function () {
    if (window.__wlcComplianceBound) return;
    window.__wlcComplianceBound = true;

    function esc(v) {
        return String(v === null || v === undefined ? '' : v)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    // The catalogue is embedded as a JSON attribute on the chip container.
    // Parsed once per element and cached, so repeated clicks are cheap.
    function catalogue(container) {
        if (container.__catalogue) return container.__catalogue;
        try {
            container.__catalogue = JSON.parse(container.getAttribute('data-compliance-catalogue') || '{}');
        } catch (e) {
            container.__catalogue = { frameworks: {}, controls: {} };
        }
        return container.__catalogue;
    }

    document.addEventListener('click', function (e) {
        var chip = e.target.closest('.compliance-chip');
        if (!chip) return;

        // Keep the click away from the backdrop handler, or the drawer closes.
        e.preventDefault();
        e.stopPropagation();

        var container = chip.closest('[data-compliance-catalogue]');
        if (!container) return;

        var target = container.getAttribute('data-compliance-target');
        var panel = target ? document.getElementById(target) : null;
        if (!panel) return;

        var framework = chip.getAttribute('data-framework') || '';
        var code = chip.getAttribute('data-code') || '';
        var cat = catalogue(container);
        var entry = ((cat.controls || {})[framework] || {})[code];
        var fwNote = (cat.frameworks || {})[framework] || '';
        var title = entry && entry.title ? entry.title : code;
        var summary = entry && entry.summary ? entry.summary
            : 'Penjelasan untuk kontrol ini belum tersedia di katalog.';

        panel.innerHTML =
            '<div class="compliance-detail-head">' +
                '<span class="badge neutral mono">' + esc(framework) + '</span>' +
                '<strong>' + esc(code) + '</strong>' +
            '</div>' +
            '<div class="compliance-detail-title">' + esc(title) + '</div>' +
            '<p class="compliance-detail-summary">' + esc(summary) + '</p>' +
            (fwNote ? '<p class="compliance-detail-framework">' + esc(fwNote) + '</p>' : '');

        panel.style.display = '';

        // Mark the active chip within this container only.
        container.querySelectorAll('.compliance-chip').forEach(function (el) {
            var on = el.getAttribute('data-framework') === framework && el.getAttribute('data-code') === code;
            el.classList.toggle('is-active', on);
        });
    });
})();
</script>
