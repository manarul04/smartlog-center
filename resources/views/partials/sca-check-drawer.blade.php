    {{-- Read-only SCA check detail. Kept separate from the handling drawer so
         a passed check (which has no Handle action) can still be inspected. --}}
    <div class="drawer-backdrop" id="scaCheckDrawer" style="display:none">
        <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="sccTitle">
            <header class="drawer-head">
                <div style="min-width:0;flex:1">
                    <div class="row" style="gap:8px">
                        <span class="badge info" id="sccResult">—</span>
                        <span class="mono fs-11 text-muted" id="sccId">—</span>
                    </div>
                    <h3 id="sccTitle" style="margin:5px 0 0;font-size:13px;font-weight:600;line-height:1.4">Check</h3>
                </div>
                <button type="button" class="btn ghost sm" id="sccClose" aria-label="Close">&times;</button>
            </header>

            <div class="drawer-body">
                <div class="detail-grid" id="sccFacts"></div>

                <div class="drawer-section" id="sccRationaleSection" style="display:none">
                    <h4>Why this check exists</h4>
                    <p class="fs-12 text-secondary" id="sccRationale" style="margin:0;line-height:1.6"></p>
                </div>

                <div class="drawer-section">
                    <h4>Description</h4>
                    <p class="fs-12 text-secondary" id="sccDescription" style="margin:0;line-height:1.6;white-space:pre-wrap"></p>
                </div>

                <div class="drawer-section" id="sccRemediationSection" style="display:none">
                    <h4>Remediation</h4>
                    <p class="fs-12" id="sccRemediation" style="margin:0 0 8px;line-height:1.6"></p>
                    <pre class="json" id="sccCommand" style="display:none;max-height:150px"></pre>
                    <pre class="json" id="sccDirectory" style="display:none;max-height:150px"></pre>
                </div>

                <div class="drawer-section">
                    <p class="fs-11 text-muted" style="margin:0;line-height:1.6">
                        Result and policy come from the SCA scan reported by the agent. Handling state is
                        tracked separately, so a finding can be reviewed without changing the check result.
                    </p>
                </div>
            </div>
        </aside>
    </div>
