    {{-- MITRE technique detail off-canvas drawer --}}
    <div class="drawer-backdrop" id="mitreDrawer" style="display:none">
        <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="mtTitle">
            <header class="drawer-head">
                <div style="min-width:0;flex:1">
                    <div class="row" style="gap:8px">
                        <span class="badge neutral mono" id="mtCode">—</span>
                    </div>
                    <h3 id="mtTitle" style="margin:5px 0 0;font-size:13px;font-weight:600;line-height:1.4">Technique</h3>
                    <div class="fs-11 text-muted" id="mtName">—</div>
                </div>
                <button type="button" class="btn ghost sm" id="mtClose" aria-label="Close">&times;</button>
            </header>

            <div class="drawer-body">
                <div class="drawer-section" style="margin-top:0;padding-top:0;border-top:0">
                    <h4>Technique</h4>
                    <div class="detail-grid" id="mtStats"></div>
                </div>

                <div class="drawer-section">
                    <h4>Tactics</h4>
                    <div class="row wrap" style="gap:4px" id="mtTactics"></div>
                </div>

                <div class="drawer-section">
                    <h4>Top agents</h4>
                    <div class="detail-grid" id="mtAgents"></div>
                </div>

                <div class="drawer-section">
                    <p class="fs-11 text-muted" style="margin:0;line-height:1.6">
                        Counts are alerts whose rule carries this ATT&amp;CK technique ID in the selected window.
                    </p>
                    <a class="btn sm" id="mtExplore" style="margin-top:10px" href="#">Open in Alert Explorer</a>
                </div>
            </div>
        </aside>
    </div>
