const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

console.log('=== GENERATING RELEASE KEYSTORE ===');

const keytool = path.resolve(__dirname, 'jdk', 'bin', 'keytool.exe');
const keystorePath = path.resolve(__dirname, '..', 'android', 'release.jks');

if (fs.existsSync(keystorePath)) {
    fs.unlinkSync(keystorePath);
}

const args = [
    '-genkeypair',
    '-v',
    '-keystore', keystorePath,
    '-alias', 'smartinspection',
    '-keyalg', 'RSA',
    '-keysize', '2048',
    '-validity', '10000',
    '-storetype', 'PKCS12',
    '-storepass', 'Chakravyuh@2026',
    '-keypass', 'Chakravyuh@2026',
    '-dname', 'CN=Team Chakravyuh, OU=Smart Inspection MoSJE, O=Government of India, L=New Delhi, ST=Delhi, C=IN'
];

console.log('Running keytool with spawnSync...');
const res = spawnSync(keytool, args, { encoding: 'utf8' });
console.log('Exit code:', res.status);
if (res.stdout) console.log('STDOUT:', res.stdout);
if (res.stderr) console.log('STDERR:', res.stderr);

if (fs.existsSync(keystorePath)) {
    console.log(`[SUCCESS] Keystore created at: ${keystorePath} (${fs.statSync(keystorePath).size} bytes)`);
} else {
    console.error('[ERROR] Keystore file not created!');
}
