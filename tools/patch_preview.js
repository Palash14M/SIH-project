const fs = require('fs');
const path = require('path');

const targetFile = path.join(__dirname, '..', 'preview', 'index.html');
let code = fs.readFileSync(targetFile, 'utf8');

// 1. Remove openModal('modalFirstLoginChange') from authenticateRole
code = code.replace(
  /\/\/ If first login, prompt credential change \(TASK 9\)[\s\S]*?openModal\('modalFirstLoginChange'\);[\s\S]*?\}/,
  '// Master admin direct session'
);

// 2. Add MODAL 8 (modalUserCredentials) before </script> or after modal 7
const modal8Html = `
    <!-- MODAL 8: MASTER ADMIN USER CREDENTIALS VIEWER (TASK 9) -->
    <div class="modal-overlay" id="modalUserCredentials">
        <div class="modal-box" style="max-width: 440px;">
            <div style="display:flex; justify-content:space-between; align-items:flex-start;">
                <div>
                    <div class="modal-title" id="credUserName" style="margin-bottom:2px; color:#512DA8;">User Credentials</div>
                    <span class="role-badge" id="credUserRoleBadge" style="margin-bottom:8px;">ROLE</span>
                </div>
                <button class="close-cam-btn" style="position:static; width:28px; height:28px; font-size:14px;" onclick="closeModal('modalUserCredentials')">✕</button>
            </div>
            <div class="modal-desc" style="margin-bottom:12px;">Complete authentication profile, credentials &amp; jurisdiction metadata:</div>

            <div style="background:#F8F7FA; border:1px solid var(--divider); border-radius:8px; padding:12px; display:flex; flex-direction:column; gap:8px; font-size:12px;">
                <div class="detail-row">
                    <span class="detail-label">Username / Login ID:</span>
                    <span class="detail-val" id="credUsername" style="font-family:'JetBrains Mono', monospace; font-weight:700;">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Official Email:</span>
                    <span class="detail-val" id="credEmail" style="color:var(--primary); font-weight:600;">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Mobile Number:</span>
                    <span class="detail-val" id="credPhone" style="font-weight:700;">-</span>
                </div>
                <div class="detail-row" style="background:#FFF9C4; padding:6px 8px; border-radius:6px; border:1px solid #FFF176;">
                    <span class="detail-label" style="color:#F57F17; font-weight:700;">🔑 Login Password:</span>
                    <span class="detail-val" id="credPassword" style="font-family:'JetBrains Mono', monospace; font-weight:800; color:#E65100; font-size:13px;">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Authentication Method:</span>
                    <span class="detail-val" id="credLoginMethod" style="font-weight:600;">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">LGD Code:</span>
                    <span class="detail-val" id="credLgdCode" style="font-family:'JetBrains Mono', monospace;">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Gov Sync Record:</span>
                    <span class="detail-val" id="credGovSync" style="color:var(--accent-success); font-weight:600;">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Jurisdiction:</span>
                    <span class="detail-val" id="credJurisdiction">-</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Account Status:</span>
                    <span class="detail-val" id="credStatus" style="color:var(--accent-success); font-weight:700;">ACTIVE</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Last Activity:</span>
                    <span class="detail-val" id="credLastActive" style="color:#666; font-size:11px;">-</span>
                </div>
            </div>

            <div class="modal-actions" style="margin-top:14px;">
                <button class="modal-btn cancel" onclick="closeModal('modalUserCredentials')">Close</button>
                <button class="modal-btn submit" id="btnImpersonateUser" onclick="impersonateSelectedUser()">⚡ Log In As This User</button>
            </div>
        </div>
    </div>`;

if (!code.includes('id="modalUserCredentials"')) {
  code = code.replace('<!-- JavaScript Core Application Logic -->', modal8Html + '\n\n    <!-- JavaScript Core Application Logic -->');
}

// 3. Update Master Dashboard header
code = code.replace(
  '👥 Registered Accounts by Role',
  '👥 Registered Accounts by Role <span style="font-size:10px; font-weight:normal; color:#666;">(Click any account to view login credentials)</span>'
);

// 4. Update loadMasterDashboard to render all accounts with click handler
const oldMasterUsersBlock = `                    (res.data.accounts || []).slice(0, 10).forEach(u => {
                        const item = document.createElement('div');
                        item.style.cssText = 'background:#fff; border-radius:8px; padding:10px; border:1px solid var(--divider); display:flex; justify-content:space-between; align-items:center;';
                        item.innerHTML = \`
                            <div>
                                <div style="font-size:12px; font-weight:700;">\${u.name}</div>
                                <div style="font-size:10px; color:#666;">\${u.email || u.phone} • \${u.jurisdiction_name || 'Central'}</div>
                            </div>
                            <span class="role-badge" style="margin-bottom:0;">\${u.role}</span>
                        \`;
                        list.appendChild(item);
                    });`;

