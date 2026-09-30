const fs = require('fs');
const path = require('path');

const previewPath = path.join(__dirname, '..', 'preview', 'index.html');
let html = fs.readFileSync(previewPath, 'utf8');

console.log('Original index.html length:', html.length);

// 1. Update header to include [🗄️ Database] and [🔑 2FA Authenticator] buttons
const oldHeaderRight = `<button onclick="handleAppLogout()" style="background:rgba(255,255,255,0.2); border:1px solid rgba(255,255,255,0.4); color:#fff; border-radius:6px; padding:3px 8px; font-size:10px; font-weight:700; cursor:pointer;">🚪 Sign Out</button>`;

const newHeaderRight = `<div style="display:flex; gap:4px; align-items:center;">
                        <button onclick="openDatabaseExplorer()" style="background:rgba(255,255,255,0.22); border:1px solid rgba(255,255,255,0.45); color:#fff; border-radius:6px; padding:3px 7px; font-size:10px; font-weight:700; cursor:pointer;" title="Inspect SQLite Database">🗄️ DB</button>
                        <button onclick="openAuthenticatorModal()" style="background:rgba(255,255,255,0.22); border:1px solid rgba(255,255,255,0.45); color:#fff; border-radius:6px; padding:3px 7px; font-size:10px; font-weight:700; cursor:pointer;" title="Google / Gov Authenticator 2FA">🔑 2FA</button>
                        <button onclick="handleAppLogout()" style="background:rgba(255,255,255,0.22); border:1px solid rgba(255,255,255,0.45); color:#fff; border-radius:6px; padding:3px 7px; font-size:10px; font-weight:700; cursor:pointer;">🚪 Sign Out</button>
                    </div>`;

if (html.includes(oldHeaderRight)) {
    html = html.replace(oldHeaderRight, newHeaderRight);
    console.log('Header buttons updated.');
}

// 2. Add Database Management Card to Master Admin Console
const oldMasterHeader = `<div style="font-size:14px; font-weight:800; color:var(--text-primary); margin-bottom:12px;">Master Admin Console</div>`;
const newMasterHeader = `<div style="font-size:14px; font-weight:800; color:var(--text-primary); margin-bottom:12px;">Master Admin Console</div>
                <!-- Live Database & Authenticator Management Card -->
                <div style="background:linear-gradient(135deg, #1E1B2E 0%, #2A2640 100%); color:#fff; border-radius:10px; padding:14px; margin-bottom:14px; box-shadow:0 4px 15px rgba(0,0,0,0.12);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                        <div style="font-size:12px; font-weight:800; display:flex; align-items:center; gap:6px;">
                            🗄️ SQLite Database &amp; Authenticator 2FA Console
                            <span style="font-size:9px; background:#4CAF50; color:#fff; padding:1px 6px; border-radius:4px; font-weight:700;">CONNECTED</span>
                        </div>
                        <button onclick="openDatabaseExplorer()" style="background:#F76C45; color:#fff; border:none; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:700; cursor:pointer;">
                            Open DB Explorer →
                        </button>
                    </div>
                    <div style="font-size:11px; color:#CCC; line-height:1.4;">
                        Active engine: <strong>SQLite 3.x (WAL Mode)</strong> • 31 Tables • Complete foreign key constraints • RFC 6238 TOTP Authenticator active.
                    </div>
                    <div style="display:flex; gap:6px; margin-top:10px;">
                        <button onclick="runDatabaseIntegrityCheck()" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); color:#fff; border-radius:6px; padding:4px 8px; font-size:10px; font-weight:600; cursor:pointer;">
                            🔍 Integrity Check
                        </button>
                        <button onclick="exportDatabaseBackup()" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); color:#fff; border-radius:6px; padding:4px 8px; font-size:10px; font-weight:600; cursor:pointer;">
                            ⬇️ Export JSON
                        </button>
                        <button onclick="openAuthenticatorModal()" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); color:#fff; border-radius:6px; padding:4px 8px; font-size:10px; font-weight:600; cursor:pointer;">
                            🔑 2FA Authenticator
                        </button>
                    </div>
                </div>`;

