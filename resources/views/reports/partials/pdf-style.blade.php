{{-- Shared print styling for agent-scoped PDF exports. --}}
<style>
    @page { margin: 24px 22px 40px 22px; }

    * { box-sizing: border-box; }

    body {
        font-family: 'DejaVu Sans', sans-serif;
        font-size: 8.5px;
        color: #1e293b;
        line-height: 1.45;
        margin: 0;
    }

    table { border-collapse: collapse; }
    td, th { vertical-align: top; }

    /* ---------- Header ---------- */
    .head-table { width: 100%; border-bottom: 2.5px solid #1d4ed8; margin-bottom: 12px; }
    .head-table td { padding: 0 0 9px 0; }

    .brand {
        font-size: 8.5px;
        font-weight: bold;
        letter-spacing: 1.2px;
        color: #1d4ed8;
        text-transform: uppercase;
    }

    h1 { font-size: 17px; margin: 2px 0 2px; color: #0f172a; }

    .scope { font-size: 9px; color: #475569; margin: 0; }

    .meta { font-size: 7.5px; color: #64748b; line-height: 1.65; text-align: right; }
    .meta b { color: #334155; }

    /* ---------- Filter chips ---------- */
    .filters {
        background: #f1f5f9;
        border: 1px solid #e2e8f0;
        padding: 6px 9px;
        margin-bottom: 11px;
    }

    .ftitle {
        font-size: 6.8px;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        color: #64748b;
        font-weight: bold;
        margin-bottom: 3px;
    }

    .fchip {
        display: inline-block;
        background: #ffffff;
        border: 1px solid #cbd5e1;
        padding: 1.5px 5px;
        margin: 0 4px 2px 0;
        font-size: 7.3px;
    }

    .fchip b { color: #1d4ed8; }

    /* ---------- KPI ---------- */
    .kpi-table { width: 100%; margin-bottom: 12px; }
    .kpi-table td {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-top: 2px solid #1d4ed8;
        padding: 6px 7px;
    }
    .kpi-table td + td { border-left: none; }

    .kpi-k {
        font-size: 6.4px;
        text-transform: uppercase;
        letter-spacing: 0.6px;
        color: #64748b;
        font-weight: bold;
    }
    .kpi-v { font-size: 14.5px; font-weight: bold; color: #0f172a; line-height: 1.15; }
    .kpi-s { font-size: 6.4px; color: #94a3b8; }

    /* ---------- Sections ---------- */
    h2 {
        font-size: 9px;
        margin: 0 0 5px;
        padding-bottom: 2.5px;
        border-bottom: 1px solid #cbd5e1;
        color: #0f172a;
        text-transform: uppercase;
        letter-spacing: 0.6px;
    }

    .section { margin-bottom: 12px; }

    /* ---------- Data tables ---------- */
    table.data { width: 100%; }

    table.data th {
        background: #1e293b;
        color: #ffffff;
        font-size: 6.7px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        text-align: left;
        padding: 3.5px 4px;
        font-weight: bold;
    }

    table.data td {
        padding: 3px 4px;
        border-bottom: 1px solid #e8edf3;
        font-size: 7.3px;
    }

    table.data tr:nth-child(even) td { background: #fafbfc; }

    .num { text-align: right; }
    .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 6.8px; }

    /* ---------- Pills ---------- */
    .pill {
        display: inline-block;
        padding: 0.5px 4px;
        border-radius: 7px;
        font-size: 6.3px;
        font-weight: bold;
        color: #ffffff;
    }
    .pill.critical { background: #e11d48; }
    .pill.high { background: #ea580c; }
    .pill.medium { background: #ca8a04; }
    .pill.low { background: #0284c7; }
    .pill.info { background: #64748b; }
    .pill.success { background: #10b981; }

    .status {
        display: inline-block;
        padding: 0.5px 4px;
        border-radius: 2px;
        font-size: 6.3px;
        font-weight: bold;
        border: 1px solid #cbd5e1;
        background: #f8fafc;
        color: #334155;
    }

    /* ---------- Bar lists (inside real tables) ---------- */
    .barlist { width: 100%; }
    .barlist td { padding: 1.8px 0; font-size: 7.3px; border: none; background: none !important; }
    .barlist .lbl { width: 40%; color: #334155; }
    .barlist .bar { width: 46%; }
    .barlist .val { width: 14%; text-align: right; color: #0f172a; font-weight: bold; }

    .track { background: #e8edf3; height: 5px; width: 100%; }
    .fill { height: 5px; background: #1d4ed8; }

    /* ---------- Notes / footer ---------- */
    .note {
        background: #fffbeb;
        border: 1px solid #fde68a;
        padding: 5px 7px;
        font-size: 7.3px;
        color: #92400e;
        margin-bottom: 8px;
    }

    .footer {
        margin-top: 10px;
        border-top: 1px solid #cbd5e1;
        padding-top: 4px;
        font-size: 6.3px;
        color: #94a3b8;
    }
    .footer .left { float: left; width: 72%; }
    .footer .right { float: right; width: 26%; text-align: right; }

    .muted { color: #94a3b8; }

    .identity { width: 100%; margin-bottom: 12px; }
    .identity td { border: 1px solid #e2e8f0; padding: 4px 6px; font-size: 7.3px; }
    .identity .k {
        width: 13%;
        background: #f8fafc;
        color: #64748b;
        font-size: 6.4px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        font-weight: bold;
    }
</style>