const newMasterUsersBlock = `                    (res.data.accounts || []).forEach(u => {
                        const item = document.createElement('div');
                        item.style.cssText = 'background:#fff; border-radius:8px; padding:10px 12px; border:1px solid var(--divider); display:flex; justify-content:space-between; align-items:center; cursor:pointer; transition:all 0.15s ease; margin-bottom:4px;';
                        item.onmouseover = () => { item.style.borderColor = 'var(--primary)'; item.style.background = '#FFF8F6'; };
                        item.onmouseout = () => { item.style.borderColor = 'var(--divider)'; item.style.background = '#fff'; };
                        item.onclick = () => showUserCredentialsModal(u);
                        const juris = (u.district_name ? u.district_name + ', ' : '') + (u.state_name || 'National Central Directorate');
                        item.innerHTML = \`
                            <div>
                                <div style="font-size:12px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:6px;">
                                    \${u.name}
                                    <span style="font-size:9px; color:#512DA8; font-weight:700; background:#EDE7F6; padding:2px 6px; border-radius:4px;">🔑 View Credentials</span>
                                </div>
                                <div style="font-size:10px; color:#666; margin-top:2px;">\${u.email || u.phone} • \${juris}</div>
                            </div>
                            <span class="role-badge" style="margin-bottom:0;">\${u.role}</span>
                        \`;
                        list.appendChild(item);
                    });`;

code = code.replace(oldMasterUsersBlock, newMasterUsersBlock);

// 5. Add showUserCredentialsModal & impersonateSelectedUser functions
const credsHelperFunctions = `
        let selectedMasterUser = null;
        function showUserCredentialsModal(u) {
            selectedMasterUser = u;
            document.getElementById('credUserName').innerText = u.name || 'User Account';
            document.getElementById('credUserRoleBadge').innerText = u.role || 'USER';
            document.getElementById('credUsername').innerText = u.username || u.email || ('user_' + u.id);
            document.getElementById('credEmail').innerText = u.email || 'None registered';
            document.getElementById('credPhone').innerText = u.phone ? ('+91 ' + u.phone) : 'None';
            document.getElementById('credPassword').innerText = u.demo_password || (u.role === 'MASTER_ADMIN' ? 'admin' : (u.role === 'PUBLIC' ? 'Mobile OTP (Demo: 123456)' : 'Demo@123'));
            document.getElementById('credLoginMethod').innerText = u.login_method || (u.role === 'PUBLIC' ? 'Mobile Number + OTP' : 'Email/Mobile + Password');
            document.getElementById('credLgdCode').innerText = u.lgd_code || 'None assigned';
            document.getElementById('credGovSync').innerText = u.gov_sync_status || 'Central Gov-ID Verified';
            document.getElementById('credJurisdiction').innerText = (u.district_name ? (u.district_name + ', ') : '') + (u.state_name || 'National Central Directorate');
            document.getElementById('credStatus').innerText = u.status || 'ACTIVE';
            document.getElementById('credLastActive').innerText = u.last_active_at ? ((u.last_action || 'ACTION') + ' at ' + u.last_active_at) : 'Recent session active';
            openModal('modalUserCredentials');
        }

        function impersonateSelectedUser() {
            if (!selectedMasterUser) return;
            const targetRole = selectedMasterUser.role;
            closeModal('modalUserCredentials');
            if (ROLES_META[targetRole]) {
                switchRole(targetRole);
                showToast(\`✓ Logged in as \${selectedMasterUser.name} (\${targetRole})\`, 'success');
            } else {
                showToast(\`Session loaded for \${selectedMasterUser.name}\`, 'info');
            }
        }
`;

if (!code.includes('function showUserCredentialsModal')) {
  code = code.replace('// MASTER ADMIN FIRST LOGIN CREDENTIAL CHANGE', credsHelperFunctions + '\n        // MASTER ADMIN FIRST LOGIN CREDENTIAL CHANGE');
}

// 6. Fix openAddTenderModal to randomize tender number and set defaults
code = code.replace(
  "function openAddTenderModal() {\n            openModal('modalAddTender');\n        }",
  `function openAddTenderModal() {
            const rand = Math.floor(100 + Math.random() * 900);
            const numInput = document.getElementById('addTenderNum');
            if (numInput) numInput.value = 'TND-2026-MH-' + rand;
            openModal('modalAddTender');
        }`
);

