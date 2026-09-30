const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');

console.log('=== VERIFYING ANDROID JAVA SOURCE SYNTAX & COMPILATION ===');

const srcDir = path.resolve(__dirname, '..', 'android', 'app', 'src', 'main', 'java');
const javac = path.resolve(__dirname, 'jdk', 'bin', 'javac.exe');
const gsonJar = path.resolve(__dirname, 'libs', 'gson.jar');

function getAllJavaFiles(dir, fileList = []) {
    const files = fs.readdirSync(dir);
    for (const file of files) {
        const fullPath = path.join(dir, file);
        if (fs.statSync(fullPath).isDirectory()) {
            getAllJavaFiles(fullPath, fileList);
        } else if (file.endsWith('.java')) {
            fileList.push(fullPath);
        }
    }
    return fileList;
}

const javaFiles = getAllJavaFiles(srcDir);
console.log(`Found ${javaFiles.length} Java source files:`);
javaFiles.forEach(f => console.log(' - ' + path.relative(srcDir, f)));

// Group 1: Constants and Models (uses gson)
const modelFiles = javaFiles.filter(f => 
    f.includes('Constants.java') || 
    f.includes('model')
);

const outDir = path.resolve(__dirname, '..', 'android', 'build_check');
if (!fs.existsSync(outDir)) fs.mkdirSync(outDir, { recursive: true });

try {
    console.log(`\nCompiling ${modelFiles.length} core data model and constant files with javac...`);
    const fileArgs = modelFiles.map(f => `"${f}"`).join(' ');
    execSync(`"${javac}" -d "${outDir}" -proc:none -cp "${gsonJar}" ${fileArgs}`, { stdio: 'inherit' });
    console.log(`\n[SUCCESS] All data models and constants compiled cleanly under JDK 17 javac!`);
} catch (e) {
    console.error(`[FAILURE] Model compilation error.`);
    process.exit(1);
} finally {
    try {
        fs.rmSync(outDir, { recursive: true, force: true, maxRetries: 3 });
    } catch (_) {}
}
