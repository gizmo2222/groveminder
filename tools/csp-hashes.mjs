// Keeps the sha256 hashes in each page's Content-Security-Policy <meta> in step
// with that page's inline <script> blocks.
//   node tools/csp-hashes.mjs           rewrite the hashes
//   node tools/csp-hashes.mjs --check   exit 1 if any page is out of date
import { readFileSync, writeFileSync } from 'node:fs';
import { createHash } from 'node:crypto';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const check = process.argv.includes('--check');
let stale = 0;

for (const page of ['index.html', 'admin.html']) {
    const path = join(root, page);
    const html = readFileSync(path, 'utf8');
    // The browser hashes the script text after its HTML parser turns CRLF into LF,
    // so a Windows checkout (CRLF) and the deployed files (LF) get the same hashes
    const hashes = [...html.matchAll(/<script>([\s\S]*?)<\/script>/g)]
        .map(m => m[1].replace(/\r\n?/g, '\n'))
        .map(js => `'sha256-${createHash('sha256').update(js, 'utf8').digest('base64')}'`);
    const out = html.replace(/(<meta http-equiv="Content-Security-Policy" content="[^"]*script-src 'self' )(?:HASHES|(?:'sha256-[^']+' )*'sha256-[^']+')/,
        (_, head) => head + hashes.join(' '));
    if (out === html && !html.includes('HASHES')) {
        if (!/Content-Security-Policy/.test(html)) { console.error(`${page}: no CSP <meta>`); stale++; }
        else console.log(`${page}: ${hashes.length} inline scripts, hashes up to date`);
        continue;
    }
    if (check) { console.error(`${page}: CSP hashes are out of date. Run: node tools/csp-hashes.mjs`); stale++; }
    else { writeFileSync(path, out); console.log(`${page}: updated ${hashes.length} hashes`); }
}
process.exit(stale ? 1 : 0);