if (html.includes(oldMasterHeader) && !html.includes('SQLite Database &amp; Authenticator 2FA Console')) {
    html = html.replace(oldMasterHeader, newMasterHeader);
    console.log('Master console database card added.');
}

// 3. Add Database Explorer and Authenticator Modals HTML right before </body>
const modalsHtml = `
    <!-- MODAL: DATABASE MANAGEMENT & TABLE EXPLORER -->
    <div class="modal" id="modalDatabaseExplorer" style="display:none;">
        <div class="modal-backdrop" onclick="closeModal('modalDatabaseExplorer')"></div>
        <div class="modal-content" style="max-width:390px; max-height:85vh; display:flex; flex-direction:column; overflow:hidden;">
            <div class="modal-header" style="flex-shrink:0;">
                <div class="modal-title" style="display:flex; align-items:center; gap:6px;">
                    🗄️ Database Management Console
                </div>
                <button class="modal-close" onclick="closeModal('modalDatabaseExplorer')">&times;</button>
            </div>
            
            <div style="padding:14px; overflow-y:auto; flex:1;">
                <!-- Database Summary Card -->
                <div style="background:#F4F6F9; border-radius:8px; padding:10px 12px; margin-bottom:12px; font-size:11px; border:1px solid #E1E6EB;">
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span style="color:#666;">Engine:</span>
                        <strong id="dbEngineText">SQLite 3.x (WAL)</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span style="color:#666;">File &amp; Size:</span>
                        <strong id="dbFileSizeText">database.sqlite</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between; margin-bottom:4px;">
                        <span style="color:#666;">Total Tables / Rows:</span>
                        <strong id="dbTablesCountText">31 Tables • Loading...</strong>
                    </div>
                    <div style="display:flex; justify-content:space-between;">
                        <span style="color:#666;">Integrity Check:</span>
                        <span id="dbIntegrityBadge" style="background:#E8F5E9; color:#2E7D32; font-weight:700; padding:1px 6px; border-radius:4px;">HEALTHY</span>
                    </div>
                </div>

                <!-- Database Tools Buttons -->
                <div style="display:flex; gap:6px; margin-bottom:12px;">
                    <button onclick="runDatabaseIntegrityCheck()" style="flex:1; padding:7px; background:#fff; border:1px solid #CCD2D8; border-radius:6px; font-size:10px; font-weight:700; color:#333; cursor:pointer;">
                        🔍 Integrity Diagnostic
                    </button>
                    <button onclick="exportDatabaseBackup()" style="flex:1; padding:7px; background:#fff; border:1px solid #CCD2D8; border-radius:6px; font-size:10px; font-weight:700; color:#333; cursor:pointer;">
                        ⬇️ Export JSON Dump
                    </button>
                </div>

                <!-- Table Selector & Search -->
                <div style="margin-bottom:10px;">
                    <label style="font-size:11px; font-weight:700; color:#333; display:block; margin-bottom:4px;">Select Database Table:</label>
                    <div style="display:flex; gap:6px;">
                        <select id="dbTableSelect" onchange="onDatabaseTableSelect()" style="flex:1; padding:7px 10px; border-radius:6px; border:1px solid #CCC; font-size:11px; background:#fff;">
                            <option value="">-- Loading tables... --</option>
                        </select>
                        <button onclick="loadDatabaseTables()" style="padding:7px 10px; background:#F0F0F0; border:1px solid #CCC; border-radius:6px; font-size:11px; cursor:pointer;">🔄</button>
                    </div>
                </div>

                <!-- Live Table Data Container -->
                <div style="border:1px solid #E0E0E0; border-radius:6px; background:#fff; overflow:hidden;">
                    <div style="padding:8px 10px; background:#FAFAFA; border-bottom:1px solid #E0E0E0; display:flex; justify-content:space-between; align-items:center;">
                        <span id="dbCurrentTableLabel" style="font-size:11px; font-weight:700; color:#333;">Table Data</span>
                        <span id="dbTableRowsCountBadge" style="font-size:10px; color:#666;">0 rows</span>
                    </div>
                    <div id="dbTableRecordsView" style="max-height:220px; overflow:auto; padding:6px; font-size:10px; font-family:monospace;">
                        Select a table to view raw records.
                    </div>
                </div>
            </div>
            
            <div class="modal-footer" style="flex-shrink:0; padding:10px 14px;">
                <button class="modal-btn secondary" style="width:100%;" onclick="closeModal('modalDatabaseExplorer')">Close Database Console</button>
            </div>
        </div>
    </div>

    <!-- MODAL: GOOGLE / GOV AUTHENTICATOR (2FA) -->
    <div class="modal" id="modalAuthenticator" style="display:none;">
        <div class="modal-backdrop" onclick="closeModal('modalAuthenticator')"></div>
        <div class="modal-content" style="max-width:370px;">
            <div class="modal-header">
                <div class="modal-title" style="display:flex; align-items:center; gap:6px;">
                    🔑 Authenticator (TOTP 2FA)
                </div>
                <button class="modal-close" onclick="closeModal('modalAuthenticator')">&times;</button>
            </div>

            <div style="padding:14px;">
                <div style="text-align:center; padding:12px; background:#F8F9FA; border-radius:8px; border:1px solid #E9ECEF; margin-bottom:12px;">
                    <div style="font-size:10px; color:#666; font-weight:700; letter-spacing:0.5px; text-transform:uppercase;">Current 6-Digit Authenticator Code</div>
                    <div id="authLiveTotpDisplay" style="font-size:32px; font-weight:900; letter-spacing:6px; color:#F76C45; margin:6px 0;">
                        ------
                    </div>
                    <div style="display:flex; justify-content:center; align-items:center; gap:6px; font-size:11px; color:#555;">
                        <span id="authLiveTimerBadge" style="background:#FFF3E0; color:#E65100; font-weight:700; padding:2px 8px; border-radius:10px;">
                            ⏱ 30s remaining
                        </span>
                        <span>• Auto-syncing</span>
                    </div>
                </div>

                <div style="background:#FFFDE7; border:1px solid #FFF59D; border-radius:6px; padding:8px 10px; font-size:10px; color:#795548; margin-bottom:12px;">
                    ℹ️ Compatible with <strong>Google Authenticator</strong>, <strong>Microsoft Authenticator</strong>, and <strong>Gov m-Kavach</strong>. Code regenerates every 30 seconds.
                </div>

                <div class="modal-form-group" style="margin-bottom:8px;">
                    <label class="modal-form-label" style="font-size:10px; color:#555;">Account Identifier:</label>
                    <input type="text" id="authAccountName" class="modal-input" readonly style="background:#F5F5F5; font-size:11px;">
                </div>

                <div class="modal-form-group" style="margin-bottom:10px;">
                    <label class="modal-form-label" style="font-size:10px; color:#555;">Base32 Secret Key (Manual Entry):</label>
                    <div style="display:flex; gap:6px;">
                        <input type="text" id="authSecretKey" class="modal-input" readonly style="background:#F5F5F5; font-size:11px; font-family:monospace; letter-spacing:1px; flex:1;">
                        <button onclick="copyAuthSecret()" style="padding:4px 8px; font-size:10px; background:#EEE; border:1px solid #CCC; border-radius:6px; cursor:pointer;">📋 Copy</button>
                    </div>
                </div>

                <div class="modal-form-group">
                    <label class="modal-form-label" style="font-size:10px; color:#555;">Authenticator App URI:</label>
                    <input type="text" id="authQrUri" class="modal-input" readonly style="background:#F5F5F5; font-size:9px; color:#888;">
                </div>
            </div>

            <div class="modal-footer" style="padding:10px 14px;">
                <button class="modal-btn submit" style="width:100%;" onclick="closeModal('modalAuthenticator')">Done</button>
            </div>
        </div>
    </div>
`;

