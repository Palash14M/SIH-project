const fs = require('fs');
const path = require('path');

const previewPath = path.join(__dirname, '..', 'preview', 'index.html');
let html = fs.readFileSync(previewPath, 'utf8');

// 1. Remove all crown symbols 👑
html = html.replace(/👑\s*/g, '');

// 2. Add PWA manifest link and mobile meta tags in <head>
if (!html.includes('rel="manifest"')) {
  const pwaMeta = `
    <!-- Mobile App PWA Meta Tags -->
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#F76C45">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="MoSJE Inspection">
    <link rel="apple-touch-icon" href="/watermark_logo.png">
`;
  html = html.replace('<!-- Inter Font -->', pwaMeta + '\n    <!-- Inter Font -->');
}

// 3. Update CSS for Responsive Mobile Fullscreen on actual phone screens
const responsiveCss = `
        /* Responsive Mobile Phone Mode */
        @media (max-width: 600px) {
            body {
                padding: 0 !important;
                background: var(--background) !important;
            }
            .phone-frame {
                width: 100vw !important;
                height: 100vh !important;
                height: 100dvh !important;
                border-radius: 0 !important;
                border: none !important;
                box-shadow: none !important;
            }
            .status-bar {
                display: none !important;
            }
            .preview-controls {
                display: none !important;
            }
        }

        .login-screen-container {
            display: flex;
            flex-direction: column;
            flex: 1;
            background: #F8F7FA;
            overflow-y: auto;
            padding: 16px 14px;
        }

        .position-select-field {
            width: 100%;
            padding: 12px 14px;
            font-size: 14px;
            font-weight: 700;
            color: #110B0A;
            background: #FFF8F6;
            border: 2px solid var(--primary);
            border-radius: 8px;
            outline: none;
            cursor: pointer;
            box-shadow: 0 2px 8px rgba(247, 108, 69, 0.15);
        }

        .btn-position-login {
            width: 100%;
            padding: 13px;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 12px rgba(247, 108, 69, 0.3);
            transition: all 0.2s ease;
        }

        .btn-position-login:hover {
            background: var(--primary-dark);
            transform: translateY(-1px);
        }
`;

if (!html.includes('Responsive Mobile Phone Mode')) {
  html = html.replace('/* Phone Mockup Frame */', responsiveCss + '\n        /* Phone Mockup Frame */');
}

// 4. Remove .preview-controls (the row of position buttons on top)
// Replace with a sleek top notification banner for desktop visitors
const newTopBar = `
    <!-- Desktop Simulator Top Bar (Hidden on Mobile) -->
    <div style="width:100%; max-width:410px; display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; color:#fff; font-size:11px;">
        <span style="font-weight:700; color:#F76C45;">📲 MoSJE Smart Inspection App</span>
        <button onclick="installOrDownloadApp()" style="background:rgba(255,255,255,0.15); border:1px solid rgba(255,255,255,0.3); color:#fff; border-radius:6px; padding:4px 10px; font-size:10px; font-weight:700; cursor:pointer;">
            ⬇ Download Mobile App
        </button>
    </div>
`;

html = html.replace(
  /<div class="preview-controls">[\s\S]*?<\/div>\s*<\/div>/,
  newTopBar
);

// 5. Update .app-header to include an explicit [Logout] button
const headerOld = `<div class="app-header">
                <div class="ministry-label">MoSJE • PM-AJAY &amp; INFRA MONITOR</div>
                <div class="user-greeting" id="userGreeting">Namaste, Rajesh M.</div>
                <div class="role-badge" id="roleBadge">Field Inspector • Nagpur Division</div>`;

const headerNew = `<div class="app-header" id="appHeader" style="display:none;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <div class="ministry-label">MoSJE • PM-AJAY &amp; INFRA MONITOR</div>
                    <button onclick="handleAppLogout()" style="background:rgba(255,255,255,0.2); border:1px solid rgba(255,255,255,0.4); color:#fff; border-radius:6px; padding:3px 8px; font-size:10px; font-weight:700; cursor:pointer;">🚪 Sign Out</button>
                </div>
                <div class="user-greeting" id="userGreeting">Namaste, Official</div>
                <div class="role-badge" id="roleBadge">Position</div>`;

html = html.replace(headerOld, headerNew);

