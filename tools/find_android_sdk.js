const fs = require('fs');
const path = require('path');

function searchForFile(dir, targetName, maxDepth = 4, currentDepth = 0) {
    if (currentDepth > maxDepth) return [];
    let results = [];
    try {
        const entries = fs.readdirSync(dir, { withFileTypes: true });
        for (const entry of entries) {
            const fullPath = path.join(dir, entry.name);
            if (entry.isFile() && entry.name.toLowerCase() === targetName.toLowerCase()) {
                results.push(fullPath);
            } else if (entry.isDirectory()) {
                // skip windows system dirs
                const lower = entry.name.toLowerCase();
                if (lower === 'windows' || lower === '$recycle.bin' || lower === 'system volume information' || lower === 'node_modules') continue;
                results = results.concat(searchForFile(fullPath, targetName, maxDepth, currentDepth + 1));
            }
        }
    } catch (e) {}
    return results;
}

console.log('Searching common locations for android.jar...');
const searchRoots = [
    'C:\\Program Files',
    'C:\\Program Files (x86)',
    'C:\\Users\\Admin',
    'I:\\developments'
];

for (const root of searchRoots) {
    console.log(`Checking in ${root}...`);
    const hits = searchForFile(root, 'android.jar', 4);
    if (hits.length > 0) {
        console.log(`FOUND in ${root}:`, hits);
    }
}
console.log('Search finished.');