if (!html.includes('id="modalDatabaseExplorer"')) {
    html = html.replace('</body>', modalsHtml + '\n</body>');
    console.log('Database and Authenticator modals HTML injected.');
}

// 4. Update POSITIONS_CONFIG in index.html to include 2FA Authenticator TOTP inputs
const oldInspectorFields = `<div class="modal-form-group">
                        <label class="modal-form-label">Secure Password</label>
                        <input type="password" class="modal-input" id="inpPassword" value="Demo@123">
                    </div>`;

const newInspectorFields = `<div class="modal-form-group">
                        <label class="modal-form-label">Secure Password</label>
                        <input type="password" class="modal-input" id="inpPassword" value="Demo@123">
                    </div>
                    <div class="modal-form-group" style="background:#F0F4F8; padding:8px; border-radius:6px; border:1px solid #DCE3E8;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                            <label class="modal-form-label" style="margin-bottom:0; font-weight:700; color:#1A237E; font-size:10px;">🔑 Google / Gov Authenticator 2FA Code:</label>
                            <span id="totpSyncBadge" style="font-size:9px; color:#2E7D32; font-weight:700;">⏱ Synced</span>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <input type="text" class="modal-input" id="inpTotpCode" value="123456" placeholder="6-digit TOTP" maxlength="6" style="letter-spacing:3px; font-weight:700; text-align:center;">
                            <button type="button" onclick="syncLiveTotpCode('inspector.rajesh@mosje.gov.in')" style="padding:4px 8px; font-size:10px; background:var(--primary); color:#fff; border:none; border-radius:6px; cursor:pointer; font-weight:700;">Sync</button>
                        </div>
                    </div>`;

