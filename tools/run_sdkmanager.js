const fs = require('fs');
const path = require('path');
const { spawnSync } = require('child_process');

console.log('=== RUNNING SDKMANAGER WITH FULL CLASSPATH ===');

const sdkDir = path.resolve(__dirname, 'sdk');
const jdkDir = path.resolve(__dirname, 'jdk');
const javaExe = path.join(jdkDir, 'bin', 'java.exe');
const libDir = path.join(sdkDir, 'cmdline-tools', 'latest', 'lib');
const toolsDir = path.join(sdkDir, 'cmdline-tools', 'latest');

function getJarFiles(dir) {
    let res = [];
    try {
        for (const f of fs.readdirSync(dir)) {
            const p = path.join(dir, f);
            if (f.endsWith('.jar')) res.push(p);
            if (fs.statSync(p).isDirectory()) res = res.concat(getJarFiles(p));
        }
    } catch(e) {}
    return res;
}

const allJars = getJarFiles(libDir);
console.log(`Found ${allJars.length} jars in cmdline-tools/latest/lib`);
const fullClasspath = allJars.join(';');

const jvmArgs = [
    `-Dcom.android.sdklib.toolsdir=${toolsDir}`,
    '-classpath', fullClasspath,
    'com.android.sdklib.tool.sdkmanager.SdkManagerCli',
    `--sdk_root=${sdkDir}`,
    '--version'
];

console.log('Testing sdkmanager --version...');
const verRes = spawnSync(javaExe, jvmArgs, { encoding: 'utf8' });
console.log('Exit code:', verRes.status);
if (verRes.stdout) console.log('STDOUT:', verRes.stdout.trim());
if (verRes.stderr) console.log('STDERR:', verRes.stderr.trim());

if (verRes.status === 0) {
    console.log('\nInstalling platforms;android-34 and build-tools;34.0.0...');
    const installArgs = [
        `-Dcom.android.sdklib.toolsdir=${toolsDir}`,
        '-classpath', fullClasspath,
        'com.android.sdklib.tool.sdkmanager.SdkManagerCli',
        `--sdk_root=${sdkDir}`,
        'platform-tools',
        'platforms;android-34',
        'build-tools;34.0.0'
    ];
    const installRes = spawnSync(javaExe, installArgs, { encoding: 'utf8', input: 'y\ny\ny\ny\n' });
    console.log('Install exit code:', installRes.status);
    if (installRes.stdout) console.log('STDOUT:', installRes.stdout.substring(0, 1000));
    if (installRes.stderr) console.log('STDERR:', installRes.stderr.trim());
}
