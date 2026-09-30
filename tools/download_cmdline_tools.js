const fs = require('fs');
const path = require('path');
const https = require('https');

const url = 'https://dl.google.com/android/repository/commandlinetools-win-11076708_latest.zip';
const targetDir = path.resolve(__dirname, 'sdk');
const zipPath = path.join(targetDir, 'cmdline-tools.zip');

if (!fs.existsSync(targetDir)) fs.mkdirSync(targetDir, { recursive: true });

if (fs.existsSync(zipPath) && fs.statSync(zipPath).size > 150 * 1024 * 1024) {
    console.log('[EXISTS] cmdline-tools.zip already downloaded:', fs.statSync(zipPath).size);
    process.exit(0);
}

console.log('Downloading Android commandline tools from Google...');
const file = fs.createWriteStream(zipPath);

https.get(url, res => {
    if (res.statusCode !== 200) {
        console.error('Failed with status:', res.statusCode);
        process.exit(1);
    }
    const total = parseInt(res.headers['content-length'] || '0', 10);
    let downloaded = 0;
    let lastLog = Date.now();

    res.on('data', chunk => {
        downloaded += chunk.length;
        if (Date.now() - lastLog > 3000) {
            console.log(`Downloaded ${(downloaded / (1024*1024)).toFixed(1)} MB / ${(total / (1024*1024)).toFixed(1)} MB (${((downloaded/total)*100).toFixed(1)}%)`);
            lastLog = Date.now();
        }
    });

    res.pipe(file);

    file.on('finish', () => {
        file.close(() => {
            console.log('[SUCCESS] Downloaded cmdline-tools.zip:', (downloaded / (1024*1024)).toFixed(1), 'MB');
        });
    });
}).on('error', err => {
    console.error('Download error:', err.message);
    process.exit(1);
});
