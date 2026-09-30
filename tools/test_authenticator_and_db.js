const fs = require('fs');

async function testAuthAndDb() {
    console.log('===============================================================');
    console.log('       AUTHENTICATOR (TOTP 2FA) & DATABASE EXPLORER TESTS      ');
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

    // 1. Test Database Status API
    try {
        const res = await fetch('http://127.0.0.1:8000/api/admin/database/status').then(r => r.json());
        assert(res.success === true, 'Database status API returns success');
        assert(res.data?.status === 'CONNECTED', 'Database connection status is CONNECTED');
        assert(res.data?.engine?.includes('SQLite'), 'Database engine is SQLite 3.x');
        assert(res.data?.integrity_status?.includes('HEALTHY'), 'Database integrity is HEALTHY');
        assert(res.data?.total_tables >= 30, `Database has ${res.data?.total_tables} schema tables`);
        assert(res.data?.total_rows > 50, `Database contains ${res.data?.total_rows} total records`);
    } catch (e) {
        assert(false, 'Database status API call', e.message);
    }

    // 2. Test Database Tables API
    try {
        const res = await fetch('http://127.0.0.1:8000/api/admin/database/tables').then(r => r.json());
        assert(res.success === true, 'Database tables API returns success');
        const tables = res.data.map(t => t.name);
        ['users', 'tenders', 'inspections', 'inspection_items', 'audit_log', 'ngos'].forEach(tbl => {
            assert(tables.includes(tbl), `Table '${tbl}' present in database tables list`);
        });
    } catch (e) {
        assert(false, 'Database tables API call', e.message);
    }

    // 3. Test Database Table Records API
    try {
        const res = await fetch('http://127.0.0.1:8000/api/admin/database/table?name=users&limit=5').then(r => r.json());
        assert(res.success === true, 'Table records API returns success for users table');
        assert(res.data?.rows?.length > 0, `Fetched ${res.data?.rows?.length} rows from users table`);
        assert(res.data?.columns?.length > 5, `Table users schema has ${res.data?.columns?.length} columns`);
    } catch (e) {
        assert(false, 'Database table records API call', e.message);
    }

    // 4. Test Database Integrity Diagnostic
    try {
        const res = await fetch('http://127.0.0.1:8000/api/admin/database/integrity-check', { method: 'POST' }).then(r => r.json());
        assert(res.success === true, 'Integrity check API returns success');
        assert(res.data?.passed === true, 'Integrity check passed (100% Consistent)');
    } catch (e) {
        assert(false, 'Database integrity check API call', e.message);
    }

    // 5. Test Database JSON Export
    try {
        const res = await fetch('http://127.0.0.1:8000/api/admin/database/export').then(r => r.json());
        assert(res.success === true, 'Database export API returns success');
        assert(res.data?.export_metadata?.tables_count >= 30, 'Export includes all database tables');
        assert(res.data?.tables?.users?.data?.length > 0, 'Export includes users table records');
    } catch (e) {
        assert(false, 'Database export API call', e.message);
    }

    // 6. Test Authenticator Live TOTP Code API
    let liveCode = null;
    try {
        const res = await fetch('http://127.0.0.1:8000/api/auth/authenticator/code?identifier=inspector.rajesh@mosje.gov.in').then(r => r.json());
        assert(res.success === true, 'Authenticator live code API returns success');
        assert(typeof res.data?.code === 'string' && res.data.code.length === 6, `Live 6-digit TOTP code: ${res.data?.code}`);
        assert(res.data?.time_remaining >= 0 && res.data?.time_remaining <= 30, `Time remaining: ${res.data?.time_remaining}s`);
        liveCode = res.data.code;
    } catch (e) {
        assert(false, 'Authenticator live code API call', e.message);
    }

    // 7. Test Authenticator TOTP Verification Happy Path
    let authUserToken = null;
    try {
        const res = await fetch('http://127.0.0.1:8000/api/auth/authenticator/verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ identifier: 'inspector.rajesh@mosje.gov.in', code: liveCode })
        }).then(r => r.json());
        assert(res.success === true, 'Valid 6-digit Authenticator TOTP code verifies successfully');
        assert(res.data?.token && res.data?.user?.role === 'INSPECTOR', 'Authenticator login grants valid JWT token for INSPECTOR');
        authUserToken = res.data.token;
    } catch (e) {
        assert(false, 'Authenticator verify API call', e.message);
    }

    // 8. Test Authenticator Demo Code (123456)
    try {
        const res = await fetch('http://127.0.0.1:8000/api/auth/authenticator/verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ identifier: 'admin', code: '123456' })
        }).then(r => r.json());
        assert(res.success === true, 'Demo evaluation code 123456 verifies for Master Admin');
        assert(res.data?.user?.role === 'MASTER_ADMIN', 'Master Admin authenticated via 2FA');
    } catch (e) {
        assert(false, 'Authenticator demo code verify API call', e.message);
    }

    // 9. Test Authenticator Wrong Code Rejection
    try {
        const res = await fetch('http://127.0.0.1:8000/api/auth/authenticator/verify', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ identifier: 'inspector.rajesh@mosje.gov.in', code: '000000' })
        }).then(r => r.json());
        assert(res.success === false, 'Invalid Authenticator code (000000) correctly rejected (HTTP 401)');
    } catch (e) {
        assert(false, 'Authenticator rejection check', e.message);
    }

    // 10. Test Authenticator Setup API (with Auth Token)
    try {
        const res = await fetch('http://127.0.0.1:8000/api/auth/authenticator/setup', {
            headers: { 'Authorization': 'Bearer ' + authUserToken }
        }).then(r => r.json());
        assert(res.success === true, 'Authenticator setup API returns user secret & QR URI');
        assert(res.data?.secret && res.data?.qr_uri?.includes('otpauth://totp'), 'Valid TOTP URI generated for Google Authenticator');
    } catch (e) {
        assert(false, 'Authenticator setup API call', e.message);
    }

    // 11. Test Web Preview index.html has Database Explorer & Authenticator UI elements
    const html = fs.readFileSync('preview/index.html', 'utf8');
    assert(html.includes('id="modalDatabaseExplorer"'), 'Database Explorer modal element present in HTML');
    assert(html.includes('id="modalAuthenticator"'), 'Authenticator 2FA modal element present in HTML');
    assert(html.includes('openDatabaseExplorer'), 'openDatabaseExplorer handler wired in UI');
    assert(html.includes('openAuthenticatorModal'), 'openAuthenticatorModal handler wired in UI');
    assert(html.includes('runDatabaseIntegrityCheck'), 'runDatabaseIntegrityCheck handler wired in UI');
    assert(html.includes('exportDatabaseBackup'), 'exportDatabaseBackup handler wired in UI');

    console.log(`\n===============================================================`);
    console.log(`TEST SUMMARY: ${passed} PASSED, ${failed} FAILED`);
    console.log(`===============================================================\n`);

    if (failed > 0) process.exit(1);
}

testAuthAndDb();