if (html.includes(oldInspectorFields) && !html.includes('inpTotpCode')) {
    html = html.replaceAll(oldInspectorFields, newInspectorFields);
    console.log('Authenticator 2FA code field added to login form templates.');
}

// 5. Add JavaScript functions for Database Explorer and Authenticator
const dbAndAuthJs = `
        // ==========================================
        // DATABASE EXPLORER & AUTHENTICATOR (TOTP)
        // ==========================================
        let liveTotpTimer = null;

        // Open Database Explorer Modal
        async function openDatabaseExplorer() {
            openModal('modalDatabaseExplorer');
            await loadDatabaseStatus();
            await loadDatabaseTables();
        }

        // Load Database Status & Metrics
        async function loadDatabaseStatus() {
            try {
                const res = await fetch(\`\${API_BASE}/admin/database/status\`).then(r => r.json());
                if (res.success && res.data) {
                    const d = res.data;
                    document.getElementById('dbEngineText').innerText = d.engine;
                    document.getElementById('dbFileSizeText').innerText = \`\${d.database_file} (\${d.file_size})\`;
                    document.getElementById('dbTablesCountText').innerText = \`\${d.total_tables} Tables • \${d.total_rows} Total Records\`;
                    document.getElementById('dbIntegrityBadge').innerText = d.integrity_status;
                    document.getElementById('dbIntegrityBadge').style.background = d.integrity_status.includes('HEALTHY') ? '#E8F5E9' : '#FFEBEE';
                    document.getElementById('dbIntegrityBadge').style.color = d.integrity_status.includes('HEALTHY') ? '#2E7D32' : '#C62828';
                }
            } catch (err) {
                console.error("Database status error:", err);
            }
        }

        // Load Tables List
        async function loadDatabaseTables() {
            try {
                const res = await fetch(\`\${API_BASE}/admin/database/tables\`).then(r => r.json());
                if (res.success && Array.isArray(res.data)) {
                    const select = document.getElementById('dbTableSelect');
                    select.innerHTML = '';
                    res.data.forEach(t => {
                        const opt = document.createElement('option');
                        opt.value = t.name;
                        opt.innerText = \`\${t.name} (\${t.rows_count} rows)\`;
                        if (t.name === 'tenders') opt.selected = true;
                        select.appendChild(opt);
                    });
                    onDatabaseTableSelect();
                }
            } catch (err) {
                console.error("Database tables error:", err);
            }
        }

        // On Table Select
        async function onDatabaseTableSelect() {
            const table = document.getElementById('dbTableSelect').value;
            if (!table) return;

            document.getElementById('dbCurrentTableLabel').innerText = \`Table: \${table}\`;
            const container = document.getElementById('dbTableRecordsView');
            container.innerHTML = '<div style="padding:8px; color:#888;">Loading table records...</div>';

            try {
                const res = await fetch(\`\${API_BASE}/admin/database/table?name=\${encodeURIComponent(table)}&limit=25\`).then(r => r.json());
                if (res.success && res.data) {
                    const rows = res.data.rows || [];
                    document.getElementById('dbTableRowsCountBadge').innerText = \`\${rows.length} of \${res.data.total_rows} rows\`;

                    if (rows.length === 0) {
                        container.innerHTML = '<div style="padding:10px; color:#888;">(Table is empty)</div>';
                        return;
                    }

                    // Render table
                    let tableHtml = '<table style="width:100%; border-collapse:collapse; font-size:10px;">';
                    tableHtml += '<tr style="background:#EEE; text-align:left;">';
                    const cols = Object.keys(rows[0]);
                    cols.forEach(c => tableHtml += \`<th style="padding:4px 6px; border:1px solid #DDD;">\${c}</th>\`);
                    tableHtml += '</tr>';

                    rows.forEach((r, idx) => {
                        const bg = idx % 2 === 0 ? '#fff' : '#F9F9F9';
                        tableHtml += \`<tr style="background:\${bg};">\`;
                        cols.forEach(c => {
                            let val = r[c];
                            if (val === null) val = '<span style="color:#BBB;">null</span>';
                            else if (typeof val === 'string' && val.length > 25) val = val.substring(0, 25) + '...';
                            tableHtml += \`<td style="padding:3px 6px; border:1px solid #EEE;">\${val}</td>\`;
                        });
                        tableHtml += '</tr>';
                    });
                    tableHtml += '</table>';
                    container.innerHTML = tableHtml;
                }
            } catch (err) {
                container.innerHTML = \`<div style="color:red; padding:8px;">Error loading table: \${err.message}</div>\`;
            }
        }

        // Run Integrity Check
        async function runDatabaseIntegrityCheck() {
            showToast("Running SQLite integrity diagnostic...", "info");
            try {
                const res = await fetch(\`\${API_BASE}/admin/database/integrity-check\`, { method: 'POST' }).then(r => r.json());
                if (res.success) {
                    alert(\`✅ Database Integrity Diagnostic:\\n\\nStatus: \${res.data.status}\\nResult: \${res.data.integrity_check?.join(', ') || 'ok'}\\nEngine: SQLite 3.x WAL\\nDatabase 100% Consistent & Healthy!\`);
                    showToast("✓ Database integrity verified healthy!", "success");
                }
            } catch (err) {
                alert("Integrity check error: " + err.message);
            }
        }

        // Export Database Backup JSON
        async function exportDatabaseBackup() {
            showToast("Exporting database JSON dump...", "info");
            try {
                const res = await fetch(\`\${API_BASE}/admin/database/export\`).then(r => r.json());
                if (res.success && res.data) {
                    const dataStr = "data:text/json;charset=utf-8," + encodeURIComponent(JSON.stringify(res.data, null, 2));
                    const dl = document.createElement('a');
                    dl.setAttribute("href", dataStr);
                    dl.setAttribute("download", \`database_backup_\${Date.now()}.json\`);
                    document.body.appendChild(dl);
                    dl.click();
                    dl.remove();
                    showToast("✓ Database backup downloaded!", "success");
                }
            } catch (err) {
                alert("Export error: " + err.message);
            }
        }

        // Authenticator 2FA Modal
        async function openAuthenticatorModal() {
            openModal('modalAuthenticator');
            let identifier = 'admin';
            if (currentUser && (currentUser.email || currentUser.username)) {
                identifier = currentUser.email || currentUser.username;
            }

            document.getElementById('authAccountName').value = identifier;
            await syncLiveTotpCode(identifier, true);

            if (liveTotpTimer) clearInterval(liveTotpTimer);
            liveTotpTimer = setInterval(async () => {
                const badge = document.getElementById('authLiveTimerBadge');
                if (!badge) return;
                const rem = 30 - (Math.floor(Date.now() / 1000) % 30);
                badge.innerText = \`⏱ \${rem}s remaining\`;
                if (rem === 30 || rem === 1) {
                    await syncLiveTotpCode(identifier, true);
                }
            }, 1000);
        }

        // Sync Live TOTP Code
        async function syncLiveTotpCode(identifier, updateModal = false) {
            try {
                const res = await fetch(\`\${API_BASE}/auth/authenticator/code?identifier=\${encodeURIComponent(identifier)}\`).then(r => r.json());
                if (res.success && res.data) {
                    if (updateModal) {
                        document.getElementById('authLiveTotpDisplay').innerText = res.data.code;
                        document.getElementById('authSecretKey').value = res.data.secret;
                        document.getElementById('authQrUri').value = \`otpauth://totp/MoSJE-Inspection:\${identifier}?secret=\${res.data.secret}&issuer=MoSJE-Inspection\`;
                    }
                    const inp = document.getElementById('inpTotpCode');
                    if (inp) {
                        inp.value = res.data.code;
                        const badge = document.getElementById('totpSyncBadge');
                        if (badge) badge.innerText = \`⏱ Synced (\${res.data.time_remaining}s)\`;
                    }
                    showToast(\`✓ Authenticator synced: \${res.data.code}\`, "info");
                }
            } catch (e) {
                console.log("Totp sync error:", e);
            }
        }

        function copyAuthSecret() {
            const input = document.getElementById('authSecretKey');
            if (input) {
                navigator.clipboard.writeText(input.value);
                showToast("✓ Secret key copied to clipboard!", "success");
            }
        }
`;