// 6. Insert SCREEN 0 (Login & Position Selector) right after <div class="app-body" id="appBody">
const loginScreenHtml = `
            <!-- SCREEN 0: LOGIN & POSITION SELECTOR (FIRST PAGE) -->
            <div id="screenLogin" class="login-screen-container" style="display: flex;">
                <!-- Header Logo & Emblem -->
                <div style="text-align:center; padding: 14px 8px 10px;">
                    <img src="/watermark_logo.png" style="width:52px; height:52px; object-fit:contain; margin-bottom:6px;" alt="Gov Emblem">
                    <div style="font-size:10px; font-weight:800; letter-spacing:1px; color:#555;">GOVERNMENT OF INDIA</div>
                    <div style="font-size:15px; font-weight:800; color:#110B0A; line-height:1.2; margin-top:2px;">Ministry of Social Justice &amp; Empowerment</div>
                    <div style="font-size:11px; color:#F76C45; font-weight:700; margin-top:3px;">Field Inspection &amp; Project Monitoring Portal</div>
                </div>

                <!-- Main Login Card -->
                <div style="background:#fff; border-radius:14px; padding:16px 14px; box-shadow:0 4px 16px rgba(0,0,0,0.06); border:1px solid #EAE6E5; margin-bottom:12px;">
                    <div style="font-size:13px; font-weight:800; color:#110B0A; margin-bottom:3px;">Official Position Login</div>
                    <div style="font-size:11px; color:#666; margin-bottom:12px;">Select your official post from the dropdown below. Respective details will be loaded for authentication.</div>

                    <!-- Position Dropdown -->
                    <div class="modal-form-group" style="margin-bottom:12px;">
                        <label class="modal-form-label" style="color:#110B0A; font-weight:700; font-size:11px;">Select Official Position / Post:</label>
                        <select id="loginPositionSelect" class="position-select-field" onchange="onLoginPositionChange()">
                            <option value="">-- Choose Position / Post --</option>
                            <option value="INSPECTOR">1. Field Inspector</option>
                            <option value="DISTRICT_OFFICER">2. District Officer</option>
                            <option value="STATE_OFFICER">3. State Officer</option>
                            <option value="MOSJE_ADMIN">4. MoSJE Admin (Ministry)</option>
                            <option value="MASTER_ADMIN">5. Master Admin (Supreme)</option>
                            <option value="NGO">6. NGO / Social Auditor</option>
                            <option value="PUBLIC">7. Public Citizen</option>
                        </select>
                    </div>

                    <!-- Dynamic Credential Fields Container -->
                    <div id="dynamicCredContainer" style="display:none;">
                        <!-- Role Info Banner -->
                        <div id="loginRoleInfoBanner" style="background:#F0F4F8; border-left:4px solid var(--primary); padding:8px 10px; border-radius:6px; font-size:11px; color:#333; margin-bottom:10px;">
                            <!-- Populated dynamically -->
                        </div>

                        <!-- Form Inputs -->
                        <div id="loginFormInputs">
                            <!-- Populated dynamically -->
                        </div>

                        <div style="background:#FFF9C4; border:1px solid #FFE082; padding:7px 9px; border-radius:6px; font-size:10px; color:#F57F17; margin:10px 0;">
                            ✓ <strong>Task 12 Demo Account:</strong> Sync checks and credentials pre-loaded. You can verify or edit details above.
                        </div>

                        <button id="btnLoginSubmit" class="btn-position-login" onclick="submitPositionLogin()">
                            Verify Details &amp; Enter Dashboard →
                        </button>
                    </div>

                    <div id="loginPromptPlaceholder" style="text-align:center; padding:18px 10px; color:#888; font-size:11px; border:1px dashed #DDD; border-radius:8px;">
                        👆 Please select a position from the dropdown menu to enter details.
                    </div>
                </div>

                <!-- Mobile Application Download / PWA Banner -->
                <div style="background:linear-gradient(135deg, #2A2A33 0%, #1A1A22 100%); color:#fff; border-radius:12px; padding:12px 14px; text-align:center; box-shadow:0 4px 15px rgba(0,0,0,0.15);">
                    <div style="font-size:12px; font-weight:700; margin-bottom:3px;">📲 Download Application to Mobile</div>
                    <div style="font-size:10px; color:#BBB; margin-bottom:8px;">Install this app directly on your Android phone or iPhone home screen for fast field access.</div>
                    <button onclick="installOrDownloadApp()" style="background:var(--primary); color:#fff; border:none; border-radius:6px; padding:8px 16px; font-size:11px; font-weight:700; cursor:pointer;">
                        ⬇ Install / Download App
                    </button>
                </div>
            </div>
`;

if (!html.includes('id="screenLogin"')) {
  html = html.replace(
    '<div class="app-body" id="appBody">',
    '<div class="app-body" id="appBody">\n' + loginScreenHtml
  );
}