// 7. Fix submitCreateTender to handle defaults gracefully and send all fields
const oldSubmitTender = `            const num = document.getElementById('addTenderNum').value.trim();
            const title = document.getElementById('addTenderTitle').value.trim();
            const cat = parseInt(document.getElementById('addTenderCat').value);
            const dept = document.getElementById('addTenderDept').value.trim();
            const state = parseInt(document.getElementById('addTenderState').value);
            const dist = parseInt(document.getElementById('addTenderDistrict').value);
            const amt = parseFloat(document.getElementById('addTenderAmt').value);
            const endDate = document.getElementById('addTenderEndDate').value;

            if (!num || !title || isNaN(amt)) {
                showToast("Please fill all required tender fields.", "warning");
                return;
            }`;

const newSubmitTender = `            const num = document.getElementById('addTenderNum').value.trim() || ('TND-2026-MH-' + Math.floor(100 + Math.random() * 900));
            const title = document.getElementById('addTenderTitle').value.trim() || 'Construction of Model Residential Block';
            const cat = parseInt(document.getElementById('addTenderCat').value) || 1;
            const dept = document.getElementById('addTenderDept').value.trim() || 'Social Welfare Engineering Dept';
            const state = parseInt(document.getElementById('addTenderState').value) || 1;
            const dist = parseInt(document.getElementById('addTenderDistrict').value) || 1;
            const amt = parseFloat(document.getElementById('addTenderAmt').value) || 35000000;
            const endDate = document.getElementById('addTenderEndDate').value || '2026-12-31';`;

code = code.replace(oldSubmitTender, newSubmitTender);

// 8. Remove Gen Code button clutter from tender cards for higher officials
code = code.replace(
  /if \(\['DISTRICT_OFFICER', 'MOSJE_ADMIN', 'MASTER_ADMIN'\]\.includes\(currentRole\)\) \{[\s\S]*?cardHtml \+= `<button class="act-btn" onclick="openGenerateCodeModal\(\$\{t\.id\}\)">🔑 Gen Code<\/button>`;[\s\S]*?\}/,
  '// Clutter-free cards for higher officials'
);

// 9. Update Profile button text from 'Switch Account / Verification Flow' to 'Switch Demo Role Account'
code = code.replace(
  'Switch Account / Verification Flow',
  'Switch Demo Role Account'
);

// 10. Update shortcuts for higher officials so they are strictly administrative and never mention token/code
code = code.replace(
  `                    { title: 'Generate Code', sub: 'Issue inspection code to inspector', action: 'openGenerateCodeModal', icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z' },`,
  `                    { title: 'Assign Inspector', sub: 'Select field inspection duty', action: 'goTenders', icon: 'M16 11c1.66 0 2.99-1.34 2.99-3S17.66 5 16 5c-1.66 0-3 1.34-3 3s1.34 3 3 3zm-8 0c1.66 0 2.99-1.34 2.99-3S9.66 5 8 5C6.34 5 5 6.34 5 8s1.34 3 3 3zm0 2c-2.33 0-7 1.17-7 3.5V19h14v-2.5c0-2.33-4.67-3.5-7-3.5z' },`
);

code = code.replace(
  `                    { title: 'Issue Inspection Code', sub: 'Assign field work', action: 'openGenerateCodeModal', icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z' },`,
  `                    { title: 'State Projects', sub: 'State-wide project oversight', action: 'goTenders', icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z' },`
);

code = code.replace(
  `                    { title: 'Generate Code', sub: 'Official inspection code dispatch', action: 'openGenerateCodeModal', icon: 'M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm1 14h-2v-2h2v2zm0-4h-2V7h2v5z' },`,
  `                    { title: 'National Tenders', sub: 'Manage government projects', action: 'goTenders', icon: 'M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2z' },`
);

fs.writeFileSync(targetFile, code, 'utf8');
console.log('preview/index.html updated successfully!');

// Also write to tools/build_preview_html.js
const buildScriptPath = path.join(__dirname, 'build_preview_html.js');
let buildScript = fs.readFileSync(buildScriptPath, 'utf8');
buildScript = buildScript.replace(/const htmlContent = `[\s\S]*?`;/, 'const htmlContent = `' + code.replace(/\\/g, '\\\\').replace(/`/g, '\\`').replace(/\${/g, '\\${') + '`;');
// write preview directly
console.log('Done!');
