const fs = require('fs');

['tests/test_part2.php', 'tests/test_part3.php', 'tests/test_part4.php', 'tests/test_part9_e2e.php'].forEach(f => {
  const c = fs.readFileSync(f, 'utf8');
  const m = [...c.matchAll(/password['"]\s*=>\s*['"]([^'"]+)['"]/g)].map(x => x[1]);
  console.log(f, [...new Set(m)]);
});
