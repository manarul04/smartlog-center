(function () {
  'use strict';

  // ---- Clock -------------------------------------------------------------
  var clock = document.getElementById('clock');
  if (clock) {
    var tick = function () {
      var d = new Date();
      clock.textContent = d.toLocaleString([], {
        hour: '2-digit', minute: '2-digit', second: '2-digit',
        day: '2-digit', month: 'short'
      });
    };
    tick();
    setInterval(tick, 1000);
  }

  // ---- Sidebar toggle (mobile) ------------------------------------------
  var toggle = document.getElementById('sidebarToggle');
  var sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    if (window.matchMedia('(max-width: 980px)').matches) {
      toggle.style.display = 'inline-flex';
    }
    toggle.addEventListener('click', function () {
      sidebar.classList.toggle('open');
    });
  }

  // ---- Theme -------------------------------------------------------------
  var themeForm = document.getElementById('themeForm');
  var themeInput = document.getElementById('themeInput');

  if (themeForm && themeInput) {
    themeForm.addEventListener('submit', function () {
      var next = themeInput.value === 'light' ? 'dark' : 'light';
      themeInput.value = next;
      // Mirror the choice locally so the next page paints with it immediately,
      // then let the request persist it on the account.
      try { localStorage.setItem('wlc-theme', next); } catch (e) { /* ignore */ }
      document.documentElement.setAttribute('data-theme', next);
    });
  }

  var currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';

  function themePalette() {
    var styles = getComputedStyle(document.documentElement);
    var read = function (name, fallback) {
      return (styles.getPropertyValue(name) || '').trim() || fallback;
    };

    return {
      text: read('--text-secondary', '#9aa9be'),
      title: read('--text-primary', '#e8eef7'),
      border: read('--border', '#1f2b3d'),
      grid: read('--grid-line', 'rgba(31,43,61,.7)'),
      tooltipBg: read('--bg-root', '#0a0e17'),
      tooltipBorder: read('--border-strong', '#2a3a52'),
      surface: read('--bg-surface', '#111827')
    };
  }

  // ---- Chart defaults ----------------------------------------------------
  if (window.Chart) {
    var palette = themePalette();
    Chart.defaults.color = palette.text;
    Chart.defaults.font.family = "'Inter', -apple-system, sans-serif";
    Chart.defaults.font.size = 11;
    Chart.defaults.borderColor = palette.border;
    Chart.defaults.plugins.legend.display = false;
    Chart.defaults.plugins.tooltip.backgroundColor = palette.tooltipBg;
    Chart.defaults.plugins.tooltip.borderColor = palette.tooltipBorder;
    Chart.defaults.plugins.tooltip.borderWidth = 1;
    Chart.defaults.plugins.tooltip.titleColor = palette.title;
    Chart.defaults.plugins.tooltip.bodyColor = palette.text;
    Chart.defaults.plugins.tooltip.padding = 10;
    Chart.defaults.plugins.tooltip.cornerRadius = 7;
    Chart.defaults.animation.duration = 320;
    Chart.defaults.maintainAspectRatio = false;
  }

  // ---- Helpers exposed to page scripts -----------------------------------
  window.WLC = {
    palette: ['#64748b', '#38bdf8', '#fbbf24', '#fb923c', '#f43f5e'],

    /** Resolved theme colors, read live from CSS custom properties. */
    theme: function () {
      return themePalette();
    },

    levelColor: function (level) {
      level = Number(level);
      if (level >= 12) return '#f43f5e';
      if (level >= 10) return '#fb923c';
      if (level >= 7) return '#fbbf24';
      if (level >= 4) return '#38bdf8';
      return '#64748b';
    },

    fmt: function (n) {
      if (n === null || n === undefined) return '—';
      n = Number(n);
      if (n >= 1e9) return (n / 1e9).toFixed(1).replace('.0', '') + 'B';
      if (n >= 1e6) return (n / 1e6).toFixed(1).replace('.0', '') + 'M';
      if (n >= 1e4) return (n / 1e3).toFixed(1).replace('.0', '') + 'k';
      return n.toLocaleString();
    },

    shortTime: function (iso) {
      if (!iso) return '—';
      var d = new Date(iso);
      if (isNaN(d)) return iso;
      return d.toLocaleString([], { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
    },

    submitFilter: function (form) {
      if (form && typeof form.requestSubmit === 'function') form.requestSubmit();
      else if (form) form.submit();
    },

    debounce: function (fn, ms) {
      var t;
      return function () {
        var args = arguments, ctx = this;
        clearTimeout(t);
        t = setTimeout(function () { fn.apply(ctx, args); }, ms || 300);
      };
    }
  };

  // ---- Auto-submit selects -----------------------------------------------
  document.querySelectorAll('[data-autosubmit]').forEach(function (el) {
    el.addEventListener('change', function () {
      var form = el.closest('form');
      if (form) WLC.submitFilter(form);
    });
  });

  // ---- Debounced text filter ---------------------------------------------
  document.querySelectorAll('[data-debounce-submit]').forEach(function (el) {
    el.addEventListener('input', WLC.debounce(function () {
      var form = el.closest('form');
      if (form) WLC.submitFilter(form);
    }, 550));
  });

  // ---- Custom date range fields ------------------------------------------
  document.querySelectorAll('[data-range-select]').forEach(function (select) {
    var form = select.closest('form');
    if (!form) return;

    var fields = form.querySelectorAll('[data-range-custom]');

    function sync() {
      var custom = select.value === 'custom';
      fields.forEach(function (field) {
        field.style.display = custom ? '' : 'none';
        field.querySelectorAll('input').forEach(function (input) {
          input.disabled = !custom;
        });
      });
    }

    select.addEventListener('change', function () {
      sync();
      // Picking "custom" only reveals the date fields; the user still has to
      // choose both dates before the range means anything.
      if (select.value !== 'custom') WLC.submitFilter(form);
    });

    fields.forEach(function (field) {
      field.querySelectorAll('input').forEach(function (input) {
        input.addEventListener('change', function () {
          if (select.value === 'custom' && input.value) WLC.submitFilter(form);
        });
      });
    });

    sync();
  });

  // ---- Copy buttons ------------------------------------------------------
  document.querySelectorAll('[data-copy]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.getAttribute('data-copy') || '';
      navigator.clipboard.writeText(text).then(function () {
        var original = btn.textContent;
        btn.textContent = 'Copied';
        setTimeout(function () { btn.textContent = original; }, 1200);
      });
    });
  });

  // ---- JSON syntax highlighting -----------------------------------------
  document.querySelectorAll('pre[data-json]').forEach(function (pre) {
    var raw = pre.textContent;
    try {
      var parsed = JSON.parse(raw);
      raw = JSON.stringify(parsed, null, 2);
    } catch (e) { /* leave as-is */ }
    pre.innerHTML = raw.replace(
      /("(\\u[a-zA-Z0-9]{4}|\\[^u]|[^\\"])*"(\s*:)?|\b(true|false|null)\b|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
      function (match) {
        var cls = 'n';
        if (/^"/.test(match)) cls = /:$/.test(match) ? 'k' : 's';
        else if (/true|false|null/.test(match)) cls = 'b';
        return '<span class="' + cls + '">' + match + '</span>';
      }
    );
  });

  // ---- Clickable table rows ---------------------------------------------
  // Rows carrying data-href navigate on click, but real links and form
  // controls inside the row keep their own behaviour.
  document.querySelectorAll('tr[data-href]').forEach(function (row) {
    row.addEventListener('click', function (e) {
      if (e.target.closest('a, button, input, select, textarea, label')) return;
      window.location.href = row.getAttribute('data-href');
    });
  });

  // ---- Auto refresh ------------------------------------------------------
  var refreshEl = document.querySelector('[data-refresh-interval]');
  if (refreshEl) {
    var seconds = parseInt(refreshEl.getAttribute('data-refresh-interval'), 10);
    var label = document.getElementById('refreshLabel');
    if (seconds > 0) {
      var remaining = seconds;
      setInterval(function () {
        remaining -= 1;
        if (label) label.textContent = remaining + 's';
        if (remaining <= 0) window.location.reload();
      }, 1000);
    }
  }
})();

  // ---- Alert explorer bulk selection -------------------------------------
  (function () {
    var form = document.getElementById('bulkForm');
    if (!form) return;

    var selectAll = document.getElementById('selectAll');
    var checks = Array.prototype.slice.call(document.querySelectorAll('.rowCheck'));
    var bar = document.getElementById('bulkBar');
    var countEl = document.getElementById('bulkCount');
    var itemsHost = document.getElementById('bulkItems');

    function selected() {
      return checks.filter(function (c) { return c.checked; });
    }

    function sync() {
      var chosen = selected();
      if (countEl) countEl.textContent = chosen.length;
      if (bar) bar.style.display = chosen.length > 0 ? 'flex' : 'none';

      if (selectAll) {
        selectAll.checked = chosen.length > 0 && chosen.length === checks.length;
        selectAll.indeterminate = chosen.length > 0 && chosen.length < checks.length;
      }

      // Mirror the selection into hidden inputs right before submit.
      if (itemsHost) {
        itemsHost.innerHTML = '';
        chosen.forEach(function (c, i) {
          ['index', 'id'].forEach(function (field) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'items[' + i + '][' + field + ']';
            input.value = c.getAttribute('data-' + (field === 'index' ? 'index' : 'id'));
            itemsHost.appendChild(input);
          });
        });
      }
    }

    if (selectAll) {
      selectAll.addEventListener('change', function () {
        checks.forEach(function (c) { c.checked = selectAll.checked; });
        sync();
      });
    }

    checks.forEach(function (c) { c.addEventListener('change', sync); });

    form.addEventListener('submit', function (e) {
      if (selected().length === 0) {
        e.preventDefault();
        return;
      }
      sync();
    });

    sync();
  })();

  // ---- Confirm destructive/bulk actions ---------------------------------
  document.querySelectorAll('form[data-confirm]').forEach(function (f) {
    f.addEventListener('submit', function (e) {
      if (!window.confirm(f.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });

  // ---- Help dialogs ------------------------------------------------------
  // Any [data-help-open="<id>"] opens the matching .modal-backdrop; closing
  // happens through [data-help-close], the backdrop, or Escape.
  (function () {
    function close(backdrop) {
      if (!backdrop) return;
      backdrop.style.display = 'none';
      var trigger = document.querySelector('[data-help-open="' + backdrop.id + '"]');
      if (trigger) trigger.focus();
    }

    document.querySelectorAll('[data-help-open]').forEach(function (trigger) {
      trigger.addEventListener('click', function () {
        var backdrop = document.getElementById(trigger.getAttribute('data-help-open'));
        if (!backdrop) return;
        backdrop.style.display = 'flex';
        var focusable = backdrop.querySelector('[data-help-close]');
        if (focusable) focusable.focus();
      });
    });

    document.querySelectorAll('[data-help-close]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        close(btn.closest('.modal-backdrop'));
      });
    });

    document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
      backdrop.addEventListener('click', function (event) {
        if (event.target === backdrop) close(backdrop);
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key !== 'Escape') return;
      var open = Array.prototype.slice.call(document.querySelectorAll('.modal-backdrop'))
        .filter(function (b) { return b.style.display !== 'none'; });
      if (open.length) close(open[open.length - 1]);
    });
  })();
