const fs = require('fs');

async function runTests() {
    console.log('===============================================================');
    console.log('   MOBILE APPLICATION & POSITION DROPDOWN LOGIN VERIFICATION   ');
    console.log('===============================================================\n');

    let passed = 0;
    let failed = 0;

    function assert(cond, name, details = '') {
        if (cond) {
            console.log(`[PASS] ${name}`);
            passed++;
        } else {
            console.error(`[FAIL] ${name} ${details}`);
            failed++;
        }
    }

    // 1. Test Web Preview index.html serving and DOM elements
    try {
        const res = await fetch('http://127.0.0.1:3000/');
        assert(res.status === 200, 'Preview server returns HTTP 200 on /');
        const html = await res.text();

        assert(html.includes('id="screenLogin"'), 'Screen 0 (Position Login Page) is present');
        assert(html.includes('id="loginPositionSelect"'), 'Position dropdown menu is present');
        assert(html.includes('id="dynamicCredContainer"'), 'Dynamic credential container is present');
        assert(html.includes('id="loginRoleInfoBanner"'), 'Position info banner is present');
        assert(html.includes('handleAppLogout'), 'Sign Out action is present');
        assert(!html.includes('👑'), 'Zero crown symbols anywhere in preview application');
        assert(html.includes('rel="manifest" href="/manifest.json"'), 'PWA manifest linked');
        assert(html.includes('serviceWorker.register'), 'Service worker registered');

        // Check all 7 positions in dropdown
        const positions = [
            'value="INSPECTOR"',
            'value="DISTRICT_OFFICER"',
            'value="STATE_OFFICER"',
            'value="MOSJE_ADMIN"',
            'value="MASTER_ADMIN"',
            'value="NGO"',
            'value="PUBLIC"'
        ];
        positions.forEach(p => {
            assert(html.includes(p), `Dropdown includes position: ${p}`);
        });

        // Check that initial screen starts on Screen 0 (Login) and tabs are hidden
        assert(html.includes("document.getElementById('screenLogin').style.display = 'flex'"), 'Init starts on Screen 0 (screenLogin)');
    } catch (e) {
        assert(false, 'Preview server request', e.message);
    }

    // 2. Test manifest.json
    try {
        const res = await fetch('http://127.0.0.1:3000/manifest.json');
        assert(res.status === 200, 'manifest.json returns HTTP 200');
        const manifest = await res.json();
        assert(manifest.display === 'standalone', 'PWA display mode is standalone for mobile');
        assert(manifest.theme_color === '#F76C45', 'PWA theme color matches MoSJE primary brand');
        assert(manifest.name && manifest.short_name, 'PWA app name defined');
    } catch (e) {
        assert(false, 'manifest.json request', e.message);
    }

    // 3. Test sw.js
    try {
        const res = await fetch('http://127.0.0.1:3000/sw.js');
        assert(res.status === 200, 'sw.js returns HTTP 200');
        const sw = await res.text();
        assert(sw.includes('install') && sw.includes('fetch'), 'Service worker has install & fetch handlers');
    } catch (e) {
        assert(false, 'sw.js request', e.message);
    }

    // 4. Test Single-Role Position Authentication (All 7 Positions)
    const seedAccounts = [
        { pos: 'INSPECTOR', payload: { email: 'inspector.rajesh@mosje.gov.in', password: 'Demo@123' }, expRole: 'INSPECTOR' },
        { pos: 'DISTRICT_OFFICER', payload: { email: 'district.nagpur@mosje.gov.in', password: 'Demo@123' }, expRole: 'DISTRICT_OFFICER' },
        { pos: 'STATE_OFFICER', payload: { email: 'state.maharashtra@mosje.gov.in', password: 'Demo@123' }, expRole: 'STATE_OFFICER' },
        { pos: 'MOSJE_ADMIN', payload: { email: 'mosje.admin@gov.in', password: 'Demo@123' }, expRole: 'MOSJE_ADMIN' },
        { pos: 'MASTER_ADMIN', payload: { username: 'admin', password: 'admin' }, expRole: 'MASTER_ADMIN' },
        { pos: 'NGO', payload: { email: 'demo.ngo@example.org', password: 'Demo@123' }, expRole: 'NGO' }
    ];

    let masterToken = null;
    let mosjeToken = null;

    for (const acc of seedAccounts) {
        try {
            const res = await fetch('http://127.0.0.1:8000/api/auth/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(acc.payload)
            }).then(r => r.json());

            assert(res.success === true && res.data?.token, `Login for position ${acc.pos} succeeds with seed credentials`);
            assert(res.data?.user?.role === acc.expRole, `Position ${acc.pos} returns role ${acc.expRole}`);

            if (acc.pos === 'MASTER_ADMIN') masterToken = res.data.token;
            if (acc.pos === 'MOSJE_ADMIN') mosjeToken = res.data.token;
        } catch (e) {
            assert(false, `Login for position ${acc.pos}`, e.message);
        }
    }

    // Public OTP Login
    try {
        const res = await fetch('http://127.0.0.1:8000/api/auth/otp/verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ phone: '9821004567', otp: '123456' })
        }).then(r => r.json());

        assert(res.success === true && res.data?.token, 'Login for position PUBLIC succeeds with seed OTP 123456');
        assert(res.data?.user?.role === 'PUBLIC', 'Position PUBLIC returns role PUBLIC');
    } catch (e) {
        assert(false, 'Public OTP login', e.message);
    }

    // 5. Test Master Admin Dashboard & User Credentials
    try {
        const res = await fetch('http://127.0.0.1:8000/api/admin/master-dashboard', {
            headers: { 'Authorization': 'Bearer ' + masterToken }
        }).then(r => r.json());

        assert(res.success === true, 'Master Admin dashboard API returns success');
        assert(res.data?.accounts && res.data.accounts.length > 0, `Master Admin accounts count: ${res.data?.accounts?.length}`);

        const sample = res.data.accounts.find(a => a.email === 'inspector.rajesh@mosje.gov.in') || res.data.accounts[0];
        assert(sample.demo_password !== undefined, 'User account includes demo_password for Master Admin inspect modal');
        assert(sample.login_method !== undefined, 'User account includes login_method description');
        assert(sample.gov_sync_status !== undefined, 'User account includes gov_sync_status');
    } catch (e) {
        assert(false, 'Master Admin dashboard API', e.message);
    }

    // 6. Test Tender Creation without validation error
    try {
        const tenderNum = 'VERIFY-TND-' + Math.floor(1000 + Math.random() * 9000);
        const res = await fetch('http://127.0.0.1:8000/api/tenders', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer ' + mosjeToken
            },
            body: JSON.stringify({
                tender_number: tenderNum,
                title: 'Construction of Residential Ashram School Block',
                category_id: 1,
                issuing_department: 'Social Welfare Engineering Dept',
                state_id: 1,
                district_id: 1,
                sanctioned_amount: 35000000,
                scheduled_end_date: '2026-12-31'
            })
        }).then(r => r.json());

        assert(res.success === true, 'Tender creation succeeds without validation error');
        assert(res.data?.tender_number === tenderNum, `Tender ${tenderNum} successfully persisted`);
        assert(res.data?.district_officer_id && res.data?.state_officer_id, 'Tender auto-routed to State and District officers');
    } catch (e) {
        assert(false, 'Tender creation request', e.message);
    }

    console.log(`\n===============================================================`);
    console.log(`TEST SUMMARY: ${passed} PASSED, ${failed} FAILED`);
    console.log(`===============================================================\n`);

    if (failed > 0) process.exit(1);
}

runTests();
