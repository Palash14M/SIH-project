const fs = require('fs');
const html = fs.readFileSync('preview/index.html', 'utf8');

const crowns = (html.match(/👑/g) || []).length;
const hasScreenLogin = html.includes('id="screenLogin"');
const hasDropdown = html.includes('id="loginPositionSelect"');
const hasManifest = html.includes('rel="manifest"');
const hasSignOut = html.includes('handleAppLogout');
const hasUserGreeting = html.includes('id="userGreeting"');

console.log('Crown count:', crowns);
console.log('Has screenLogin:', hasScreenLogin);
console.log('Has position dropdown:', hasDropdown);
console.log('Has PWA manifest link:', hasManifest);
console.log('Has Sign Out button:', hasSignOut);
console.log('Has User Greeting:', hasUserGreeting);

// Check if any script syntax errors in index.html
const scriptMatches = [...html.matchAll(/<script(?![^>]*src=)[^>]*>([\s\S]*?)<\/script>/gi)];
console.log('Inline scripts found:', scriptMatches.length);
let scriptErrors = 0;
scriptMatches.forEach((m, idx) => {
    try {
        new Function(m[1]);
        console.log(`Script ${idx + 1}: Valid syntax`);
    } catch (e) {
        console.error(`Script ${idx + 1} Syntax Error:`, e.message);
        scriptErrors++;
    }
});
if (scriptErrors === 0) {
    console.log('ALL INLINE JAVASCRIPT VALID!');
} else {
    process.exit(1);
}
