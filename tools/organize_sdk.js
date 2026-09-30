const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

console.log('=== ORGANIZING CMDLINE-TOOLS ===');

const sdkDir = path.resolve(__dirname, 'sdk');
const cmdlineToolsDir = path.join(sdkDir, 'cmdline-tools');
const latestDir = path.join(cmdlineToolsDir, 'latest');

if (fs.existsSync(path.join(cmdlineToolsDir, 'bin'))) {
    console.log('Moving bin and lib into latest/...');
    const items = fs.readdirSync(cmdlineToolsDir);
    for (const item of items) {
        if (item === 'latest') continue;
        const src = path.join(cmdlineToolsDir, item);
        const dest = path.join(latestDir, item);
        fs.renameSync(src, dest);
    }
    console.log('[SUCCESS] Moved cmdline-tools into latest/');
}

console.log('Verifying latest/bin/sdkmanager.bat exists:');
const sdkmanager = path.join(latestDir, 'bin', 'sdkmanager.bat');
console.log('sdkmanager.bat exists?', fs.existsSync(sdkmanager));

// Setup licenses
const licensesDir = path.join(sdkDir, 'licenses');
if (!fs.existsSync(licensesDir)) fs.mkdirSync(licensesDir, { recursive: true });

const androidSdkLicense = '\n24333f8a63b6825ea9c5514f83c2829b004d1fee\nd56f5187479451eabf01fb78af6dfcb131a6481e\n84831b9409646a47a130089e830e054452140683';
const androidSdkPreviewLicense = '\n84831b9409646a47a130089e830e054452140683';
const androidSdkArmLicense = '\nd975465160c612a4e103e5e0e77447fad5dacd1e';

fs.writeFileSync(path.join(licensesDir, 'android-sdk-license'), androidSdkLicense.trim());
fs.writeFileSync(path.join(licensesDir, 'android-sdk-preview-license'), androidSdkPreviewLicense.trim());
fs.writeFileSync(path.join(licensesDir, 'android-sdk-arm-dbt-license'), androidSdkArmLicense.trim());

// Write local.properties
const localPropertiesPath = path.resolve(__dirname, '..', 'android', 'local.properties');
const escapedSdkDir = sdkDir.replace(/\\/g, '\\\\');
fs.writeFileSync(localPropertiesPath, `sdk.dir=${escapedSdkDir}\n`);
console.log('[SUCCESS] Configured android/local.properties with sdk.dir:', sdkDir);
