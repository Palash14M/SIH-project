const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

console.log('=== EXTRACTING ANDROID CMDLINE TOOLS ===');

const sdkDir = path.resolve(__dirname, 'sdk');
const zipFile = path.join(sdkDir, 'cmdline-tools.zip');
const cmdlineToolsDir = path.join(sdkDir, 'cmdline-tools');
const latestDir = path.join(cmdlineToolsDir, 'latest');

if (!fs.existsSync(zipFile)) {
    console.error('cmdline-tools.zip does not exist yet!');
    process.exit(1);
}

// Extract using tar or powershell
if (!fs.existsSync(latestDir)) {
    fs.mkdirSync(latestDir, { recursive: true });
    console.log('Extracting cmdline-tools.zip...');
    try {
        execSync(`tar -xf "${zipFile}" -C "${sdkDir}"`, { stdio: 'inherit' });
        // The zip contains a folder `cmdline-tools`. Move contents to `latest`
        const extractedRoot = path.join(sdkDir, 'cmdline-tools');
        // If files extracted directly into sdkDir/cmdline-tools:
        const subdirs = fs.readdirSync(extractedRoot);
        console.log('Extracted cmdline-tools subdirs:', subdirs);
        if (subdirs.includes('bin') && subdirs.includes('lib')) {
            // It extracted directly into cmdline-tools! We need cmdline-tools/latest/bin
            const tempDir = path.join(sdkDir, 'cmdline-temp');
            fs.renameSync(extractedRoot, tempDir);
            fs.mkdirSync(cmdlineToolsDir, { recursive: true });
            fs.renameSync(tempDir, latestDir);
        }
        console.log('[SUCCESS] cmdline-tools moved to latest/');
    } catch (e) {
        console.log('tar failed or renaming, trying powershell Expand-Archive...');
        execSync(`powershell -Command "Expand-Archive -Path '${zipFile}' -DestinationPath '${sdkDir}' -Force"`, { stdio: 'inherit' });
    }
}

// Pre-accept SDK licenses
const licensesDir = path.join(sdkDir, 'licenses');
if (!fs.existsSync(licensesDir)) fs.mkdirSync(licensesDir, { recursive: true });

// Known Google Android SDK license hashes
const androidSdkLicense = '\n24333f8a63b6825ea9c5514f83c2829b004d1fee\nd56f5187479451eabf01fb78af6dfcb131a6481e\n84831b9409646a47a130089e830e054452140683';
const androidSdkPreviewLicense = '\n84831b9409646a47a130089e830e054452140683';
const androidSdkArmLicense = '\nd975465160c612a4e103e5e0e77447fad5dacd1e';

fs.writeFileSync(path.join(licensesDir, 'android-sdk-license'), androidSdkLicense.trim());
fs.writeFileSync(path.join(licensesDir, 'android-sdk-preview-license'), androidSdkPreviewLicense.trim());
fs.writeFileSync(path.join(licensesDir, 'android-sdk-arm-dbt-license'), androidSdkArmLicense.trim());

console.log('[SUCCESS] Pre-accepted Android SDK licenses in:', licensesDir);

// Configure local.properties for Gradle
const localPropertiesPath = path.resolve(__dirname, '..', 'android', 'local.properties');
const escapedSdkDir = sdkDir.replace(/\\/g, '\\\\');
fs.writeFileSync(localPropertiesPath, `sdk.dir=${escapedSdkDir}\n`);
console.log('[SUCCESS] Configured local.properties at:', localPropertiesPath);
