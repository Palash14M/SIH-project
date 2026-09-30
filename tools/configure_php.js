const fs = require('fs');
const path = require('path');

const iniPath = path.resolve(__dirname, 'php', 'php.ini');
console.log('Reading:', iniPath);
let content = fs.readFileSync(iniPath, 'utf8');

const extDir = path.resolve(__dirname, 'php', 'ext').replace(/\\/g, '/');
content = content.replace(/;?extension_dir\s*=\s*".*?"/g, `extension_dir = "${extDir}"`);
content = content.replace(/;?extension_dir\s*=\s*'ext'/g, `extension_dir = "${extDir}"`);

const extensions = [
    'curl',
    'fileinfo',
    'gd',
    'mbstring',
    'openssl',
    'pdo_mysql',
    'pdo_sqlite',
    'sqlite3'
];

for (const ext of extensions) {
    const regex = new RegExp(`;extension=${ext}`, 'g');
    content = content.replace(regex, `extension=${ext}`);
}

fs.writeFileSync(iniPath, content, 'utf8');
console.log('php.ini updated successfully!');
