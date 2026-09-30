const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

console.log('=== ENVIRONMENT CHECK ===');
console.log('Node version:', process.version);
console.log('Platform:', process.platform);

function checkCmd(cmd) {
    try {
        const out = execSync(`where ${cmd}`, { encoding: 'utf8', stdio: ['pipe', 'pipe', 'ignore'] }).trim();
        console.log(`[FOUND] ${cmd}: ${out.split('\n')[0]}`);
        return true;
    } catch (e) {
        console.log(`[NOT FOUND] ${cmd}`);
        return false;
    }
}

checkCmd('javac');
checkCmd('keytool');
checkCmd('jar');
checkCmd('adb');
checkCmd('gradle');
checkCmd('git');
checkCmd('firebase');
checkCmd('gh');
checkCmd('vercel');
checkCmd('netlify');

console.log('\n--- Checking Common Android SDK / Java Locations ---');
const possibleDirs = [
    process.env.ANDROID_HOME,
    process.env.ANDROID_SDK_ROOT,
    path.join(process.env.LOCALAPPDATA || '', 'Android', 'Sdk'),
    path.join(process.env.USERPROFILE || '', 'AppData', 'Local', 'Android', 'Sdk'),
    'C:\\Android\\Sdk',
    'C:\\Program Files\\Android\\Android Studio',
    'C:\\Program Files\\Java',
    'C:\\Program Files\\Eclipse Adoptium',
    'C:\\Program Files\\Microsoft'
];

for (const d of possibleDirs) {
    if (d && fs.existsSync(d)) {
        console.log(`[EXISTS] ${d}`);
    }
}

console.log('\n--- Checking android/ directory in project ---');
const androidDir = path.join(__dirname, '..', 'android');
if (fs.existsSync(androidDir)) {
    console.log('Files in android/:', fs.readdirSync(androidDir));
}