// 7. Make #bottomNav have id="bottomNav" and default to display:none
html = html.replace(
  '<div class="bottom-nav">',
  '<div class="bottom-nav" id="bottomNav" style="display:none;">'
);

// 8. Make #screenHome default to display:none
html = html.replace(
  '<div class="tab-screen" id="screenHome" style="display: block;">',
  '<div class="tab-screen" id="screenHome" style="display: none;">'
);

// 9. Update JavaScript with POSITIONS_CONFIG, onLoginPositionChange, submitPositionLogin, handleAppLogout, installOrDownloadApp
const mobileAppJs = `
        // POSITIONS CONFIGURATION & TASK 12 SEED CREDENTIALS
        const POSITIONS_CONFIG = {
            'INSPECTOR': {
                name: 'Rajesh Meshram',
                title: 'Field Inspector • Nagpur Division',
                badgeColor: '#F76C45',
                infoBanner: '<strong>Field Inspector</strong> | Mobile &amp; Inspection Code Flow | Nagpur District',
                fieldsHtml: \`
                    <div class="modal-form-group">
                        <label class="modal-form-label">Registered Mobile Number</label>
                        <input type="tel" class="modal-input" id="inpMobile" value="9900112233">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Inspector Name</label>
                        <input type="text" class="modal-input" id="inpName" value="Rajesh Meshram">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Secure Password</label>
                        <input type="password" class="modal-input" id="inpPassword" value="Demo@123">
                    </div>
                \`,
                getPayload: () => ({ email: 'inspector.rajesh@mosje.gov.in', password: document.getElementById('inpPassword')?.value || 'Demo@123' })
            },
            'DISTRICT_OFFICER': {
                name: 'Virendra Deshmukh',
                title: 'District Officer • Nagpur',
                badgeColor: '#1976D2',
                infoBanner: '<strong>District Officer</strong> | LGD Sync: DL-LGD-478 | Nagpur Jurisdiction',
                fieldsHtml: \`
                    <div class="modal-form-group">
                        <label class="modal-form-label">Official Mobile Number</label>
                        <input type="tel" class="modal-input" id="inpMobile" value="9765432190">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">District LGD Code (Gov-ID Synced)</label>
                        <input type="text" class="modal-input" id="inpLgd" value="DL-LGD-478">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Secure Password</label>
                        <input type="password" class="modal-input" id="inpPassword" value="Demo@123">
                    </div>
                \`,
                getPayload: () => ({ email: 'district.nagpur@mosje.gov.in', password: document.getElementById('inpPassword')?.value || 'Demo@123' })
            },
            'STATE_OFFICER': {
                name: 'K. S. Patil',
                title: 'State Officer • Maharashtra',
                badgeColor: '#388E3C',
                infoBanner: '<strong>State Officer</strong> | LGD Sync: ST-LGD-024 | Maharashtra State',
                fieldsHtml: \`
                    <div class="modal-form-group">
                        <label class="modal-form-label">Official Mobile Number</label>
                        <input type="tel" class="modal-input" id="inpMobile" value="9654321087">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">State LGD Code (Gov-ID Synced)</label>
                        <input type="text" class="modal-input" id="inpLgd" value="ST-LGD-024">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Secure Password</label>
                        <input type="password" class="modal-input" id="inpPassword" value="Demo@123">
                    </div>
                \`,
                getPayload: () => ({ email: 'state.maharashtra@mosje.gov.in', password: document.getElementById('inpPassword')?.value || 'Demo@123' })
            },
            'MOSJE_ADMIN': {
                name: 'Central MoSJE Admin',
                title: 'Ministry Director • Central Directorate',
                badgeColor: '#D32F2F',
                infoBanner: '<strong>MoSJE Admin</strong> | Central Gov-ID / @gov.in Sync | National Directorate',
                fieldsHtml: \`
                    <div class="modal-form-group">
                        <label class="modal-form-label">Central Admin ID / NIC Email</label>
                        <input type="email" class="modal-input" id="inpEmail" value="mosje.admin@gov.in">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Synced Official Mobile</label>
                        <input type="tel" class="modal-input" id="inpMobile" value="9543210876">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Secure Password</label>
                        <input type="password" class="modal-input" id="inpPassword" value="Demo@123">
                    </div>
                \`,
                getPayload: () => ({ email: document.getElementById('inpEmail')?.value || 'mosje.admin@gov.in', password: document.getElementById('inpPassword')?.value || 'Demo@123' })
            },
            'MASTER_ADMIN': {
                name: 'Master Supreme Administrator',
                title: 'Supreme Governance & Security Audit',
                badgeColor: '#512DA8',
                infoBanner: '<strong>Master Admin</strong> | Supreme Root Authority | All-Account Governance',
                fieldsHtml: \`
                    <div class="modal-form-group">
                        <label class="modal-form-label">Root Username</label>
                        <input type="text" class="modal-input" id="inpUsername" value="admin">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Root Password</label>
                        <input type="password" class="modal-input" id="inpPassword" value="admin">
                    </div>
                \`,
                getPayload: () => ({ username: document.getElementById('inpUsername')?.value || 'admin', password: document.getElementById('inpPassword')?.value || 'admin' })
            },
            'NGO': {
                name: 'Sewa Bharati Trust',
                title: 'Verified Social Auditor • NGO',
                badgeColor: '#7B1FA2',
                infoBanner: '<strong>NGO Social Auditor</strong> | Verified Attached Status | Public Grievances',
                fieldsHtml: \`
                    <div class="modal-form-group">
                        <label class="modal-form-label">Organization Email</label>
                        <input type="email" class="modal-input" id="inpEmail" value="demo.ngo@example.org">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Authorized Mobile</label>
                        <input type="tel" class="modal-input" id="inpMobile" value="9873321045">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Secure Password</label>
                        <input type="password" class="modal-input" id="inpPassword" value="Demo@123">
                    </div>
                \`,
                getPayload: () => ({ email: document.getElementById('inpEmail')?.value || 'demo.ngo@example.org', password: document.getElementById('inpPassword')?.value || 'Demo@123' })
            },
            'PUBLIC': {
                name: 'Citizen Auditor',
                title: 'Public Citizen • Transparency Portal',
                badgeColor: '#00796B',
                infoBanner: '<strong>Public Citizen</strong> | Masked Privacy Portal | 1-per-day Nudge Access',
                fieldsHtml: \`
                    <div class="modal-form-group">
                        <label class="modal-form-label">10-Digit Mobile Number</label>
                        <input type="tel" class="modal-input" id="inpMobile" value="9821004567">
                    </div>
                    <div class="modal-form-group">
                        <label class="modal-form-label">Verification OTP</label>
                        <input type="text" class="modal-input" id="inpOtp" value="123456">
                    </div>
                \`,
                getPayload: () => ({ phone: document.getElementById('inpMobile')?.value || '9821004567', otp: document.getElementById('inpOtp')?.value || '123456' })
            }
        };

        // POSITION DROPDOWN EVENT HANDLER
        function onLoginPositionChange() {
            const pos = document.getElementById('loginPositionSelect').value;
            const container = document.getElementById('dynamicCredContainer');
            const placeholder = document.getElementById('loginPromptPlaceholder');
            const banner = document.getElementById('loginRoleInfoBanner');
            const inputs = document.getElementById('loginFormInputs');

            if (!pos || !POSITIONS_CONFIG[pos]) {
                container.style.display = 'none';
                placeholder.style.display = 'block';
                return;
            }

            const cfg = POSITIONS_CONFIG[pos];
            banner.innerHTML = cfg.infoBanner;
            banner.style.borderLeftColor = cfg.badgeColor;
            inputs.innerHTML = cfg.fieldsHtml;

            placeholder.style.display = 'none';
            container.style.display = 'block';
        }

        // SUBMIT POSITION LOGIN (VERIFY DETAILS & ENTER DASHBOARD)
        async function submitPositionLogin() {
            const pos = document.getElementById('loginPositionSelect').value;
            if (!pos) {
                showToast("Please choose a position from the dropdown menu.", "warning");
                return;
            }

            const cfg = POSITIONS_CONFIG[pos];
            const btn = document.getElementById('btnLoginSubmit');
            if (btn) {
                btn.disabled = true;
                btn.innerText = "Verifying Details...";
            }

            try {
                const payload = cfg.getPayload();
                let res = null;

                if (pos === 'PUBLIC') {
                    // Public OTP verify
                    res = await fetch(\`\${API_BASE}/auth/otp/verify\`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    }).then(r => r.json());
                } else {
                    // Password login
                    res = await fetch(\`\${API_BASE}/auth/login\`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(payload)
                    }).then(r => r.json());
                }

                if (!res.success || !res.data?.token) {
                    showToast(res.message || "Authentication failed. Please check credentials.", "error");
                    alert("🚫 Authentication Error:\\n" + (res.message || "Invalid credentials"));
                    if (btn) { btn.disabled = false; btn.innerText = "Verify Details & Enter Dashboard →"; }
                    return;
                }

                currentToken = res.data.token;
                currentUser = res.data.user;
                currentRole = pos;

                // Hide Login Screen, Show App Shell
                document.getElementById('screenLogin').style.display = 'none';
                document.getElementById('appHeader').style.display = 'block';
                document.getElementById('bottomNav').style.display = 'flex';

                const meta = ROLES_META[pos];
                document.getElementById('userGreeting').innerText = \`Namaste, \${meta.name}\`;
                document.getElementById('roleBadge').innerText = meta.title;
                document.getElementById('fabCamera').style.display = meta.hasFab ? 'flex' : 'none';

                // Master Nav icon only for Master Admin
                const navMaster = document.getElementById('navMaster');
                if (navMaster) {
                    navMaster.style.display = (pos === 'MASTER_ADMIN') ? 'flex' : 'none';
                }

                // Add Tender button only for Admins
                const btnAdd = document.getElementById('btnAdminAddTender');
                if (btnAdd) {
                    btnAdd.style.display = (pos === 'MOSJE_ADMIN' || pos === 'MASTER_ADMIN') ? 'inline-flex' : 'none';
                }

                // Inspector Code box only for Inspector
                const codeBox = document.getElementById('inspectorCodeBox');
                if (codeBox) {
                    codeBox.style.display = (pos === 'INSPECTOR') ? 'block' : 'none';
                }

                renderShortcuts(pos);
                await loadHierarchyData();
                await loadTenders();
                await loadStats();

                if (pos === 'MASTER_ADMIN') {
                    switchTab('master');
                } else {
                    switchTab('home');
                }

                showToast(\`✓ Verified! Entered \${meta.title} dashboard\`, 'success');
            } catch (err) {
                console.error("Login verification error:", err);
                showToast("Verification error: " + err.message, "error");
            } finally {
                if (btn) { btn.disabled = false; btn.innerText = "Verify Details & Enter Dashboard →"; }
            }
        }

        // LOGOUT & RETURN TO POSITION DROPDOWN
        function handleAppLogout() {
            currentToken = null;
            currentUser = null;
            currentRole = null;

            document.getElementById('appHeader').style.display = 'none';
            document.getElementById('bottomNav').style.display = 'none';
            document.querySelectorAll('.tab-screen').forEach(s => s.style.display = 'none');

            document.getElementById('screenLogin').style.display = 'flex';
            document.getElementById('loginPositionSelect').value = '';
            document.getElementById('dynamicCredContainer').style.display = 'none';
            document.getElementById('loginPromptPlaceholder').style.display = 'block';

            showToast("Session closed. Select position to log in.", "info");
        }

        function handleDemoLogout() {
            handleAppLogout();
        }

        // PWA INSTALL / DOWNLOAD PROMPT
        let deferredPrompt = null;
        window.addEventListener('beforeinstallprompt', (e) => {
            e.preventDefault();
            deferredPrompt = e;
        });

        function installOrDownloadApp() {
            if (deferredPrompt) {
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then((choiceResult) => {
                    if (choiceResult.outcome === 'accepted') {
                        showToast("✓ Application installed successfully!", "success");
                    }
                    deferredPrompt = null;
                });
            } else {
                alert("📲 Mobile Application Installation:\\n\\n1. On Android Chrome: Tap menu (⋮) → 'Install App' or 'Add to Home Screen'.\\n2. On iPhone Safari: Tap Share (⎙) → 'Add to Home Screen'.\\n3. For native Android APK: The Java source is compiled under JDK 17 in android/ directory.\\n\\nThe web application runs in full-screen standalone PWA mode with complete offline caching!");
                showToast("Tap browser menu → 'Add to Home Screen' to download!", "info");
            }
        }

        // Register Service Worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js').catch(e => console.log('SW registration note:', e));
            });
        }
`;

// Insert the helper code
html = html.replace(
  '// ROLE SWITCHING',
  mobileAppJs + '\n        // ROLE SWITCHING'
);

// Update init() so it starts at #screenLogin instead of auto-logging in
html = html.replace(
  /\(async function init\(\) \{[\s\S]*?renderProfile\('INSPECTOR'\);\s*\}\)\(\);/,
  `(function init() {
            // Mobile app starts on Position Login Screen (Screen 0)
            document.getElementById('screenLogin').style.display = 'flex';
            document.getElementById('appHeader').style.display = 'none';
            document.getElementById('bottomNav').style.display = 'none';
            document.querySelectorAll('.tab-screen').forEach(s => s.style.display = 'none');
        })();`
);

// Save updated index.html
fs.writeFileSync(previewPath, html, 'utf8');
console.log('preview/index.html updated successfully with position dropdown & mobile PWA app!');
