    {{-- Compliance control detail off-canvas drawer --}}
    <div class="drawer-backdrop" id="controlDrawer" style="display:none">
        <aside class="drawer" role="dialog" aria-modal="true" aria-labelledby="cdTitle"
               data-compliance-catalogue="{{ json_encode(\App\Support\Compliance::catalogue(), JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP) }}">
            <header class="drawer-head">
                <div style="min-width:0;flex:1">
                    <div class="row" style="gap:8px">
                        <span class="badge neutral mono" id="cdFramework">—</span>
                        <span class="mono fs-11 text-muted" id="cdCode">—</span>
                    </div>
                    <h3 id="cdTitle" style="margin:5px 0 0;font-size:13px;font-weight:600;line-height:1.4">Control</h3>
                </div>
                <button type="button" class="btn ghost sm" id="cdClose" aria-label="Close">&times;</button>
            </header>

            <div class="drawer-body">
                <div class="compliance-detail" style="margin-top:0">
                    <div class="compliance-detail-title" id="cdTitleText">—</div>
                    <p class="compliance-detail-summary" id="cdSummary">—</p>
                    <p class="compliance-detail-framework" id="cdFwNote" style="display:none"></p>
                </div>

                <div class="drawer-section">
                    <h4>Observed in this period</h4>
                    <div class="detail-grid" id="cdStats"></div>
                </div>

                <div class="drawer-section">
                    <h4>Where this comes from</h4>
                    <p class="fs-11 text-muted" style="margin:0;line-height:1.6">
                        Wazuh attaches these control codes to the rule that fired. A high alert count means
                        the control is being exercised often, not that it failed an audit.
                    </p>
                    <a class="btn sm" id="cdDrill" style="margin-top:10px" href="#">Drill down to alerts</a>
                </div>
            </div>
        </aside>
    </div>