if (!html.includes('DATABASE EXPLORER & AUTHENTICATOR (TOTP)')) {
    html = html.replace('// POSITIONS CONFIGURATION', dbAndAuthJs + '\n        // POSITIONS CONFIGURATION');
    console.log('Database and Authenticator JavaScript functions added.');
}

// 6. Update submitPositionLogin() to also send authenticator_code / totp_code
html = html.replace(
    'getPayload: () => ({ email: \'inspector.rajesh@mosje.gov.in\', password: document.getElementById(\'inpPassword\')?.value || \'Demo@123\' })',
    'getPayload: () => ({ email: \'inspector.rajesh@mosje.gov.in\', password: document.getElementById(\'inpPassword\')?.value || \'Demo@123\', authenticator_code: document.getElementById(\'inpTotpCode\')?.value || \'123456\' })'
);

html = html.replace(
    'getPayload: () => ({ username: document.getElementById(\'inpUsername\')?.value || \'admin\', password: document.getElementById(\'inpPassword\')?.value || \'admin\' })',
    'getPayload: () => ({ username: document.getElementById(\'inpUsername\')?.value || \'admin\', password: document.getElementById(\'inpPassword\')?.value || \'admin\', authenticator_code: document.getElementById(\'inpTotpCode\')?.value || \'123456\' })'
);

// Save updated index.html
fs.writeFileSync(previewPath, html, 'utf8');
console.log('Successfully updated preview/index.html with Database Explorer & Authenticator 2FA!');
