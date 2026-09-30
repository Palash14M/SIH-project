const fs = require('fs');
const path = require('path');

const srcLogo = path.join(__dirname, '..', 'android', 'app', 'src', 'main', 'res', 'drawable', 'watermark_logo.png');
const resDir = path.join(__dirname, '..', 'android', 'app', 'src', 'main', 'res');

const mipmapDirs = [
    'mipmap-mdpi',
    'mipmap-hdpi',
    'mipmap-xhdpi',
    'mipmap-xxhdpi',
    'mipmap-xxxhdpi'
];

mipmapDirs.forEach(dirName => {
    const fullDir = path.join(resDir, dirName);
    if (!fs.existsSync(fullDir)) {
        fs.mkdirSync(fullDir, { recursive: true });
    }
    fs.copyFileSync(srcLogo, path.join(fullDir, 'ic_launcher.png'));
    fs.copyFileSync(srcLogo, path.join(fullDir, 'ic_launcher_round.png'));
});

console.log('App icons generated across all mipmap densities!');
