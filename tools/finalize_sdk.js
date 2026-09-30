const fs = require('fs');
const path = require('path');

console.log('=== FINALIZING ANDROID SDK ===');

const sdkDir = path.resolve(__dirname, 'sdk');
const unzippedAndroid34 = path.join(sdkDir, '.temp', 'PackageOperation01', 'unzip', 'android-34');
const targetPlatformDir = path.join(sdkDir, 'platforms', 'android-34');

if (fs.existsSync(unzippedAndroid34)) {
    console.log('Moving unzipped android-34 files into platforms/android-34...');
    if (!fs.existsSync(targetPlatformDir)) fs.mkdirSync(targetPlatformDir, { recursive: true });
    
    function copyRecursive(src, dest) {
        if (fs.statSync(src).isDirectory()) {
            if (!fs.existsSync(dest)) fs.mkdirSync(dest, { recursive: true });
            for (const item of fs.readdirSync(src)) {
                copyRecursive(path.join(src, item), path.join(dest, item));
            }
        } else {
            fs.copyFileSync(src, dest);
        }
    }
    
    copyRecursive(unzippedAndroid34, targetPlatformDir);
    console.log('[SUCCESS] Copied android-34 platform files!');
}

const androidJar = path.join(targetPlatformDir, 'android.jar');
console.log('android.jar exists?', fs.existsSync(androidJar));
if (fs.existsSync(androidJar)) {
    console.log('android.jar size:', fs.statSync(androidJar).size, 'bytes');
}

// Clean up .temp
try {
    fs.rmSync(path.join(sdkDir, '.temp'), { recursive: true, force: true });
    console.log('[SUCCESS] Cleaned up .temp');
} catch (e) {}

// Ensure local.properties
const localPropertiesPath = path.resolve(__dirname, '..', 'android', 'local.properties');
const escapedSdkDir = sdkDir.replace(/\\/g, '\\\\');
fs.writeFileSync(localPropertiesPath, `sdk.dir=${escapedSdkDir}\n`);
console.log('[SUCCESS] local.properties configured with sdk.dir:', sdkDir);
