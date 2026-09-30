const fs = require('fs');
const path = require('path');
const https = require('https');
const { execSync } = require('child_process');

const targetDir = path.resolve(__dirname, 'jdk');
const zipFile = path.resolve(__dirname, 'openjdk17.zip');

if (fs.existsSync(path.join(targetDir, 'bin', 'javac.exe'))) {
    console.log('JDK already installed!');
    process.exit(0);
}

// Check Adoptium API for latest 17 GA release
const url = 'https://api.adoptium.net/v3/binary/latest/17/ga/windows/x64/jdk/hotspot/normal/eclipse';

console.log('Downloading OpenJDK 17 from Adoptium...');

function download(url, dest, cb) {
    https.get(url, (res) => {
        if (res.statusCode >= 300 && res.statusCode < 400 && res.headers.location) {
            console.log('Redirecting to:', res.headers.location);
            return download(res.headers.location, dest, cb);
        }
        if (res.statusCode !== 200) {
            return cb(new Error('Status ' + res.statusCode));
        }
        const fileStream = fs.createWriteStream(dest);
        const total = parseInt(res.headers['content-length'] || '0', 10);
        let downloaded = 0;
        res.on('data', chunk => {
            downloaded += chunk.length;
            if (total > 0 && Math.random() < 0.05) {
                process.stdout.write(`\rProgress: ${(downloaded / 1024 / 1024).toFixed(1)}MB / ${(total / 1024 / 1024).toFixed(1)}MB`);
            }
        });
        res.pipe(fileStream);
        fileStream.on('finish', () => {
            fileStream.close();
            console.log('\nDownload complete.');
            cb(null);
        });
    }).on('error', cb);
}

download(url, zipFile, (err) => {
    if (err) {
        console.error('Download error:', err);
        process.exit(1);
    }
    console.log('Extracting OpenJDK 17...');
    if (!fs.existsSync(targetDir)) fs.mkdirSync(targetDir, { recursive: true });
    
    try {
        execSync(`powershell -Command "Expand-Archive -Path '${zipFile}' -DestinationPath '${targetDir}_temp' -Force"`, { stdio: 'inherit' });
        // Adoptium extracts into a subdirectory jdk-17.x.x
        const entries = fs.readdirSync(`${targetDir}_temp`);
        const sub = entries[0];
        const subPath = path.join(`${targetDir}_temp`, sub);
        for (const item of fs.readdirSync(subPath)) {
            fs.renameSync(path.join(subPath, item), path.join(targetDir, item));
        }
        fs.rmSync(`${targetDir}_temp`, { recursive: true, force: true });
        fs.unlinkSync(zipFile);
        console.log('OpenJDK 17 setup complete!');
    } catch (e) {
        console.error('Extraction error:', e);
    }
});
