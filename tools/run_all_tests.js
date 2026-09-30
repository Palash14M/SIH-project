const { execSync } = require('child_process');

console.log('===============================================================');
console.log('   SIH26095 MASTER APPLICATION BUILD - COMPLETE REGRESSION     ');
console.log('===============================================================\n');

const suites = [
    { name: 'PART 2: Auth, Roles, Middleware & Database Core', cmd: '.\\tools\\php\\php.exe tests\\test_part2.php' },
    { name: 'PART 3: 8-Step Lifecycle, GPS, PDF/CSV Reports & Variance', cmd: '.\\tools\\php\\php.exe tests\\test_part3.php' },
    { name: 'PART 4: Complaints, Escalation Fees, Refunds & Nudge', cmd: '.\\tools\\php\\php.exe tests\\test_part4.php' },
    { name: 'MASTER PROMPT: 12-Task Supreme Verification Suite', cmd: '.\\tools\\php\\php.exe tests\\test_master_prompt.php' },
    { name: 'PART 9: 28-Step Multi-Actor End-to-End Lifecycle', cmd: '.\\tools\\php\\php.exe tests\\test_part9_e2e.php' },
    { name: 'MOBILE APP: Dropdown Login, PWA & Single Role Control', cmd: 'node tools\\test_mobile_position_flow.js' },
    { name: 'AUTHENTICATOR & DB: RFC 6238 TOTP 2FA & Database Explorer', cmd: 'node tools\\test_authenticator_and_db.js' },
    { name: 'ANDROID SOURCES: Java Syntax & Compilation under JDK 17', cmd: 'node tools\\verify_android_sources.js' }
];

let totalPassedSuites = 0;
let totalFailedSuites = 0;

suites.forEach((s, idx) => {
    console.log(`\n---------------------------------------------------------------`);
    console.log(`RUNNING SUITE ${idx + 1}/${suites.length}: ${s.name}`);
    console.log(`---------------------------------------------------------------`);
    try {
        execSync(s.cmd, { stdio: 'inherit' });
        totalPassedSuites++;
        console.log(`>>> SUITE ${idx + 1} PASSED!`);
    } catch (e) {
        totalFailedSuites++;
        console.error(`>>> SUITE ${idx + 1} FAILED!`);
    }
});

console.log('\n===============================================================');
console.log(`FINAL RESULTS: ${totalPassedSuites}/${suites.length} SUITES PASSED (${totalFailedSuites} FAILED)`);
console.log('===============================================================\n');

if (totalFailedSuites > 0) process.exit(1);
